import { Component, OnDestroy, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService, DocumentoService, IdentidadFirmaService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import {
  documentoSeFirma,
  esPdf,
  estadoFirmaLegible,
  guardarArchivo,
  mensajeErrorApi,
  nombreArchivoDocumento,
  nombreDocumento,
  severidadFirma,
} from '../../../core/utils';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { LienzoFirmaComponent } from '../../../shared/components/lienzo-firma/lienzo-firma.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { VisorDocumentoComponent } from '../../../shared/components/visor-documento/visor-documento.component';

/**
 * Mis Documentos: lo que el trabajador tiene a su nombre.
 *
 * La lista la corta y la cuenta el backend (MisDocumentosController). Acá
 * además el trabajador sube su propia hoja de vida, que antes solo podía
 * cargar RR.HH. Lo que ve RR.HH. de todo el personal es otra pantalla:
 * Documentos del personal (expedientes-list).
 *
 * Los nombres de los documentos y el visor son los mismos que usa el
 * expediente de RR.HH. (core/utils/documentos y app-visor-documento).
 */
@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorArchivoComponent, VisorDocumentoComponent,
    LienzoFirmaComponent, IconComponent, PistaDirective,
  ],
  templateUrl: './documentos-list.component.html',
})
export class DocumentosListComponent implements OnInit, OnDestroy {
  private misDocumentosService = inject(MisDocumentosService);
  private documentoService = inject(DocumentoService);
  private toastService = inject(ToastService);
  private identidadFirmaService = inject(IdentidadFirmaService);
  private authService = inject(AuthService);

  /** El nombre que va debajo de la línea, como en la boleta. */
  readonly miNombre = this.authService.getUser()?.name ?? '';

  documentos: Documento[] = [];
  cargando = false;

  /** Filas por página; el servidor corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  total = 0;
  pendientes = 0;
  firmados = 0;

  // ── Firmar ──
  modalFirmar = false;
  docAFirmar: Documento | null = null;
  passwordFirma = '';
  firmando = false;

  // ── Subir la hoja de vida ──
  modalCv = false;
  cv: File[] = [];
  subiendoCv = false;

  // ── Mi firma ──
  /** La imagen registrada, como URL de memoria; null si todavía no tiene. */
  firmaUrl: string | null = null;
  cargandoFirma = true;
  modalFirma = false;
  guardandoFirma = false;
  hayTrazo = false;
  @ViewChild(LienzoFirmaComponent) private lienzo?: LienzoFirmaComponent;

  /** El documento abierto en el visor. */
  docAbierto: Documento | null = null;

  readonly nombreDe = nombreDocumento;

  /** Los números de la cabecera van sobre TODOS sus documentos, no la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'folder_shared', valor: this.total, etiqueta: 'Documentos', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Firmados', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Por firmar', tono: 'warning' },
    ];
  }

  columnas: ColumnaTabla<Documento>[] = [
    { campo: 'tipo', header: 'Documento', ancho: '38%', formatear: (_v, doc) => nombreDocumento(doc) },
    { campo: 'created_at', header: 'Fecha', tipo: 'fecha', ancho: '18%' },
    {
      campo: 'estado_firma', header: 'Estado', tipo: 'badge', ancho: '18%',
      formatear: (_v, doc) => estadoFirmaLegible(doc),
      badgeSeveridad: (_v, doc) => severidadFirma(doc),
    },
  ];

  /**
   * Los botones se esconden cuando no aplican en vez de quedarse apagados:
   * un botón que nunca se va a poder pulsar solo estorba. La hoja de vida se
   * ve y se descarga, pero no se firma.
   */
  acciones: AccionPersonalizada<Documento>[] = [
    { id: 'ver', titulo: 'Ver este documento', icono: 'description', visible: (doc) => esPdf(doc) },
    { id: 'descargar', titulo: 'Descargar este documento', icono: 'folder_open' },
    {
      id: 'visto', titulo: 'Marcar que ya lo viste', icono: 'check_circle',
      visible: (doc) => documentoSeFirma(doc) && doc.estado_firma === 'pendiente',
    },
    {
      id: 'firmar', titulo: 'Firmar este documento', icono: 'signature', severidad: 'success',
      visible: (doc) => documentoSeFirma(doc) && doc.estado_firma !== 'firmado',
    },
  ];

  ngOnInit(): void {
    this.cargar();
    this.cargarFirma();
  }

  ngOnDestroy(): void {
    this.olvidarFirma();
  }

  // ═══ Mi firma ════════════════════════════════════════════════

