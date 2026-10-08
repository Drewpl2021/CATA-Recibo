import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService, DocumentoService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import {
  documentoSeFirma,
  firmaResuelta,
  sePuedeVer,
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
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { VisorDocumentoComponent } from '../../../shared/components/visor-documento/visor-documento.component';
import { TIPO_DOCUMENTO_PROPIO } from '../../../shared/constants';

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
    IconComponent, PistaDirective,
  ],
  templateUrl: './documentos-list.component.html',
})
export class DocumentosListComponent implements OnInit {
  private misDocumentosService = inject(MisDocumentosService);
  private documentoService = inject(DocumentoService);
  private toastService = inject(ToastService);
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


  /** El documento abierto en el visor. */
  docAbierto: Documento | null = null;

  readonly nombreDe = nombreDocumento;

  /** Los números de la cabecera van sobre TODOS sus documentos, no la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'folder_shared', valor: this.total, etiqueta: 'Documentos', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Con conformidad', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Por confirmar', tono: 'warning' },
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
    // La boleta se ve después de firmarla; un contrato sí se lee antes.
    { id: 'ver', titulo: 'Ver este documento', icono: 'description',
      visible: (doc) => sePuedeVer(doc) && !(doc.tipo === 'boleta' && !firmaResuelta(doc)) },
    {
      id: 'descargar', titulo: 'Descargar este documento', icono: 'folder_open',
      // Lo que se firma se descarga después de firmarlo: leerlo, sí; llevárselo,
      // cuando ya lo aceptaste. Ver AccesoADocumento en el backend.
      visible: (doc) => this.puedeDescargar(doc),
    },
    {
      id: 'firmar', titulo: 'Dar tu conformidad: confirmas que lo recibiste', icono: 'check_circle', severidad: 'success',
      visible: (doc) => documentoSeFirma(doc) && !firmaResuelta(doc),
    },
  ];

  ngOnInit(): void {
    this.cargar();
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

  /** Lo que no se firma (hoja de vida, archivos anteriores) se baja siempre. */
  puedeDescargar(doc: Documento): boolean {
    return !documentoSeFirma(doc) || firmaResuelta(doc);
  }

  /**
   * Al cerrar el visor se recarga: abrirlo dejó anotado que lo revisó, y la
   * columna "Estado" y los números de arriba tienen que enterarse.
   */
  alCerrarVisor(doc: Documento | null): void {
    this.docAbierto = doc;
    if (!doc) this.cargar();
  }

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'ver') this.docAbierto = evento.fila;
    if (evento.accion === 'descargar') this.descargar(evento.fila);
    if (evento.accion === 'firmar') this.abrirFirmar(evento.fila);
  }

  descargar(doc: Documento): void {
    this.documentoService.descargar(doc.id).subscribe({
      next: (blob) => guardarArchivo(blob, nombreArchivoDocumento(doc)),
      error: (err) => this.toastService.error('No se pudo descargar', mensajeErrorApi(err, 'Intenta de nuevo.')),
    });
  }

  // ── Visto y firma ──
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
      this.toastService.warning('Falta la contraseña', 'Escribe tu contraseña para dar tu conformidad.');
      return;
    }

    this.firmando = true;
    this.misDocumentosService.firmar(this.docAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        this.firmando = false;
        if (res.success) {
          this.toastService.success('Conformidad registrada', `Diste tu conformidad a ${nombreDocumento(this.docAFirmar)}.`);
          this.cerrarFirmar();
          this.cargar();
        }
      },
      error: (err) => {
        this.firmando = false;
        this.toastService.error('No se registró', mensajeErrorApi(err, 'Revisa tu contraseña e inténtalo de nuevo.'));
      },
    });
  }

  // ── Sus propios documentos ──

  /** Lo que puede subir él: sus papeles, no lo que emite el colegio. */
  tiposPropios = TIPO_DOCUMENTO_PROPIO;

  /** Qué está subiendo. Arranca en la hoja de vida, que es lo más común. */
  tipoASubir = 'hoja_de_vida';

  /** El nombre del tipo elegido, para los textos del modal. */
  get nombreTipoASubir(): string {
    return this.tiposPropios.find((t) => t.value === this.tipoASubir)?.label ?? 'documento';
  }

  abrirSubirCv(tipo = 'hoja_de_vida'): void {
    this.tipoASubir = tipo;
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
      this.toastService.warning('Falta el archivo', `Elige el archivo de tu ${this.nombreTipoASubir.toLowerCase()}.`);
      return;
    }

    this.subiendoCv = true;
    this.misDocumentosService.subirHojaDeVida(archivo, this.tipoASubir).subscribe({
      next: (res) => {
        this.subiendoCv = false;
        if (res.success) {
          this.toastService.success(`${this.nombreTipoASubir} guardada`, 'Ya está en tu expediente.');
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
