import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

export interface EmpleadoMock {
  id: string;
  nombresApellidos: string;
  cargo: string;
  nivel: string;
  celular: string;
  estado: 'Activo' | 'Vacaciones';
}

@Component({
  selector: 'app-empleados-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './empleados-list.component.html',
  styleUrl: './empleados-list.component.scss'
})
export class EmpleadosListComponent implements OnInit {
  empleados: EmpleadoMock[] = [];
  searchTerm = '';
  mostrarConfirmacion = false;
  empleadoAEliminar: EmpleadoMock | null = null;

  constructor(private router: Router) {}

  ngOnInit(): void {
    this.empleados = [
      {
        id: 'EMP-001',
        nombresApellidos: 'Carlos Mamani',
        cargo: 'Docente',
        nivel: 'Secundaria',
        celular: '987654321',
        estado: 'Activo'
      },
      {
        id: 'EMP-002',
        nombresApellidos: 'Maria Quispe',
        cargo: 'Directora',
        nivel: 'Colegio',
        celular: '912345678',
        estado: 'Activo'
      },
      {
        id: 'EMP-003',
        nombresApellidos: 'Juan Perez',
        cargo: 'Auxiliar',
        nivel: 'Primaria',
        celular: '998877665',
        estado: 'Vacaciones'
      }
    ];
  }

  get filteredEmpleados(): EmpleadoMock[] {
    if (!this.searchTerm) return this.empleados;
    const lower = this.searchTerm.toLowerCase();
    return this.empleados.filter(e =>
      e.nombresApellidos.toLowerCase().includes(lower) ||
      e.cargo.toLowerCase().includes(lower) ||
      e.nivel.toLowerCase().includes(lower)
    );
  }

  nuevoEmpleado(): void {
    this.router.navigate(['/inicio/empleados/nuevo']);
  }

  verPerfil(emp: EmpleadoMock): void {
    this.router.navigate(['/inicio/empleados/ver', emp.id]);
  }

  editarEmpleado(emp: EmpleadoMock): void {
    this.router.navigate(['/inicio/empleados/editar', emp.id]);
  }

  confirmarEliminar(emp: EmpleadoMock): void {
    this.empleadoAEliminar = emp;
    this.mostrarConfirmacion = true;
  }

  cancelarEliminar(): void {
    this.empleadoAEliminar = null;
    this.mostrarConfirmacion = false;
  }

  eliminarEmpleado(): void {
    if (this.empleadoAEliminar) {
      this.empleados = this.empleados.filter(e => e.id !== this.empleadoAEliminar!.id);
      this.empleadoAEliminar = null;
      this.mostrarConfirmacion = false;
    }
  }
}