  private cargarFirma(): void {
    this.cargandoFirma = true;
    this.identidadFirmaService.verMia().subscribe({
      next: (blob) => {
        this.olvidarFirma();
        this.firmaUrl = URL.createObjectURL(blob);
        this.cargandoFirma = false;
      },
      // 404 es lo normal la primera vez: todavía no dibujó ninguna.
      error: () => {
        this.olvidarFirma();
        this.cargandoFirma = false;
      },
    });
  }

  abrirFirma(): void {
    this.hayTrazo = false;
    this.modalFirma = true;
  }

  cerrarFirma(): void {
    this.modalFirma = false;
  }

  async guardarFirma(): Promise<void> {
    const png = await this.lienzo?.exportarPng();
    if (!png) {
      this.toastService.warning('Falta tu firma', 'Dibújala en el recuadro antes de guardar.');
      return;
    }

    this.guardandoFirma = true;
    this.identidadFirmaService.subirMia(new File([png], 'firma.png', { type: 'image/png' })).subscribe({
      next: () => {
        this.guardandoFirma = false;
        this.modalFirma = false;
        this.cargarFirma();
        this.toastService.success('Firma guardada', 'Desde ahora sale en las boletas que firmes.');
      },
      error: (err) => {
        this.guardandoFirma = false;
        this.toastService.error('No se pudo guardar', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
      },
    });
  }

  private olvidarFirma(): void {
    if (this.firmaUrl) {
      URL.revokeObjectURL(this.firmaUrl);
      this.firmaUrl = null;
    }
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.misDocumentosService
      .paginar({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.documentos = res.data.content;
            this.total = res.data.totalElements;
            this.pendientes = res.data.pendientes ?? 0;
            this.firmados = res.data.firmados ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar tus documentos.'));
          this.cargando = false;
        },
      });
  }

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'ver') this.docAbierto = evento.fila;
    if (evento.accion === 'descargar') this.descargar(evento.fila);
    if (evento.accion === 'visto') this.marcarVisto(evento.fila);
    if (evento.accion === 'firmar') this.abrirFirmar(evento.fila);
  }

  descargar(doc: Documento): void {
    this.documentoService.descargar(doc.id).subscribe({
      next: (blob) => guardarArchivo(blob, nombreArchivoDocumento(doc)),
      error: (err) => this.toastService.error('No se pudo descargar', mensajeErrorApi(err, 'Intenta de nuevo.')),
    });
  }

  // ── Visto y firma ──

  marcarVisto(doc: Documento): void {
    if (doc.estado_firma !== 'pendiente') return;
    this.misDocumentosService.marcarVisto(doc.id).subscribe({
      next: (res) => { if (res.success) this.cargar(); },
      error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo marcar como visto.')),
    });
  }

  abrirFirmar(doc: Documento): void {
    this.docAFirmar = doc;
    this.passwordFirma = '';
    this.modalFirmar = true;
  }

  cerrarFirmar(): void {
    this.modalFirmar = false;
    this.docAFirmar = null;
    this.passwordFirma = '';
  }

  confirmarFirma(): void {
    if (!this.docAFirmar) return;
    if (!this.passwordFirma) {
      this.toastService.warning('Falta la contraseña', 'Escribe tu contraseña para firmar.');
      return;
    }

    this.firmando = true;
    this.misDocumentosService.firmar(this.docAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        this.firmando = false;
        if (res.success) {
          this.toastService.success('Firma registrada', `${nombreDocumento(this.docAFirmar)} quedó firmado.`);
          this.cerrarFirmar();
          this.cargar();
        }
      },
      error: (err) => {
        this.firmando = false;
        this.toastService.error('No se pudo firmar', mensajeErrorApi(err, 'Revisa tu contraseña e inténtalo de nuevo.'));
      },
    });
  }

  // ── La hoja de vida ──

  abrirSubirCv(): void {
    this.cv = [];
    this.modalCv = true;
  }

  cerrarSubirCv(): void {
    this.modalCv = false;
    this.cv = [];
  }

  subirCv(): void {
    const archivo = this.cv[0];
    if (!archivo) {
      this.toastService.warning('Falta el archivo', 'Elige el archivo de tu hoja de vida.');
      return;
    }

    this.subiendoCv = true;
    this.misDocumentosService.subirHojaDeVida(archivo).subscribe({
      next: (res) => {
        this.subiendoCv = false;
        if (res.success) {
          this.toastService.success('Hoja de vida guardada', 'Ya está en tu expediente.');
          this.cerrarSubirCv();
          this.cargar();
        }
      },
      error: (err) => {
        this.subiendoCv = false;
        this.toastService.error('No se pudo subir', mensajeErrorApi(err, 'Revisa el archivo e intenta de nuevo.'));
      },
    });
  }
}
