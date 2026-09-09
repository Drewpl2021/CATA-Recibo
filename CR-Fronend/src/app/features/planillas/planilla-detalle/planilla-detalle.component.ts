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

  /** El corte lo hace el backend, igual que en el resto de listados. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  totalLineas = 0;

  /*
   * Los tres totales del pie los manda el backend calculados sobre TODAS las
   * líneas de la planilla. Antes se sumaban acá recorriendo el arreglo, que
   * funcionaba solo porque llegaban todas: con la lista paginada, sumar lo
   * que hay en pantalla daría un neto que no es el que se paga.
   */
  totalSuma = 0;
  totalResta = 0;
  totalAportaciones = 0;

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
    calculo: ['fijo' as 'fijo' | 'porcentaje', [Validators.required]],
    valor: [null as number | null, [Validators.required, Validators.min(0)]],
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

  /**
   * "− S/ 150.00 (5%)" o "+ S/ 250.00", según lo que haga el concepto.
   *
   * El paréntesis solo sale cuando la línea se escribió como porcentaje: un
   * "S/ 150" suelto no dice de dónde salió, y a los tres meses nadie se
   * acuerda.
   */
  montoConSigno(detalle: PayrollDetalle): string {
    const monto = Number(detalle.monto_calculado ?? 0);
    const signo = this.resta(detalle) ? '−' : '+';
    const regla = detalle.calculo === 'porcentaje' && detalle.como_se_calculo
      ? ` (${detalle.como_se_calculo})`
      : '';

    return `${signo} S/ ${monto.toFixed(2)}${regla}`;
  }

  /** El básico sobre el que se aplican los porcentajes de esta planilla. */
  get baseDelPorcentaje(): number {
    return Number(this.planilla?.sueldo_base ?? 0);
  }

  get esPorcentaje(): boolean {
    return this.form.get('calculo')?.value === 'porcentaje';
  }

  /**
   * Los soles que va a acabar guardando el backend, para enseñarlos mientras
   * se escribe: nadie debería tener que sacar el 5% de memoria.
   *
   * La cuenta de verdad la hace el servidor; esto es solo el anticipo.
   */
  get montoPrevisto(): number {
    const valor = Number(this.form.get('valor')?.value ?? 0);
    if (!valor) return 0;

    return this.esPorcentaje
      ? +(this.baseDelPorcentaje * (valor / 100)).toFixed(2)
      : +valor.toFixed(2);
  }

  /** El usuario pidió otra página de conceptos. */
  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    forkJoin({
      planilla: this.planillaService.getById(this.planillaId),
      detalles: this.detalleService.paginaDePlanilla(this.planillaId, this.pagina, this.TAMANO_PAGINA),
      // El catálogo va entero a propósito: es el desplegable del formulario,
      // y a un <select> no se le pagina.
      conceptos: this.conceptoService.getAll(),
    }).subscribe({
      next: ({ planilla, detalles, conceptos }) => {
        if (planilla.success) this.planilla = planilla.data;
        if (detalles.success) {
          this.detalles = detalles.data.content;
          this.totalLineas = detalles.data.totalElements;
          this.totalSuma = Number(detalles.data.sumanAlSueldo ?? 0);
          this.totalResta = Number(detalles.data.restanDelSueldo ?? 0);
          this.totalAportaciones = Number(detalles.data.aportaciones ?? 0);
        }
        if (conceptos.success) this.conceptos = conceptos.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la planilla.'));
      },
    });
  }

  /**
   * Al elegir un concepto se copia su regla del catálogo — el 5% o los S/ 100
   * que tenga puestos.
   *
   * Es una copia, no un vínculo: cambiarla aquí afecta solo a esta planilla.
   * Antes, para ponerle otro porcentaje a una persona había que irse a
   * Conceptos de Pago y cambiar el valor del catálogo, que se lo cambiaba a
   * todo el colegio.
   */
  alElegirConcepto(): void {
    const id = this.form.get('payment_concept_id')?.value;
    const concepto = this.conceptos.find((c) => c.id === id);
    if (!concepto || this.detalleEditando) return;

    this.form.patchValue({
      calculo: concepto.calculo === 'porcentaje' ? 'porcentaje' : 'fijo',
      valor: concepto.valor != null ? Number(concepto.valor) : null,
    });
  }

  nuevo(): void {
    this.detalleEditando = null;
    this.form.reset({ payment_concept_id: '', calculo: 'fijo', valor: null, descripcion: '' });
    this.modalVisible = true;
  }

  editar(detalle: PayrollDetalle): void {
    this.detalleEditando = detalle;
    this.form.patchValue({
      payment_concept_id: detalle.payment_concept_id,
      // Si la línea se escribió como regla, se reabre con ella; si se puso en
      // soles a secas, se reabre como monto fijo por esa cantidad.
      calculo: detalle.calculo ?? 'fijo',
      valor: Number(detalle.valor ?? detalle.monto_calculado ?? 0),
      descripcion: detalle.descripcion ?? '',
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.detalleEditando = null;
    this.form.reset({ payment_concept_id: '', calculo: 'fijo', valor: null, descripcion: '' });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.guardando = true;

    // Se manda la regla, no los soles: la cuenta la hace el backend sobre el
    // básico de ESTA planilla, y así el porcentaje guardado y el monto cobrado
    // no pueden decir cosas distintas.
    const regla = { calculo: v.calculo!, valor: Number(v.valor) };

    // El concepto no se cambia al editar: para eso se quita la línea y se
    // vuelve a agregar, que es lo que dice la pantalla.
    const peticion = this.detalleEditando
      ? this.detalleService.update(this.detalleEditando.id, {
          ...regla,
          descripcion: v.descripcion || null,
        })
      : this.detalleService.crear({
          planilla_id: this.planillaId,
          payment_concept_id: v.payment_concept_id!,
          ...regla,
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
