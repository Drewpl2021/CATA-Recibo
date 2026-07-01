import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MisDocumentosService, MiDocumento } from '../../core/services/mis-documentos.service';
import { ToastService } from '../../core/services/toast.service';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { BoletasService } from '../../core/services/boletas.service';

@Component({
  selector: 'app-historial-boletas',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './historial-boletas.component.html',
  styleUrl: './historial-boletas.component.scss'
})
export class HistorialBoletasComponent implements OnInit {
  documentos: MiDocumento[] = [];
  searchTerm = '';
  cargando = false;

  // Propiedades de Admin/RRHH
  empleados: Empleado[] = [];
  selectedEmpleadoId = '';
  descargandoPdf = false;

  constructor(
    private misDocumentosService: MisDocumentosService,
    private toastService: ToastService,
    private empleadoService: EmpleadoService,
    private boletasService: BoletasService
  ) {}

  ngOnInit(): void {
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.cargando = true;
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => {
        if (res.success) {
          this.empleados = res.data;
          // Seleccionamos el primer empleado por defecto si hay alguno
          if (this.empleados.length > 0) {
            this.selectedEmpleadoId = this.empleados[0].id;
            this.cargarDocumentosAdmin();
          } else {
            this.cargando = false;
          }
        } else {
          this.cargando = false;
        }
      },
      error: (err) => {
        console.error('Error cargando empleados', err);
        this.cargando = false;
        this.toastService.error('Error', 'No se pudo cargar la lista de empleados.');
      }
    });
  }

  cargarDocumentosAdmin(): void {
    if (!this.selectedEmpleadoId) return;
    this.cargando = true;
    this.misDocumentosService.getDocumentosAdmin(this.selectedEmpleadoId).subscribe({
      next: (res) => {
        if (res.success) {
          this.documentos = res.data;
        }
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error cargando documentos del empleado', err);
        this.cargando = false;
        this.toastService.error('Error', 'No se pudieron cargar las boletas del empleado seleccionado.');
      }
    });
  }

  onEmpleadoChange(): void {
    this.searchTerm = '';
    this.cargarDocumentosAdmin();
  }

  descargarPdfAdmin(doc: MiDocumento): void {
    if (!doc.planilla) {
      this.toastService.error('Error', 'El documento no tiene planilla asociada para generar PDF.');
      return;
    }
    
    this.descargandoPdf = true;
    const mes = doc.planilla.mes;
    const anio = doc.planilla.anio;
    const empleadoId = doc.empleado_id;

    const nombreEmp = doc.empleado ? `${doc.empleado.nombre}_${doc.empleado.apellido}` : doc.empleado_id;

    this.boletasService.generarBoletaAdmin(empleadoId, mes, anio).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `boleta_${nombreEmp}_${mes}_${anio}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.descargandoPdf = false;
        this.toastService.success('Descarga Exitosa', 'La boleta PDF ha sido descargada.');
      },
      error: (err) => {
        console.error('Error generando boleta para admin', err);
        this.descargandoPdf = false;
        this.toastService.error('Error de Generación', 'No se pudo descargar la boleta PDF.');
      }
    });
  }

  get filteredDocumentos(): MiDocumento[] {
    if (!this.searchTerm) return this.documentos;
    const lower = this.searchTerm.toLowerCase();
    return this.documentos.filter(d => {
      const periodo = this.getPeriodo(d).toLowerCase();
      return periodo.includes(lower);
    });
  }

  getPeriodo(doc: MiDocumento): string {
    if (!doc.planilla) return doc.tipo;
    const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return `${meses[doc.planilla.mes] || doc.planilla.mes} ${doc.planilla.anio}`;
  }

  getEstadoLabel(doc: MiDocumento): string {
    switch (doc.estado_firma) {
      case 'firmado':  return 'Firmado';
      case 'visto':    return 'Visto';
      default:         return 'Pendiente';
    }
  }

  getEstadoClass(doc: MiDocumento): string {
    switch (doc.estado_firma) {
      case 'firmado':  return 'badge-active';
      case 'visto':    return 'badge-visto';
      default:         return 'badge-vacaciones';
    }
  }
}
