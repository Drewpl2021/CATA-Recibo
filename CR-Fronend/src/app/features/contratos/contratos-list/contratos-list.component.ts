import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { forkJoin } from 'rxjs';

import { ContratoService, EmpleadoService, ToastService, ConfirmService } from '../../../core/services';
import { Contrato, ContratoPayload, Empleado } from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import {
  TIPO_CONTRATO_CONTRATO_OPCIONES,
  ESTADO_CONTRATO_OPCIONES,
  MOTIVO_FIN_CONTRATO_OPCIONES,
} from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

const SEVERIDAD_ESTADO: Record<string, 'success' | 'secondary' | 'info'> = {
  vigente: 'success',
  finalizado: 'secondary',
  renovado: 'info',
};

/**
 * Contratos del personal (RR.HH. y Admin).
 *
 * La regla del negocio que manda acá: un empleado tiene UN SOLO contrato
 * vigente. Al registrar uno nuevo, el backend cierra solo el anterior — le
 * pone como fecha de fin el día antes del nuevo inicio y lo marca como
 * finalizado. Por eso "Nuevo contrato" es, en la práctica, renovar.
 *
 * Eliminar es baja lógica: el contrato deja de listarse pero no se pierde,
 * porque es el respaldo del vínculo laboral.
 */
