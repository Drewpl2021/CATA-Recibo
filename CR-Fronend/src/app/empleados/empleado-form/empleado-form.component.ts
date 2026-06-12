import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

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

  // Campos del formulario
  nombre = '';
  apellido = '';
  dni = '';
  email = '';
  telefono = '';
  cargo = '';
  nivel = '';
  estado = 'Activo';
  fechaIngreso = '';
  direccion = '';

  cargosDisponibles = ['Docente', 'Director', 'Directora', 'Auxiliar', 'Administrador', 'Secretaria', 'Psicólogo'];
  nivelesDisponibles = ['Primaria', 'Secundaria', 'Colegio', 'Inicial'];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private location: Location
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

    if (this.modo !== 'nuevo') {
      // Mock: cargar datos del empleado seleccionado
      // Cuando esté conectado al backend, aquí se haría: this.empleadoService.getById(this.empleadoId)
      this.nombre = 'Carlos';
      this.apellido = 'Mamani';
      this.dni = '12345678';
      this.email = 'cmamani@colegio.com';
      this.telefono = '987654321';
      this.cargo = 'Docente';
      this.nivel = 'Secundaria';
      this.estado = 'Activo';
      this.fechaIngreso = '2024-01-01';
      this.direccion = 'Av. Principal 123';
    }
  }

  guardar(): void {
    if (!this.nombre || !this.apellido || !this.dni || !this.cargo) {
      alert('Por favor, completa los campos obligatorios: Nombre, Apellido, DNI y Cargo.');
      return;
    }

    const datos = {
      nombre: this.nombre,
      apellido: this.apellido,
      dni: this.dni,
      email: this.email,
      telefono: this.telefono,
      cargo: this.cargo,
      nivel: this.nivel,
      estado: this.estado,
      fechaIngreso: this.fechaIngreso,
      direccion: this.direccion
    };

    console.log(this.modo === 'editar' ? 'Actualizando empleado:' : 'Creando empleado:', datos);
    const msg = this.modo === 'editar'
      ? `Empleado "${this.nombre} ${this.apellido}" actualizado exitosamente.`
      : `Empleado "${this.nombre} ${this.apellido}" creado exitosamente.`;
    alert(msg);
    this.router.navigate(['/inicio/empleados']);
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
    // Lógica para eliminar el empleado
    console.log('Eliminando empleado', this.empleadoId);
    alert(`Empleado eliminado exitosamente.`);
    this.mostrarConfirmacion = false;
    this.router.navigate(['/inicio/empleados']);
  }


  cancelar(): void {
    this.router.navigate(['/inicio/empleados']);
  }
}
