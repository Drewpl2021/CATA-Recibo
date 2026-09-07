import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';

import {
  PlanillaService,
  PayrollDetalleService,
  PaymentConceptService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import { PaymentConcept, PayrollDetalle, PayrollDetallePayload, Planilla } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { nombreMes, TIPO_CONCEPTO_CORTO } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

/** Los tipos que restan del sueldo, para saber cómo pintar cada línea. */
const TIPOS_QUE_RESTAN = ['descuento', 'adelanto'];

const SEVERIDAD_POR_TIPO: Record<string, 'success' | 'danger' | 'info' | 'warning'> = {
  bonificacion: 'success',
  descuento: 'danger',
  aportacion: 'info',
  adelanto: 'warning',
};

/**
 * Conceptos de una planilla: las líneas que salen impresas en la boleta.
 *
 * Al crear la planilla, el backend ya le mete solos los conceptos de ley
 * (pensión, EsSalud, Asignación Familiar, Renta de 5ta) y los del catálogo
 * marcados como "aplica a todos". Desde acá se agregan los que son de esa
 * persona en ese mes: un descuento puntual, un adelanto, una bonificación
 * extraordinaria.
 *
 * Cada alta, cambio o baja de una línea hace que el backend recalcule el
 * total de la planilla, así que después de cada operación se vuelve a leer.
 */
@Component({
  selector: 'app-planilla-detalle',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './planilla-detalle.component.html',
})
export class PlanillaDetalleComponent implements OnInit {
  private fb = inject(FormBuilder);
  private ruta = inject(ActivatedRoute);
  private router = inject(Router);
  private planillaService = inject(PlanillaService);
  private detalleService = inject(PayrollDetalleService);
  private conceptoService = inject(PaymentConceptService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  planillaId = '';
  planilla: Planilla | null = null;
  detalles: PayrollDetalle[] = [];
  conceptos: PaymentConcept[] = [];

  cargando = false;
  guardando = false;
  modalVisible = false;
  detalleEditando: PayrollDetalle | null = null;

  columnas: ColumnaTabla<PayrollDetalle>[] = [
    {
      campo: 'payment_concept.nombre',
      header: 'Concepto',
      ancho: '32%',
      formatear: (valor) => valor || 'Concepto eliminado',
    },
    {
      campo: 'payment_concept.tipo',
      header: 'Tipo',
      ancho: '16%',
      tipo: 'badge',
      formatear: (valor) => TIPO_CONCEPTO_CORTO[valor] ?? String(valor ?? '—'),
      badgeSeveridad: (valor) => SEVERIDAD_POR_TIPO[valor] ?? 'secondary',
    },
    {
      campo: 'monto_calculado',
      header: 'Monto',
      ancho: '16%',
      formatear: (_v, fila) => this.montoConSigno(fila),
    },
    { campo: 'descripcion', header: 'Detalle' },
  ];

  form = this.fb.group({
    payment_concept_id: ['', [Validators.required]],
    monto_calculado: [null as number | null, [Validators.required, Validators.min(0)]],
    descripcion: ['', [Validators.maxLength(255)]],
  });

  ngOnInit(): void {
    this.planillaId = this.ruta.snapshot.paramMap.get('id') ?? '';
    this.cargar();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  get tituloPeriodo(): string {
    if (!this.planilla) return '';
    return `${nombreMes(this.planilla.mes)} ${this.planilla.anio}`;
  }

  get nombreEmpleado(): string {
    const e = this.planilla?.empleado;
    return e ? `${e.nombre ?? ''} ${e.apellido ?? ''}`.trim() : '';
  }

  /** ¿Este concepto resta del sueldo? */
  private resta(detalle: PayrollDetalle): boolean {
    return TIPOS_QUE_RESTAN.includes(detalle.payment_concept?.tipo ?? '');
  }

  /** "− S/ 120.00" o "+ S/ 250.00", según lo que haga el concepto. */
  montoConSigno(detalle: PayrollDetalle): string {
    const monto = Number(detalle.monto_calculado ?? 0);
    const signo = this.resta(detalle) ? '−' : '+';
    return `${signo} S/ ${monto.toFixed(2)}`;
  }

  /** Lo que suman los conceptos que aportan al sueldo. */
  get totalSuma(): number {
    return this.detalles
      .filter((d) => !this.resta(d) && d.payment_concept?.tipo !== 'aportacion')
      .reduce((s, d) => s + Number(d.monto_calculado ?? 0), 0);
  }

  /** Lo que descuentan. */
  get totalResta(): number {
    return this.detalles
      .filter((d) => this.resta(d))
      .reduce((s, d) => s + Number(d.monto_calculado ?? 0), 0);
  }

  /**
   * Las aportaciones (EsSalud, SCTR) las paga el colegio: no salen del
   * sueldo del trabajador, así que se muestran aparte y no se restan.
   */
  get totalAportaciones(): number {
    return this.detalles
      .filter((d) => d.payment_concept?.tipo === 'aportacion')
      .reduce((s, d) => s + Number(d.monto_calculado ?? 0), 0);
  }

  cargar(): void {
    this.cargando = true;
    forkJoin({
      planilla: this.planillaService.getById(this.planillaId),
      detalles: this.detalleService.listarPorPlanilla(this.planillaId),
      conceptos: this.conceptoService.getAll(),
    }).subscribe({
      next: ({ planilla, detalles, conceptos }) => {
        if (planilla.success) this.planilla = planilla.data;
        if (detalles.success) this.detalles = detalles.data;
        if (conceptos.success) this.conceptos = conceptos.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la planilla.'));
      },
    });
  }

  /** Al elegir un concepto con valor fijo, se propone su monto de catálogo. */
  alElegirConcepto(): void {
    const id = this.form.get('payment_concept_id')?.value;
    const concepto = this.conceptos.find((c) => c.id === id);
    if (!concepto || this.detalleEditando) return;

    if (concepto.calculo === 'fijo' && concepto.valor != null) {
      this.form.patchValue({ monto_calculado: Number(concepto.valor) });
      return;
    }
    if (concepto.calculo === 'porcentaje' && concepto.valor != null && this.planilla) {
      const base = Number(this.planilla.sueldo_base ?? 0);
      const monto = +(base * (Number(concepto.valor) / 100)).toFixed(2);
      this.form.patchValue({ monto_calculado: monto });
    }
  }

  nuevo(): void {
    this.detalleEditando = null;
    this.form.reset({ payment_concept_id: '', monto_calculado: null, descripcion: '' });
    this.modalVisible = true;
  }

  editar(detalle: PayrollDetalle): void {
    this.detalleEditando = detalle;
    this.form.patchValue({
      payment_concept_id: detalle.payment_concept_id,
      monto_calculado: Number(detalle.monto_calculado ?? 0),
      descripcion: detalle.descripcion ?? '',
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.detalleEditando = null;
    this.form.reset({ payment_concept_id: '', monto_calculado: null, descripcion: '' });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.guardando = true;

    // El concepto no se cambia al editar: el backend solo acepta monto,
    // descripción y estado. Para cambiarlo hay que quitar la línea y crearla.
    const peticion = this.detalleEditando
      ? this.detalleService.update(this.detalleEditando.id, {
          monto_calculado: Number(v.monto_calculado),
          descripcion: v.descripcion || null,
        })
      : this.detalleService.crear({
          planilla_id: this.planillaId,
          payment_concept_id: v.payment_concept_id!,
          monto_calculado: Number(v.monto_calculado),
          descripcion: v.descripcion || null,
        } as PayrollDetallePayload);

    peticion.subscribe({
      next: () => {
        this.guardando = false;
        this.toastService.success(
          this.detalleEditando ? 'Concepto actualizado' : 'Concepto agregado',
          'El total de la planilla se recalculó.'
        );
        this.cerrarModal();
        this.cargar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el concepto.'));
      },
    });
  }

  eliminar(detalle: PayrollDetalle): void {
    const nombre = detalle.payment_concept?.nombre ?? 'este concepto';
    this.confirmService.confirmarEliminar(
      `"${nombre}" de esta planilla. El total se recalculará`,
      () => {
        this.detalleService.delete(detalle.id).subscribe({
          next: () => {
            this.toastService.success('Concepto quitado', 'El total de la planilla se recalculó.');
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo quitar el concepto.'));
          },
        });
      }
    );
  }

  volver(): void {
    this.router.navigate(['/inicio/planillas']);
  }
}
