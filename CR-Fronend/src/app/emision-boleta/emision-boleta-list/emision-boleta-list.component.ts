import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

export interface EmpleadoParaBoleta {
  id: string;
  nombresApellidos: string;
  cargo: string;
  nivel: string;
  estado: 'Activo' | 'Vacaciones';
}

@Component({
  selector: 'app-emision-boleta-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './emision-boleta-list.component.html',
  styleUrl: './emision-boleta-list.component.scss'
})
export class EmisionBoletaListComponent implements OnInit {
  empleados: EmpleadoParaBoleta[] = [];
  searchTerm = '';

  constructor(private router: Router) {}

  ngOnInit(): void {
    // Datos simulados (mock) idénticos a los de empleados para consistencia
    this.empleados = [
      {
        id: 'EMP-001',
        nombresApellidos: 'Carlos Mamani',
        cargo: 'Docente',
        nivel: 'Secundaria',
        estado: 'Activo'
      },
      {
        id: 'EMP-002',
        nombresApellidos: 'Maria Quispe',
        cargo: 'Directora',
        nivel: 'Colegio',
        estado: 'Activo'
      },
      {
        id: 'EMP-003',
        nombresApellidos: 'Juan Perez',
        cargo: 'Auxiliar',
        nivel: 'Primaria',
        estado: 'Vacaciones'
      }
    ];
  }

  get filteredEmpleados(): EmpleadoParaBoleta[] {
    if (!this.searchTerm) return this.empleados;
    const lower = this.searchTerm.toLowerCase();
    return this.empleados.filter(e => 
      e.nombresApellidos.toLowerCase().includes(lower) || 
      e.cargo.toLowerCase().includes(lower)
    );
  }

  irADescuentos(empleado: EmpleadoParaBoleta): void {
    this.router.navigate(['/inicio/emision-boleta/descuentos', empleado.id], {
      state: { nombre: empleado.nombresApellidos } // Pasamos el nombre por state para no tener que consultarlo en mock
    });
  }
}
