import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { PlanillaService, Planilla } from '../../core/services/planilla.service';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  selector: 'app-planilla-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './planilla-form.component.html',
  styleUrl: './planilla-form.component.scss'
})
export class PlanillaFormComponent implements OnInit {
  modoEdicion = false;
  planillaId: string | null = null;
  cargando = false;
  guardando = false;

  empleados: Empleado[] = [];
  empleadoSeleccionado: Empleado | null = null;

  planilla: Planilla = {
    empleado_id: '',
    mes: new Date().getMonth() + 1,
    anio: new Date().getFullYear(),
    bonificaciones: 0,
    descuentos: 0,
  } as Planilla;

  meses = [
    { num: 1, nombre: 'Enero' }, { num: 2, nombre: 'Febrero' },
    { num: 3, nombre: 'Marzo' }, { num: 4, nombre: 'Abril' },
    { num: 5, nombre: 'Mayo' }, { num: 6, nombre: 'Junio' },
    { num: 7, nombre: 'Julio' }, { num: 8, nombre: 'Agosto' },
    { num: 9, nombre: 'Setiembre' }, { num: 10, nombre: 'Octubre' },
    { num: 11, nombre: 'Noviembre' }, { num: 12, nombre: 'Diciembre' }
  ];

  aniosDisponibles: number[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private planillaService: PlanillaService,
    private empleadoService: EmpleadoService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 3; i <= currentYear + 1; i++) {
      this.aniosDisponibles.push(i);
    }

    this.cargarEmpleados();

    this.planillaId = this.route.snapshot.paramMap.get('id');
    if (this.planillaId) {
      this.modoEdicion = true;
      this.cargarPlanilla(this.planillaId);
    }
  }

  cargarEmpleados(): void {
    this.empleadoService.getEmpleados().subscribe({
      next: (res) => {
        if (res.success) this.empleados = res.data;
      }
    });
  }

  cargarPlanilla(id: string): void {
    this.cargando = true;
    this.planillaService.getPlanilla(id).subscribe({
      next: (res) => {
        if (res.success) {
          this.planilla = {
            empleado_id: res.data.empleado_id,
            mes: res.data.mes,
            anio: res.data.anio,
            bonificaciones: res.data.bonificaciones,
            descuentos: res.data.descuentos,
            sueldo_base: res.data.sueldo_base,
            total: res.data.total,
          };
          this.onEmpleadoChange();
        }
        this.cargando = false;
      },
      error: () => {
        this.toastService.error('Error', 'No se pudo cargar la planilla.');
        this.cargando = false;
      }
    });
  }

  onEmpleadoChange(): void {
    this.empleadoSeleccionado = this.empleados.find(e => e.id === this.planilla.empleado_id) ?? null;
  }

  get sueldoBaseEstimado(): number {
    return this.empleadoSeleccionado?.sueldo_base ?? this.planilla.sueldo_base ?? 0;
  }

  get totalEstimado(): number {
    return this.sueldoBaseEstimado + Number(this.planilla.bonificaciones ?? 0) - Number(this.planilla.descuentos ?? 0);
  }

  get nombreMesSeleccionado(): string {
    return this.meses.find(m => m.num === Number(this.planilla.mes))?.nombre ?? '';
  }

  get formularioValido(): boolean {
    return !!this.planilla.empleado_id &&
           !!this.planilla.mes &&
           !!this.planilla.anio &&
           this.planilla.bonificaciones >= 0 &&
           this.planilla.descuentos >= 0;
  }

  guardar(): void {
    if (!this.formularioValido) {
      this.toastService.warning('Campos incompletos', 'Completa todos los campos requeridos.');
      return;
    }

    this.guardando = true;
    const body: Planilla = {
      empleado_id: this.planilla.empleado_id,
      mes: Number(this.planilla.mes),
      anio: Number(this.planilla.anio),
      bonificaciones: Number(this.planilla.bonificaciones ?? 0),
      descuentos: Number(this.planilla.descuentos ?? 0),
    };

    const operacion$ = this.modoEdicion && this.planillaId
      ? this.planillaService.actualizarPlanilla(this.planillaId, body)
      : this.planillaService.crearPlanilla(body);

    operacion$.subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.success(
            this.modoEdicion ? 'Planilla actualizada' : 'Planilla creada',
            `Planilla de ${this.empleadoSeleccionado?.nombre ?? ''} para ${this.nombreMesSeleccionado} ${this.planilla.anio} guardada.`
          );
          this.router.navigate(['/inicio/planillas']);
        }
        this.guardando = false;
      },
      error: (err) => {
        const msg = err?.error?.message || 'Ocurrió un error al guardar la planilla.';
        this.toastService.error('Error', msg);
        this.guardando = false;
      }
    });
  }

  cancelar(): void {
    this.router.navigate(['/inicio/planillas']);
  }
}
