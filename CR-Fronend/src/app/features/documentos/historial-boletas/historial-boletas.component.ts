import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DocumentoService, ToastService, EmpleadoService, BoletaService } from '../../../core/services';
import { Documento, Empleado } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];

/**
 * Historial de Boletas: todo lo que se le ha emitido a un trabajador.
 *
 * RR.HH. elige de quién, y el backend manda la página (DocumentoController
 * con ?empleado_id&page). Un trabajador con cinco años de casa tiene sesenta
 * boletas: antes llegaban las sesenta para enseñar diez.
 */
@Component({
  selector: 'app-historial-boletas',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, DataTableComponent],
  templateUrl: './historial-boletas.component.html',
})
export class HistorialBoletasComponent implements OnInit {
  private documentoService = inject(DocumentoService);
  private toastService = inject(ToastService);
  private empleadoService = inject(EmpleadoService);
  private boletaService = inject(BoletaService);

  documentos: Documento[] = [];
  cargando = false;

  /** Solo id + nombre + DNI: es para el desplegable, no para una tabla. */
  empleados: Empleado[] = [];
  empleadoId = '';
  descargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  total = 0;

  columnas: ColumnaTabla<Documento>[] = [
    { campo: 'planilla', header: 'Periodo', ancho: '22%', formatear: (_v, doc) => this.periodo(doc) },
    { campo: 'tipo', header: 'Tipo', ancho: '22%', formatear: (v) => this.tipoLegible(v) },
    { campo: 'created_at', header: 'Fecha de emisión', tipo: 'fecha', ancho: '20%' },
    {
      campo: 'estado_firma', header: 'Estado', tipo: 'badge', ancho: '15%',
      formatear: (v) => this.estadoLegible(v),
      badgeSeveridad: (v) => (v === 'firmado' ? 'success' : v === 'visto' ? 'info' : 'warning'),
    },
  ];

  acciones: AccionPersonalizada<Documento>[] = [
    {
      id: 'descargar', titulo: 'Descargar esta boleta en PDF', icono: 'receipt_long',
      // Sin planilla no hay de dónde armar el PDF; el botón sobra.
      visible: (doc) => !!doc.planilla,
    },
  ];

  ngOnInit(): void {
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.cargando = true;
    this.empleadoService.paraSelector().subscribe({
      next: (res) => {
        if (res.success) {
          this.empleados = res.data;
          if (this.empleados.length > 0) {
            this.empleadoId = this.empleados[0].id;
            this.cargar();
            return;
          }
        }
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la lista de empleados.'));
      },
    });
  }

  /** Otro trabajador: se vuelve a la primera página y se limpia la búsqueda. */
  alCambiarEmpleado(): void {
    this.pagina = 0;
    this.busqueda = '';
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
    if (!this.empleadoId) return;
    this.cargando = true;
    this.documentoService
      .getPagina({
        empleado_id: this.empleadoId,
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.documentos = res.data.content;
            this.total = res.data.totalElements;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.cargando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las boletas del empleado.'));
        },
      });
  }

  alAccionar(evento: { accion: string; fila: Documento }): void {
    if (evento.accion === 'descargar') this.descargarPdf(evento.fila);
  }

  descargarPdf(doc: Documento): void {
    if (!doc.planilla) {
      this.toastService.error('Sin planilla', 'Este documento no tiene planilla, así que no se puede armar el PDF.');
      return;
    }

    this.descargando = true;
    const { mes, anio } = doc.planilla;
    const nombre = doc.empleado ? `${doc.empleado.nombre}_${doc.empleado.apellido}` : doc.empleado_id;

    this.boletaService.generarBoletaEmpleado(doc.empleado_id, mes, anio).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `boleta_${nombre}_${mes}_${anio}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.descargando = false;
        this.toastService.success('Boleta descargada', 'El PDF ya está en tus descargas.');
      },
      error: (err) => {
        this.descargando = false;
        this.toastService.error('No se pudo descargar', mensajeErrorApi(err, 'No se pudo generar el PDF de la boleta.'));
      },
    });
  }

  periodo(doc: Documento): string {
    if (!doc.planilla) return this.tipoLegible(doc.tipo);
    return `${MESES[doc.planilla.mes] || doc.planilla.mes} ${doc.planilla.anio}`;
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
}
