import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { VacacionService, Vacacion } from '../../core/services/vacacion.service';
import { AuthService } from '../../core/services/auth.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  selector: 'app-vacaciones-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './vacaciones-list.component.html',
  styleUrl: './vacaciones-list.component.scss'
})
export class VacacionesListComponent implements OnInit {
  vacaciones: Vacacion[] = [];
  cargando = false;
  isAdmin = false;
  userRole = '';
  searchTerm = '';
  filtroEstado = 'todos';

  // Balance para docentes
  diasMaximos = 30;
  diasDisponibles = 30;
  diasAprobados = 0;
  diasPendientes = 0;

  // Modal Solicitud
  mostrarModalSolicitud = false;
  guardando = false;
  nuevaSolicitud: {
    fecha_inicio: string;
    fecha_fin: string;
    motivo: string;
    dias_solicitados: number;
  } = {
    fecha_inicio: '',
    fecha_fin: '',
    motivo: '',
    dias_solicitados: 1
  };

  // Modal Rechazo (Admin)
  mostrarModalRechazo = false;
  vacacionARechazar: Vacacion | null = null;
  motivoRechazo = '';

  constructor(
    private vacacionService: VacacionService,
    private authService: AuthService,
    private toastService: ToastService
  ) {
    const user = this.authService.getUser();
    let rolName = '';
    if (typeof user?.rol === 'string') {
      rolName = user.rol;
    } else if (user?.rol && typeof user.rol === 'object') {
      rolName = (user.rol as any).nombre || '';
    }
    this.userRole = rolName.toLowerCase();
    this.isAdmin = this.userRole === 'admin' || this.userRole === 'rrhh';
  }

  ngOnInit(): void {
    this.cargarVacaciones();
  }

  cargarVacaciones(): void {
    this.cargando = true;
    const empleadoId = this.authService.getEmpleadoId();

    const filtros: { empleado_id?: string } = {};
    if (!this.isAdmin && empleadoId) {
      filtros.empleado_id = empleadoId;
    }

    this.vacacionService.getVacaciones(filtros).subscribe({
      next: (res) => {
        if (res.success) {
          this.vacaciones = res.data;
          if (!this.isAdmin) {
            this.calcularBalance();
          }
        }
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error al cargar vacaciones:', err);
        this.cargando = false;
        this.toastService.error('Error', 'No se pudieron cargar las solicitudes de vacaciones.');
      }
    });
  }

  calcularBalance(): void {
    let aprobados = 0;
    let pendientes = 0;

    for (const v of this.vacaciones) {
      if (v.estado === 'aprobado') {
        aprobados += Number(v.dias_solicitados || 0);
      } else if (v.estado === 'pendiente') {
        pendientes += Number(v.dias_solicitados || 0);
      }
    }

    this.diasAprobados = aprobados;
    this.diasPendientes = pendientes;
    this.diasDisponibles = Math.max(0, this.diasMaximos - aprobados - pendientes);
  }

  get filteredVacaciones(): Vacacion[] {
    let lista = this.vacaciones;

    if (this.filtroEstado !== 'todos') {
      lista = lista.filter(v => v.estado === this.filtroEstado);
    }

    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      lista = lista.filter(v => {
        const empNombre = v.empleado ? `${v.empleado.nombre} ${v.empleado.apellido}`.toLowerCase() : '';
        const dni = v.empleado?.dni || '';
        const motivo = (v.motivo || '').toLowerCase();
        return empNombre.includes(term) || dni.includes(term) || motivo.includes(term);
      });
    }

