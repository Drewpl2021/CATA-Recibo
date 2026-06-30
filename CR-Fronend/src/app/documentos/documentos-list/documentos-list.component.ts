import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MisDocumentosService, MiDocumento } from '../../core/services/mis-documentos.service';
import { ToastService } from '../../core/services/toast.service';
import { AuthService } from '../../core/services/auth.service';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { BoletasService } from '../../core/services/boletas.service';

@Component({
  selector: 'app-documentos-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './documentos-list.component.html',
  styleUrl: './documentos-list.component.scss'
})
export class DocumentosListComponent implements OnInit {
  documentos: MiDocumento[] = [];
  searchTerm = '';
  cargando = false;

  // Modal de firmar
  mostrarModalFirmar = false;
  docAFirmar: MiDocumento | null = null;
  passwordFirma = '';
  firmando = false;

  // Propiedades de Admin/RRHH
  isEmpleado = true;
  empleados: Empleado[] = [];
  selectedEmpleadoId = '';
  descargandoPdf = false;

  constructor(
    private misDocumentosService: MisDocumentosService,
    private toastService: ToastService,
    private authService: AuthService,
    private empleadoService: EmpleadoService,
    private boletasService: BoletasService
  ) {}

  ngOnInit(): void {
    const user = this.authService.getUser();
    if (user) {
      let rolName = '';
      if (typeof user.rol === 'string') {
        rolName = user.rol;
      } else if (user.rol && typeof user.rol === 'object') {
        rolName = (user.rol as any).nombre || '';
      }
      const rol = rolName.toLowerCase();
      if (rol === 'admin' || rol === 'rrhh') {
        this.isEmpleado = false;
      }
    }

    if (this.isEmpleado) {
      this.cargarDocumentos();
    } else {
      this.cargarEmpleados();
    }
  }

  cargarDocumentos(): void {
    this.cargando = true;
    this.misDocumentosService.getMisDocumentos().subscribe({
      next: (res) => {
        if (res.success) this.documentos = res.data;
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error cargando documentos', err);
        this.cargando = false;
      }
    });
  }

  cargarEmpleados(): void {
    this.cargando = true;
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => {
        if (res.success) {
          this.empleados = res.data;
          // Si hay empleados, seleccionamos el primero por defecto para cargar sus boletas
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

  marcarVisto(doc: MiDocumento): void {
    if (doc.estado_firma !== 'pendiente') return;
    this.misDocumentosService.marcarVisto(doc.id).subscribe({
      next: (res) => {
        if (res.success) {
          doc.estado_firma = 'visto';
          doc.fecha_visto = res.data.fecha_visto;
        }
      },
      error: (err) => {
        console.error('Error marcando como visto', err);
        this.toastService.error('Error', err?.error?.message || 'Error al marcar como visto.');
      }
    });
  }

  abrirModalFirmar(doc: MiDocumento): void {
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
      this.toastService.warning('Atención', 'Por favor, ingresa tu contraseña para firmar.');
      return;
    }
    this.firmando = true;
    this.misDocumentosService.firmar(this.docAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        if (res.success) {
          const idx = this.documentos.findIndex(d => d.id === this.docAFirmar!.id);
          if (idx !== -1) this.documentos[idx] = res.data;
          this.toastService.success('¡Firma Exitosa!', 'Documento firmado correctamente.');
          this.cerrarModalFirmar();
        }
        this.firmando = false;
      },
      error: (err) => {
        console.error('Error firmando documento', err);
        this.toastService.error('Error de Firma', err?.error?.message || 'Error al firmar el documento.');
        this.firmando = false;
      }
    });
  }
}
