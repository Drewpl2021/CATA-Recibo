import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { EmpleadoService } from '../../core/services/empleado.service';
import { AreaService, Area } from '../../core/services/area.service';
import { CargoService, Cargo } from '../../core/services/cargo.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  selector: 'app-empleado-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './empleado-form.component.html',
  styleUrl: './empleado-form.component.scss'
})
export class EmpleadoFormComponent implements OnInit {
  modo: 'nuevo' | 'editar' | 'ver' = 'nuevo';
  empleadoId: string | null = null;
  titulo = 'NUEVO EMPLEADO';

  mostrarConfirmacion = false;
  cargando = false;
  guardando = false;

  // Campos del formulario
  nombre = '';
  apellido = '';
  dni = '';
  telefono = '';
  cargo_id = '';
  area_id = '';
  estado = 'Activo';
  fechaIngreso = '';
  direccion = '';

  // Datos reales de la BD
  areasDisponibles: Area[] = [];
  cargosDisponibles: Cargo[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private location: Location,
    private empleadoService: EmpleadoService,
    private areaService: AreaService,
    private cargoService: CargoService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    this.empleadoId = this.route.snapshot.paramMap.get('id');
    const url = this.router.url;

    if (url.includes('/ver/')) {
      this.modo = 'ver';
      this.titulo = 'PERFIL DEL EMPLEADO';
    } else if (url.includes('/editar/')) {
      this.modo = 'editar';
      this.titulo = 'EDITAR EMPLEADO';
    } else {
      this.modo = 'nuevo';
      this.titulo = 'NUEVO EMPLEADO';
    }

    // Cargar áreas y cargos desde el backend
    this.cargarAreas();
    this.cargarCargos();

    // Si es ver o editar, cargar datos del empleado
    if (this.modo !== 'nuevo' && this.empleadoId) {
      this.cargarEmpleado(this.empleadoId);
    }
  }

  cargarAreas(): void {
    this.areaService.getAreas().subscribe({
      next: (res) => {
        if (res.success) this.areasDisponibles = res.data;
      },
      error: (err) => console.error('Error cargando áreas', err)
    });
  }

  cargarCargos(): void {
    this.cargoService.getCargos().subscribe({
      next: (res) => {
        if (res.success) this.cargosDisponibles = res.data;
      },
      error: (err) => console.error('Error cargando cargos', err)
    });
  }

  cargarEmpleado(id: string): void {
    this.cargando = true;
    this.empleadoService.getEmpleado(id).subscribe({
      next: (res) => {
        if (res.success) {
          const e = res.data;
          this.nombre = e.nombre;
          this.apellido = e.apellido;
          this.dni = e.dni;
          this.telefono = e.telefono || '';
          this.cargo_id = e.cargo_id || '';
          this.area_id = e.area_id || '';
          this.estado = e.estado || 'Activo';
          this.fechaIngreso = e.fecha_ingreso || '';
          this.direccion = e.direccion || '';
        }
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error cargando empleado', err);
        this.toastService.error('Error', 'No se pudo cargar los datos del empleado.');
        this.cargando = false;
        this.router.navigate(['/inicio/empleados']);
      }
    });
  }

  guardar(): void {
    if (!this.nombre || !this.apellido || !this.dni || !this.cargo_id || !this.area_id) {
      this.toastService.warning('Campos Incompletos', 'Por favor, completa los campos obligatorios: Nombre, Apellido, DNI, Cargo y Área.');
      return;
    }

    const datos = {
      nombre: this.nombre,
      apellido: this.apellido,
      dni: this.dni,
      telefono: this.telefono,
      cargo_id: this.cargo_id,
      area_id: this.area_id,
      estado: this.estado,
      fecha_ingreso: this.fechaIngreso,
      direccion: this.direccion
    };

    this.guardando = true;

    if (this.modo === 'editar' && this.empleadoId) {
      this.empleadoService.updateEmpleado(this.empleadoId, datos).subscribe({
        next: (res) => {
          if (res.success) {
            this.toastService.success('Actualizado', `Empleado "${this.nombre} ${this.apellido}" actualizado exitosamente.`);
            this.router.navigate(['/inicio/empleados']);
          }
          this.guardando = false;
        },
        error: (err) => {
          console.error('Error actualizando empleado', err);
          const msg = err?.error?.message || 'Error al actualizar el empleado. Verifica los datos e intenta de nuevo.';
          this.toastService.error('Error', msg);
          this.guardando = false;
        }
      });
    } else {
      this.empleadoService.createEmpleado(datos).subscribe({
        next: (res) => {
          if (res.success) {
            this.toastService.success('Creado', `Empleado "${this.nombre} ${this.apellido}" creado exitosamente.`);
            this.router.navigate(['/inicio/empleados']);
          }
          this.guardando = false;
        },
        error: (err) => {
          console.error('Error creando empleado', err);
          const msg = err?.error?.message || 'Error al crear el empleado. Verifica los datos e intenta de nuevo.';
          this.toastService.error('Error', msg);
          this.guardando = false;
        }
      });
    }
  }

  irAEditar(): void {
    if (this.empleadoId) {
      this.router.navigate(['/inicio/empleados/editar', this.empleadoId]);
    }
  }

  confirmarEliminar(): void {
    this.mostrarConfirmacion = true;
  }

  cancelarEliminar(): void {
    this.mostrarConfirmacion = false;
  }

  eliminarEmpleado(): void {
    if (!this.empleadoId) return;
    this.empleadoService.deleteEmpleado(this.empleadoId).subscribe({
      next: () => {
        this.toastService.success('Eliminado', 'Empleado eliminado exitosamente.');
        this.mostrarConfirmacion = false;
        this.router.navigate(['/inicio/empleados']);
      },
      error: (err) => {
        console.error('Error eliminando empleado', err);
        this.toastService.error('Error', 'Error al eliminar el empleado.');
        this.mostrarConfirmacion = false;
      }
    });
  }

  cancelar(): void {
    this.router.navigate(['/inicio/empleados']);
  }
}
