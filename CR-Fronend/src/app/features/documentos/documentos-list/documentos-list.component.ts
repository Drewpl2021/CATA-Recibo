import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MisDocumentosService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];

/** "Agosto 2026" a partir de la planilla del documento; si no tiene, su tipo. */
function periodoDe(doc: Documento): string {
  if (!doc.planilla) return doc.tipo;
  return `${MESES[doc.planilla.mes] || doc.planilla.mes} ${doc.planilla.anio}`;
}

/**
 * Mis Documentos: lo que el trabajador tiene a su nombre.
 *
 * La lista la corta y la cuenta el backend (MisDocumentosController). Antes
 * esta pantalla se traía todos los documentos de toda su carrera para pintar
 * los diez primeros, y filtraba el buscador en el navegador.
 */
@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, DataTableComponent],
  templateUrl: './documentos-list.component.html',
})
export class DocumentosListComponent implements OnInit {
  private misDocumentosService = inject(MisDocumentosService);
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

  // Modal de firmar
  mostrarModalFirmar = false;
  docAFirmar: Documento | null = null;
  passwordFirma = '';
  firmando = false;

  /** Los números de la cabecera van sobre TODOS sus documentos, no la página. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'folder_shared', valor: this.total, etiqueta: 'Documentos', tono: 'brand' },
      { icono: 'signature', valor: this.firmados, etiqueta: 'Firmados', tono: 'success' },
      { icono: 'clock', valor: this.pendientes, etiqueta: 'Por firmar', tono: 'warning' },
    ];
  }

  columnas: ColumnaTabla<Documento>[] = [
    { campo: 'planilla', header: 'Periodo', ancho: '22%', formatear: (_v, doc) => periodoDe(doc) },
    { campo: 'tipo', header: 'Tipo', ancho: '22%', formatear: (v) => this.tipoLegible(v) },
    { campo: 'created_at', header: 'Fecha de emisión', tipo: 'fecha', ancho: '20%' },
    {
      campo: 'estado_firma', header: 'Estado', tipo: 'badge', ancho: '15%',
      formatear: (v) => this.estadoLegible(v),
      badgeSeveridad: (v) => (v === 'firmado' ? 'success' : v === 'visto' ? 'info' : 'warning'),
    },
  ];

  /**
   * Los dos botones se esconden cuando ya no aplican en vez de quedarse
   * apagados: un botón deshabilitado que nunca se va a poder pulsar solo
   * estorba.
   */
  acciones: AccionPersonalizada<Documento>[] = [
    {
      id: 'visto', titulo: 'Marcar que ya lo viste', icono: 'check_circle',
      visible: (doc) => doc.estado_firma === 'pendiente',
    },
    {
      id: 'firmar', titulo: 'Firmar este documento', icono: 'signature', severidad: 'success',
      visible: (doc) => doc.estado_firma !== 'firmado',
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
    if (evento.accion === 'visto') this.marcarVisto(evento.fila);
    if (evento.accion === 'firmar') this.abrirModalFirmar(evento.fila);
  }

  periodo(doc: Documento | null): string {
    return doc ? periodoDe(doc) : '';
  }

  tipoLegible(tipo: string): string {
    const nombres: Record<string, string> = {
      boleta: 'Boleta',
      contrato: 'Contrato',
      cts: 'CTS',
      vacaciones_truncas: 'Vacaciones truncas',
      comprobante_transferencia: 'Comprobante de transferencia',
      hoja_de_vida: 'Hoja de vida',
      otro: 'Otro',
    };
    return nombres[tipo] ?? tipo;
  }

  estadoLegible(estado: string): string {
    if (estado === 'firmado') return 'Firmado';
    if (estado === 'visto') return 'Visto';
    return 'Pendiente';
  }

  marcarVisto(doc: Documento): void {
    if (doc.estado_firma !== 'pendiente') return;
    this.misDocumentosService.marcarVisto(doc.id).subscribe({
      next: (res) => {
        if (res.success) this.cargar();
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo marcar como visto.'));
      },
    });
  }

  abrirModalFirmar(doc: Documento): void {
    this.docAFirmar = doc;
    this.passwordFirma = '';
    this.mostrarModalFirmar = true;
  }

  cerrarModalFirmar(): void {
    this.docAFirmar = null;
    this.passwordFirma = '';
    this.mostrarModalFirmar = false;
  }

  confirmarFirma(): void {
    if (!this.docAFirmar || !this.passwordFirma) {
      this.toastService.warning('Atención', 'Ingresa tu contraseña para firmar.');
      return;
    }
    this.firmando = true;
    this.misDocumentosService.firmar(this.docAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.success('Firma registrada', 'El documento quedó firmado.');
          this.cerrarModalFirmar();
          this.cargar();
        }
        this.firmando = false;
      },
      error: (err) => {
        this.toastService.error(
          'No se pudo firmar',
          mensajeErrorApi(err, 'Revisa tu contraseña e inténtalo de nuevo.')
        );
        this.firmando = false;
      },
    });
  }
}
