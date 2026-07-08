import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { PlanillaService, Planilla } from '../../core/services/planilla.service';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  selector: 'app-planillas-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './planillas-list.component.html',
  styleUrl: './planillas-list.component.scss'
})
export class PlanillasListComponent implements OnInit {
  planillas: Planilla[] = [];
  empleados: Empleado[] = [];
  cargando = false;
  eliminando: string | null = null;

  // Filtros
  filtroMes: number = new Date().getMonth() + 1;
  filtroAnio: number = new Date().getFullYear();
  filtroEmpleado: string = '';
  searchTerm: string = '';

  // Confirmación de eliminación
  planillaAEliminar: Planilla | null = null;
  showConfirmDelete = false;

  aniosDisponibles: number[] = [];
  meses = [
    { num: 1, nombre: 'Enero' }, { num: 2, nombre: 'Febrero' },
    { num: 3, nombre: 'Marzo' }, { num: 4, nombre: 'Abril' },
    { num: 5, nombre: 'Mayo' }, { num: 6, nombre: 'Junio' },
    { num: 7, nombre: 'Julio' }, { num: 8, nombre: 'Agosto' },
    { num: 9, nombre: 'Setiembre' }, { num: 10, nombre: 'Octubre' },
    { num: 11, nombre: 'Noviembre' }, { num: 12, nombre: 'Diciembre' }
  ];

  constructor(
    private planillaService: PlanillaService,
    private empleadoService: EmpleadoService,
    private toastService: ToastService,
    private router: Router
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 3; i <= currentYear + 1; i++) {
      this.aniosDisponibles.push(i);
    }
    this.cargarPlanillas();
    this.cargarEmpleados();
  }

  cargarPlanillas(): void {
    this.cargando = true;
    const filtros: any = { mes: this.filtroMes, anio: this.filtroAnio };
    if (this.filtroEmpleado) filtros.empleado_id = this.filtroEmpleado;

    this.planillaService.getPlanillas(filtros).subscribe({
      next: (res) => {
        if (res.success) this.planillas = res.data;
        this.cargando = false;
      },
      error: () => {
        this.toastService.error('Error', 'No se pudieron cargar las planillas.');
        this.cargando = false;
      }
    });
  }

  cargarEmpleados(): void {
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => { if (res.success) this.empleados = res.data; }
    });
  }

  get planillasFiltradas(): Planilla[] {
    if (!this.searchTerm) return this.planillas;
    const lower = this.searchTerm.toLowerCase();
    return this.planillas.filter(p => {
      if (!p.empleado) return false;
      const nombre = `${p.empleado.nombre ?? ''} ${p.empleado.apellido ?? ''}`.toLowerCase();
      const doc = p.empleado.dni ?? '';
      return nombre.includes(lower) || doc.includes(lower);
    });
  }

  nombreMes(num: number): string {
    return this.meses.find(m => m.num === num)?.nombre || '';
  }

  nuevaPlanilla(): void {
    this.router.navigate(['/inicio/planillas/nuevo']);
  }

  editarPlanilla(id: string): void {
    this.router.navigate(['/inicio/planillas/editar', id]);
  }

  confirmarEliminar(planilla: Planilla): void {
    this.planillaAEliminar = planilla;
    this.showConfirmDelete = true;
  }

  cancelarEliminar(): void {
    this.planillaAEliminar = null;
    this.showConfirmDelete = false;
  }

  eliminarPlanilla(): void {
    if (!this.planillaAEliminar?.id) return;
    this.eliminando = this.planillaAEliminar.id;
    this.planillaService.eliminarPlanilla(this.planillaAEliminar.id).subscribe({
      next: () => {
        this.toastService.success('Eliminada', 'La planilla fue eliminada correctamente.');
        this.showConfirmDelete = false;
        this.planillaAEliminar = null;
        this.eliminando = null;
        this.cargarPlanillas();
      },
      error: () => {
        this.toastService.error('Error', 'No se pudo eliminar la planilla.');
        this.eliminando = null;
      }
    });
  }

  nombreEmpleado(p: Planilla): string {
    if (p.empleado && (p.empleado.nombre || p.empleado.apellido)) {
      return `${p.empleado.nombre ?? ''} ${p.empleado.apellido ?? ''}`.trim();
    }
    // Si no hay empleado, mostrar que es un empleado no encontrado
    return 'Empleado no encontrado';
  }

  get totalBonificaciones(): number {
    return this.planillasFiltradas.reduce((s, p) => s + Number(p.bonificaciones ?? 0), 0);
  }

  get totalDescuentos(): number {
    return this.planillasFiltradas.reduce((s, p) => s + Number(p.descuentos ?? 0), 0);
  }

  get totalMasa(): number {
    return this.planillasFiltradas.reduce((s, p) => s + Number(p.total ?? 0), 0);
  }
}
