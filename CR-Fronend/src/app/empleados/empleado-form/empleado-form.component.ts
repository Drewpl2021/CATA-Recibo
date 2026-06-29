import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { EmpleadoService } from '../../core/services/empleado.service';
import { AreaService, Area } from '../../core/services/area.service';
import { CargoService, Cargo } from '../../core/services/cargo.service';
import { SedeService, Sede } from '../../core/services/sede.service';
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
  
  // Campos de contrato
  sueldo_base: number | null = null;
  sede_id = '';

  // Campos de planilla
  sistema_pensiones = 'ONP';
  afp = '';
  cuspp = '';
  entidad_financiera = '';
  numero_cuenta = '';
  tiene_hijos = false;
  
  // Firma del empleado
  firma_imagen = '';
  archivoFirma: File | null = null;
  subiendoFirma = false;

  // Datos reales de la BD
  areasDisponibles: Area[] = [];
  cargosDisponibles: Cargo[] = [];
  sedesDisponibles: Sede[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private location: Location,
    private empleadoService: EmpleadoService,
    private areaService: AreaService,
    private cargoService: CargoService,
    private sedeService: SedeService,
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

    // Cargar áreas, cargos y sedes desde el backend
    this.cargarAreas();
    this.cargarCargos();
    this.cargarSedes();

    // Si es ver o editar, cargar datos del empleado
    if (this.modo !== 'nuevo' && this.empleadoId) {
      this.cargarEmpleado(this.empleadoId);
    }
  }

  cargarSedes(): void {
    this.sedeService.getSedes().subscribe({
      next: (res) => {
        if (res.success) this.sedesDisponibles = res.data;
      },
      error: (err) => console.error('Error cargando sedes', err)
    });
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
          this.fechaIngreso = e.fecha_ingreso ? e.fecha_ingreso.split('T')[0] : '';
          this.direccion = e.direccion || '';
          this.sueldo_base = e.sueldo_base !== undefined ? e.sueldo_base : null;
          this.sede_id = e.sede_id || '';
          
          this.sistema_pensiones = e.sistema_pensiones || 'ONP';
          this.afp = e.afp || '';
          this.cuspp = e.cuspp || '';
          this.entidad_financiera = e.entidad_financiera || '';
          this.numero_cuenta = e.numero_cuenta || '';
          this.tiene_hijos = e.tiene_hijos || false;
          this.firma_imagen = e.firma_imagen || '';
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
    if (!this.nombre || !this.apellido || !this.dni || !this.cargo_id || !this.area_id || !this.sede_id || !this.sueldo_base) {
      this.toastService.warning('Campos Incompletos', 'Por favor, completa los campos obligatorios: Nombre, Apellido, DNI, Cargo, Área, Sede y Sueldo.');
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
      direccion: this.direccion,
      sistema_pensiones: this.sistema_pensiones,
      afp: this.sistema_pensiones === 'AFP' ? this.afp : null,
      cuspp: this.sistema_pensiones === 'AFP' ? this.cuspp : null,
      entidad_financiera: this.entidad_financiera,
      numero_cuenta: this.numero_cuenta,
      tiene_hijos: this.tiene_hijos,
      sueldo_base: this.sueldo_base,
      sede_id: this.sede_id
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
          let msg = 'Error al actualizar el empleado.';
          if (err?.error?.errors) {
            const errores = Object.values(err.error.errors).flat() as string[];
            msg = errores.join(' | ');
          } else if (err?.error?.message) {
            msg = err.error.message;
          } else if (err?.message) {
            msg = err.message;
          }
          this.toastService.error('Error de validación', msg);
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
          let msg = 'Error al crear el empleado.';
          if (err?.error?.errors) {
            const errores = Object.values(err.error.errors).flat() as string[];
            msg = errores.join(' | ');
          } else if (err?.error?.message) {
            msg = err.error.message;
          }
          this.toastService.error('Error de validación', msg);
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

  onFirmaSeleccionada(event: any): void {
    const file = event.target?.files?.[0];
    if (file) {
      this.archivoFirma = file;
    }
  }

  subirFirma(): void {
    if (!this.empleadoId || !this.archivoFirma) {
      this.toastService.warning('Archivo requerido', 'Por favor, selecciona un archivo de imagen primero.');
      return;
    }

    this.subiendoFirma = true;
    this.empleadoService.subirFirma(this.empleadoId, this.archivoFirma).subscribe({
      next: (res) => {
        this.subiendoFirma = false;
        if (res.success) {
          this.firma_imagen = res.data.firma_imagen;
          this.archivoFirma = null;
          this.toastService.success('Firma cargada', 'La firma del empleado se ha subido exitosamente.');
        } else {
          this.toastService.error('Error', 'No se pudo subir la firma.');
        }
      },
      error: (err) => {
        this.subiendoFirma = false;
        console.error('Error subiendo firma', err);
        const msg = err?.error?.message || 'Error al subir el archivo de firma.';
        this.toastService.error('Error', msg);
      }
    });
  }
}