    return lista;
  }

  abrirModalSolicitud(): void {
    const hoy = new Date().toISOString().split('T')[0];
    this.nuevaSolicitud = {
      fecha_inicio: hoy,
      fecha_fin: hoy,
      motivo: '',
      dias_solicitados: 1
    };
    this.mostrarModalSolicitud = true;
  }

  cerrarModalSolicitud(): void {
    this.mostrarModalSolicitud = false;
  }

  calcularDias(): void {
    if (this.nuevaSolicitud.fecha_inicio && this.nuevaSolicitud.fecha_fin) {
      const ini = new Date(this.nuevaSolicitud.fecha_inicio);
      const fin = new Date(this.nuevaSolicitud.fecha_fin);
      const diffMs = fin.getTime() - ini.getTime();
      const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24)) + 1;
      this.nuevaSolicitud.dias_solicitados = diffDays > 0 ? diffDays : 0;
    }
  }

  guardarSolicitud(): void {
    const empleadoId = this.authService.getEmpleadoId();
    if (!empleadoId) {
      this.toastService.error('Error', 'Tu usuario no tiene un perfil de empleado vinculado.');
      return;
    }

    if (!this.nuevaSolicitud.fecha_inicio || !this.nuevaSolicitud.fecha_fin) {
      this.toastService.warning('Campos Requeridos', 'Indica fecha de inicio y fecha de fin.');
      return;
    }

    if (this.nuevaSolicitud.dias_solicitados <= 0) {
      this.toastService.error('Error', 'La fecha de fin debe ser posterior a la fecha de inicio.');
      return;
    }

    if (this.nuevaSolicitud.dias_solicitados > this.diasDisponibles) {
      this.toastService.error(
        'Saldo Insuficiente',
        `Solo tienes ${this.diasDisponibles} días disponibles (solicitaste ${this.nuevaSolicitud.dias_solicitados}).`
      );
      return;
    }

    this.guardando = true;
    const payload = {
      empleado_id: empleadoId,
      fecha_inicio: this.nuevaSolicitud.fecha_inicio,
      fecha_fin: this.nuevaSolicitud.fecha_fin,
      dias_solicitados: this.nuevaSolicitud.dias_solicitados,
      motivo: this.nuevaSolicitud.motivo,
      estado: 'pendiente' as const
    };

    this.vacacionService.crearVacacion(payload).subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success('¡Solicitud Enviada!', 'Tus vacaciones han sido registradas para revisión.');
          this.cerrarModalSolicitud();
          this.cargarVacaciones();
        }
      },
      error: (err) => {
        this.guardando = false;
        console.error('Error guardando vacación:', err);
        const msg = err.error?.message || 'Error al enviar la solicitud.';
        this.toastService.error('Error', msg);
      }
    });
  }

  aprobarVacacion(v: Vacacion): void {
    const user = this.authService.getUser();
    const adminNombre = user?.name || 'Administrador';

    this.vacacionService.actualizarVacacion(v.id, {
      estado: 'aprobado',
      aprobado_por: adminNombre
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.success('Aprobada', 'La solicitud de vacaciones ha sido aprobada.');
          this.cargarVacaciones();
        }
      },
      error: (err) => {
        console.error('Error aprobando vacación:', err);
        this.toastService.error('Error', 'No se pudo aprobar la solicitud.');
      }
    });
  }

  abrirModalRechazo(v: Vacacion): void {
    this.vacacionARechazar = v;
    this.motivoRechazo = '';
    this.mostrarModalRechazo = true;
  }

  cerrarModalRechazo(): void {
    this.vacacionARechazar = null;
    this.motivoRechazo = '';
    this.mostrarModalRechazo = false;
  }

  confirmarRechazo(): void {
    if (!this.vacacionARechazar) return;

    this.vacacionService.actualizarVacacion(this.vacacionARechazar.id, {
      estado: 'rechazado',
      motivo: this.motivoRechazo ? `${this.vacacionARechazar.motivo || ''} [Rechazo: ${this.motivoRechazo}]` : this.vacacionARechazar.motivo
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.warning('Rechazada', 'La solicitud ha sido marcada como rechazada.');
          this.cerrarModalRechazo();
          this.cargarVacaciones();
        }
      },
      error: (err) => {
        console.error('Error rechazando vacación:', err);
        this.toastService.error('Error', 'No se pudo rechazar la solicitud.');
      }
    });
  }

  cancelarSolicitudPropia(v: Vacacion): void {
    if (v.estado !== 'pendiente') return;

    if (!confirm('¿Estás seguro de que deseas cancelar esta solicitud?')) return;

    this.vacacionService.eliminarVacacion(v.id).subscribe({
      next: () => {
        this.toastService.success('Cancelada', 'Tu solicitud fue eliminada.');
        this.cargarVacaciones();
      },
      error: (err) => {
        console.error('Error cancelando vacacion:', err);
        this.toastService.error('Error', 'No se pudo cancelar la solicitud.');
      }
    });
  }
}
