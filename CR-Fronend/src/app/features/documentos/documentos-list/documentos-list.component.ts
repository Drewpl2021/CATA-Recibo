import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DocumentoService, MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { VisorPdfComponent } from '../../../shared/components/visor-pdf/visor-pdf.component';
import { nombreMes } from '../../../shared/constants';

/** Los que no se firman, igual que ExpedienteDigital::SIN_FIRMA en el backend. */
const SIN_FIRMA = ['hoja_de_vida'];

const NOMBRE_TIPO: Record<string, string> = {
  boleta: 'Boleta',
  contrato: 'Contrato',
  cts: 'CTS',
  vacaciones_truncas: 'Vacaciones truncas',
  comprobante_transferencia: 'Comprobante de transferencia',
  hoja_de_vida: 'Hoja de vida',
  otro: 'Documento',
};

/**
 * Mis Documentos: lo que el trabajador tiene a su nombre.
 *
 * La lista la corta y la cuenta el backend (MisDocumentosController). Acá
 * además el trabajador sube su propia hoja de vida, que antes solo podía
 * cargar RR.HH.
 *
 * Todo con los componentes compartidos: la tabla, el modal de formulario, el
 * selector de archivos y el visor de PDF. El modal de firmar estaba hecho a
 * mano con estilos sueltos y se veía distinto al resto —el título pegado al
 * borde, el campo sin márgenes—.
 */
@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorArchivoComponent, VisorPdfComponent,
  ],
  templateUrl: './documentos-list.component.html',
})
export class DocumentosListComponent implements OnInit {
  private misDocumentosService = inject(MisDocumentosService);
  private documentoService = inject(DocumentoService);
  private toastService = inject(ToastService);

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

  // ── Ver un PDF ──
  modalVisor = false;
  docEnVisor: Documento | null = null;
  urlVisor: string | null = null;
  private blobVisor: Blob | null = null;

  /** Los números de la cabecera van sobre TODOS sus documentos, no la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'folder_shared', valor: this.total, etiqueta: 'Documentos', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Firmados', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Por firmar', tono: 'warning' },
    ];
  }

  columnas: ColumnaTabla<Documento>[] = [
    { campo: 'tipo', header: 'Documento', ancho: '38%', formatear: (_v, doc) => this.nombreDe(doc) },
    { campo: 'created_at', header: 'Fecha', tipo: 'fecha', ancho: '18%' },
    {
      campo: 'estado_firma', header: 'Estado', tipo: 'badge', ancho: '18%',
      formatear: (v, doc) => this.estadoLegible(v, doc),
      badgeSeveridad: (v, doc) => (!this.seFirma(doc) ? 'info' : v === 'firmado' ? 'success' : v === 'visto' ? 'info' : 'warning'),
    },
  ];

  /**
   * Los botones se esconden cuando no aplican en vez de quedarse apagados:
   * un botón que nunca se va a poder pulsar solo estorba. La hoja de vida se
   * ve y se descarga, pero no se firma.
   */
  acciones: AccionPersonalizada<Documento>[] = [
    { id: 'ver', titulo: 'Ver este documento', icono: 'description', visible: (doc) => this.esPdf(doc) },
    { id: 'descargar', titulo: 'Descargar este documento', icono: 'folder_open' },
    {
      id: 'visto', titulo: 'Marcar que ya lo viste', icono: 'check_circle',
      visible: (doc) => this.seFirma(doc) && doc.estado_firma === 'pendiente',
    },
    {
      id: 'firmar', titulo: 'Firmar este documento', icono: 'signature', severidad: 'success',
      visible: (doc) => this.seFirma(doc) && doc.estado_firma !== 'firmado',
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

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'ver') this.ver(evento.fila);
    if (evento.accion === 'descargar') this.descargar(evento.fila);
    if (evento.accion === 'visto') this.marcarVisto(evento.fila);
    if (evento.accion === 'firmar') this.abrirFirmar(evento.fila);
  }

  // ── Cómo se nombra cada documento ──

  /** "Boleta de Agosto 2026", "Hoja de vida", "Contrato". */
  nombreDe(doc: Documento | null): string {
    if (!doc) return '';
    const tipo = NOMBRE_TIPO[doc.tipo] ?? 'Documento';
    return doc.planilla ? `${tipo} de ${nombreMes(doc.planilla.mes)} ${doc.planilla.anio}` : tipo;
  }

  seFirma(doc: Documento): boolean {
    return !SIN_FIRMA.includes(doc.tipo);
  }

  estadoLegible(estado: string, doc: Documento): string {
    if (!this.seFirma(doc)) return 'Guardada';
    if (estado === 'firmado') return 'Firmado';
    if (estado === 'visto') return 'Visto';
    return 'Pendiente';
  }

  private esPdf(doc: Documento): boolean {
    return (doc.archivo ?? '').toLowerCase().endsWith('.pdf');
  }

  private extension(doc: Documento): string {
    const nombre = doc.archivo ?? '';
    return nombre.includes('.') ? nombre.slice(nombre.lastIndexOf('.') + 1).toLowerCase() : 'pdf';
  }

  // ── Ver y descargar ──

  ver(doc: Documento): void {
    this.documentoService.descargar(doc.id).subscribe({
      next: (blob) => {
        this.cerrarVisor();
        this.blobVisor = blob;
        this.urlVisor = URL.createObjectURL(blob);
        this.docEnVisor = doc;
        this.modalVisor = true;
      },
      error: (err) => this.toastService.error('No se pudo abrir', mensajeErrorApi(err, 'Intenta descargarlo.')),
    });
  }

  cerrarVisor(): void {
    this.modalVisor = false;
    if (this.urlVisor) URL.revokeObjectURL(this.urlVisor);
    this.urlVisor = null;
    this.blobVisor = null;
    this.docEnVisor = null;
  }

  descargarDelVisor(): void {
    if (this.blobVisor && this.docEnVisor) this.guardarComo(this.blobVisor, this.docEnVisor);
  }

  descargar(doc: Documento): void {
    this.documentoService.descargar(doc.id).subscribe({
      next: (blob) => this.guardarComo(blob, doc),
      error: (err) => this.toastService.error('No se pudo descargar', mensajeErrorApi(err, 'Intenta de nuevo.')),
    });
  }

  /**
   * El nombre del archivo se arma acá: el backend no expone la cabecera
   * Content-Disposition, así que el navegador no la puede leer.
   */
  private guardarComo(blob: Blob, doc: Documento): void {
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = `${this.nombreDe(doc)}.${this.extension(doc)}`;
    enlace.click();
    URL.revokeObjectURL(url);
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
          this.toastService.success('Firma registrada', `${this.nombreDe(this.docAFirmar)} quedó firmado.`);
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