@Component({
  selector: 'app-contratos-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent,
  ],
  templateUrl: './contratos-list.component.html',
})
export class ContratosListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private contratoService = inject(ContratoService);
  private empleadoService = inject(EmpleadoService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  contratos: Contrato[] = [];
  empleados: Empleado[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;
  /** Los conteos que manda el backend junto a la página. */
  vigentes = 0;
  finalizados = 0;

  /** Un contrato no está "activo o inactivo": está corriendo o ya terminó. */
  get cifras(): CifraCabecera[] {
    return [
      { icono: 'layers', valor: this.total, etiqueta: 'Total', tono: 'brand' },
      { icono: 'check_circle', valor: this.vigentes, etiqueta: 'Vigentes', tono: 'success' },
      { icono: 'pause_circle', valor: this.finalizados, etiqueta: 'Terminados', tono: 'muted' },
    ];
  }
  guardando = false;
  modalVisible = false;
  contratoEditando: Contrato | null = null;

  /** Filtros de la barra superior (los resuelve el backend). */
  filtroEmpleado = '';
  filtroEstado = '';

  tipos = TIPO_CONTRATO_CONTRATO_OPCIONES;
  estados = ESTADO_CONTRATO_OPCIONES;
  motivos = MOTIVO_FIN_CONTRATO_OPCIONES;

  columnas: ColumnaTabla<Contrato>[] = [
    {
      campo: 'empleado.nombre',
      header: 'Empleado',
      ancho: '24%',
      formatear: (_v, fila) => this.nombreEmpleado(fila.empleado),
    },
    {
      campo: 'tipo_contrato',
      header: 'Tipo',
      ancho: '15%',
      formatear: (valor) => this.etiqueta(this.tipos, valor),
    },
    { campo: 'fecha_inicio', header: 'Desde', tipo: 'fecha', ancho: '12%' },
    {
      campo: 'fecha_fin',
      header: 'Hasta',
      ancho: '12%',
      tipo: 'fecha',
      formatear: (valor) => (valor ? '' : 'Sin fecha de fin'),
    },
    {
      campo: 'estado',
      header: 'Estado',
      ancho: '12%',
      tipo: 'badge',
      formatear: (valor) => this.etiqueta(this.estados, valor),
      badgeSeveridad: (valor) => SEVERIDAD_ESTADO[valor] ?? 'secondary',
    },
    {
      campo: 'motivo_fin',
      header: 'Motivo de fin',
      formatear: (valor) => (valor ? this.etiqueta(this.motivos, valor) : '—'),
    },
  ];

  form = this.fb.group({
    empleado_id: ['', [Validators.required]],
    tipo_contrato: ['plazo_fijo', [Validators.required]],
    fecha_inicio: ['', [Validators.required]],
    fecha_fin: [''],
    // Solo al editar: en el alta el backend siempre lo crea como vigente.
    estado: ['vigente'],
    motivo_fin: [''],
    observaciones: [''],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarEmpleados();
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  etiqueta(opciones: readonly { label: string; value: unknown }[], valor: unknown): string {
    return opciones.find((o) => o.value === valor)?.label ?? String(valor ?? '');
  }

  nombreEmpleado(empleado?: Empleado | null): string {
    if (!empleado) return '—';
    return `${empleado.nombre ?? ''} ${empleado.apellido ?? ''}`.trim() || '—';
  }

  get rangoInvertido(): boolean {
    const desde = this.form.get('fecha_inicio')?.value;
    const hasta = this.form.get('fecha_fin')?.value;
    return !!desde && !!hasta && hasta < desde;
  }

  /** El motivo de fin solo tiene sentido si el contrato ya terminó. */
  get pideMotivo(): boolean {
    return this.form.get('estado')?.value === 'finalizado';
  }

  /** Al dar de alta, avisa de qué contrato se va a cerrar automáticamente. */
  get contratoQueSeCerrara(): Contrato | null {
    if (this.contratoEditando) return null;
    const empleadoId = this.form.get('empleado_id')?.value;
    if (!empleadoId) return null;
    return this.contratos.find((c) => c.empleado_id === empleadoId && c.estado === 'vigente') ?? null;
  }

  /** El usuario pidió otra página. */
  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  /** Búsqueda contra el backend; llega ya con el retardo aplicado. */
  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  /**
   * La lista de empleados llena el desplegable del filtro y el del
   * formulario: se pide UNA vez y completa, porque a un <select> no se le
   * pagina. Los contratos sí vienen por páginas.
   */
  private cargarEmpleados(): void {
    this.empleadoService.paraSelector().subscribe({
      next: (res) => {
        if (res.success) this.empleados = res.data;
      },
      error: () => {
        this.toastService.error('Aviso', 'No se pudo cargar la lista de empleados.');
      },
    });
  }

  cargar(): void {
    this.cargando = true;
    this.contratoService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        empleado_id: this.filtroEmpleado || undefined,
        estado: this.filtroEstado || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.contratos = res.data.content;
            this.total = res.data.totalElements;
            this.vigentes = res.data.vigentes ?? 0;
            this.finalizados = res.data.finalizados ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los contratos.'));
          this.cargando = false;
        },
      });
  }

  /** Los filtros los aplica el backend, así que se recarga. */
  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  nuevo(): void {
    this.contratoEditando = null;
    this.form.reset({
      tipo_contrato: 'plazo_fijo',
      estado: 'vigente',
      motivo_fin: '',
      empleado_id: '',
    });
    this.modalVisible = true;
  }

  editar(contrato: Contrato): void {
    this.contratoEditando = contrato;
    this.form.patchValue({
      empleado_id: contrato.empleado_id,
      tipo_contrato: contrato.tipo_contrato,
      fecha_inicio: (contrato.fecha_inicio ?? '').slice(0, 10),
      fecha_fin: (contrato.fecha_fin ?? '').slice(0, 10),
      estado: contrato.estado,
      motivo_fin: contrato.motivo_fin ?? '',
      observaciones: contrato.observaciones ?? '',
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.contratoEditando = null;
    this.form.reset({ tipo_contrato: 'plazo_fijo', estado: 'vigente', motivo_fin: '', empleado_id: '' });
  }

  guardar(): void {
    if (this.form.invalid || this.rangoInvertido) {
      this.form.markAllAsTouched();
      return;
    }

    const crudo = this.form.getRawValue();
    // El backend valida `nullable|date` y `nullable|in:...`: una cadena vacía
    // no pasa esas reglas, así que los opcionales vacíos se mandan como null.
    const base: ContratoPayload = {
      empleado_id: crudo.empleado_id!,
      tipo_contrato: crudo.tipo_contrato!,
      fecha_inicio: crudo.fecha_inicio!,
      fecha_fin: crudo.fecha_fin || null,
      observaciones: crudo.observaciones || null,
    };

    this.guardando = true;

    const peticion = this.contratoEditando
      ? this.contratoService.update(this.contratoEditando.id, {
          ...base,
          estado: crudo.estado!,
          // El motivo solo viaja si el contrato quedó finalizado.
          motivo_fin: crudo.estado === 'finalizado' ? crudo.motivo_fin || null : null,
        })
      : this.contratoService.create(base);

    const cerrado = this.contratoQueSeCerrara;

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.contratoEditando ? 'Contrato actualizado' : 'Contrato registrado',
            cerrado
              ? `Se cerró el contrato anterior de ${this.nombreEmpleado(cerrado.empleado)} y el nuevo queda vigente.`
              : 'El contrato se guardó correctamente.'
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el contrato.'));
      },
    });
  }

  eliminar(contrato: Contrato): void {
    this.confirmService.confirmarEliminar(
      `el contrato de ${this.nombreEmpleado(contrato.empleado)}. Dejará de listarse, pero queda guardado como respaldo`,
      () => {
        this.contratoService.delete(contrato.id).subscribe({
          next: () => {
            this.toastService.success('Eliminado', 'El contrato fue dado de baja.');
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el contrato.'));
          },
        });
      }
    );
  }
}
