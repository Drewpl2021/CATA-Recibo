import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  selector: 'app-empleados-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './empleados-list.component.html',
  styleUrl: './empleados-list.component.scss'
})
export class EmpleadosListComponent implements OnInit {
  empleados: Empleado[] = [];
  searchTerm = '';

  constructor(
    private router: Router,
    private empleadoService: EmpleadoService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    this.cargarEmpleados();
  }

  cargarEmpleados(): void {
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => {
        if (res.success) {
          this.empleados = res.data;
        }
      },
      error: (err) => {
        console.error('Error cargando empleados', err);
        this.toastService.error('Error', 'Hubo un error al cargar los empleados.');
      }
    });
  }

  get filteredEmpleados(): Empleado[] {
    if (!this.searchTerm) return this.empleados;
    const lower = this.searchTerm.toLowerCase();
    return this.empleados.filter(e => {
      const nombreCompleto = `${e.nombre} ${e.apellido}`.toLowerCase();
      const cargoStr = e.cargo?.nombre?.toLowerCase() || '';
      return nombreCompleto.includes(lower) || cargoStr.includes(lower);
    });
  }

  nuevoEmpleado(): void {
    this.router.navigate(['/inicio/empleados/nuevo']);
  }

  verPerfil(emp: Empleado): void {
    this.router.navigate(['/inicio/empleados/ver', emp.id]);
  }
}
