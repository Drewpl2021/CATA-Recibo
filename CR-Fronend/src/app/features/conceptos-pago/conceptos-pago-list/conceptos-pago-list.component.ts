import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';

import {
  PaymentConceptService,
  EmpleadoService,
  AreaService,
  CargoService,
  SedeService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import {
  AplicacionConceptoGrupo,
  Area,
  Cargo,
  Empleado,
  PaymentConcept,
  PaymentConceptPayload,
  Sede,
  TipoConcepto,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import {
  TIPO_CONCEPTO_OPCIONES,
  TIPO_CONCEPTO_CORTO,
  TIPO_CONCEPTO_PLURAL,
  TIPO_CONCEPTO_ICONO,
  TIPO_CALCULO_OPCIONES,
  MESES_OPCIONES,
  nombreMes,
} from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import {
  SelectorEmpleadosComponent,
  AlcanceGrupo,
} from '../../../shared/components/selector-empleados/selector-empleados.component';
import { forkJoin } from 'rxjs';

/** Cómo se pinta cada tipo en la tabla. */
const SEVERIDAD_POR_TIPO: Record<string, 'success' | 'danger' | 'info' | 'warning'> = {
  bonificacion: 'success',
  descuento: 'danger',
  aportacion: 'info',
  adelanto: 'warning',
};

/**
 * Catálogo de conceptos de pago (RR.HH. y Admin).
 *
 * Un concepto es una línea que puede aparecer en la boleta: una
 * bonificación, un descuento, una aportación del empleador o un adelanto.
 *
 * La casilla "aplicar a todos" es la parte delicada: si se marca, el
 * concepto entra automáticamente en la planilla de cada empleado al
 * generarla, y por eso el backend exige que tenga cálculo y valor. Sin
 * marcar, el concepto queda en el catálogo y se aplica a mano cuando toca.
 *
 * Los conceptos de cálculo especial (pensión, EsSalud, Renta 5ta) los
 * calcula el backend por empleado y no se manejan desde acá.
 */
@Component({
  selector: 'app-conceptos-pago-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorEmpleadosComponent,
    IconComponent,
  ],
  templateUrl: './conceptos-pago-list.component.html',
})
export class ConceptosPagoListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private conceptoService = inject(PaymentConceptService);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  /** Filas por página. La tabla y el backend tienen que coincidir. */
  readonly TAMANO_PAGINA = 10;

  conceptos: PaymentConcept[] = [];
  cargando = false;

  /**
   * Filtro, página y búsqueda NO se resuelven en el navegador: se mandan
   * como ?tipo=&page=&size=&search= y el backend devuelve solo la página
   * pedida más el total. Traerse el catálogo entero para cortarlo acá
   * funciona con 20 conceptos y deja de funcionar cuando son cientos.
   */
  filtroTipo: TipoConcepto | '' = '';
  pagina = 0;
  busqueda = '';
  /** Cuántos hay con el filtro puesto — lo cuenta el backend. */
  totalConceptos = 0;
  /** Cuántos hay en total, sin filtrar. Para el pie de la tarjeta. */
  totalCatalogo = 0;

  /**
   * Con un tipo elegido la cifra grande es la de ese tipo, y al lado se
   * recuerda cuántos hay en todo el catálogo.
   */
  get cifras(): CifraCabecera[] {
    const cifras: CifraCabecera[] = [
      {
        icono: this.filtroTipo ? TIPO_CONCEPTO_ICONO[this.filtroTipo] : 'layers',
        valor: this.totalConceptos,
        etiqueta: this.filtroTipo ? TIPO_CONCEPTO_CORTO[this.filtroTipo] : 'Conceptos',
        tono: 'brand',
      },
    ];
    if (this.filtroTipo) {
      cifras.push({ icono: 'money', valor: this.totalCatalogo, etiqueta: 'En total', tono: 'muted' });
    }
    return cifras;
  }

  /** Los tipos con su ícono, para los chips de filtro. */
  tiposChip = TIPO_CONCEPTO_OPCIONES.map((t) => ({
    value: t.value as TipoConcepto,
    label: TIPO_CONCEPTO_CORTO[t.value],
    icono: TIPO_CONCEPTO_ICONO[t.value],
  }));
  guardando = false;
  modalVisible = false;
  conceptoEditando: PaymentConcept | null = null;

  tipos = TIPO_CONCEPTO_OPCIONES;
  calculos = TIPO_CALCULO_OPCIONES;
  meses = MESES_OPCIONES;
  nombreMes = nombreMes;

  // ── Aplicar a un grupo ──
  empleados: Empleado[] = [];
  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];
  modalGrupoVisible = false;
  aplicando = false;
  conceptoAAplicar: PaymentConcept | null = null;
  resultadoGrupo: AplicacionConceptoGrupo | null = null;

  /**
   * Acá el alcance arranca en 'elegidos': aplicar un concepto a TODO el
   * personal de golpe casi nunca es lo que se busca (para eso está la
   * casilla "aplica a todos" del propio concepto).
   */
  alcance: AlcanceGrupo = 'elegidos';
  empleadosElegidos: string[] = [];

  formGrupo = this.fb.group({
    mes: [new Date().getMonth() + 1, [Validators.required]],
    anio: [new Date().getFullYear(), [Validators.required, Validators.min(2000)]],
  });

  columnas: ColumnaTabla<PaymentConcept>[] = [
    { campo: 'nombre', header: 'Concepto', ancho: '28%' },
    {
      campo: 'tipo',
      header: 'Tipo',
      ancho: '16%',
      tipo: 'badge',
      formatear: (valor) => TIPO_CONCEPTO_CORTO[valor] ?? String(valor ?? ''),
      badgeSeveridad: (valor) => SEVERIDAD_POR_TIPO[valor] ?? 'secondary',
    },
    {
      campo: 'valor',
      header: 'Valor',
      ancho: '14%',
      formatear: (_valor, fila) => this.valorLegible(fila),
    },
    {
      campo: 'aplica_a_todos',
      header: 'Automático',
      ancho: '14%',
      tipo: 'badge',
      formatear: (valor) => (valor ? 'A todos' : 'Manual'),
      badgeSeveridad: (valor) => (valor ? 'info' : 'secondary'),
    },
    { campo: 'descripcion', header: 'Descripción' },
  ];

  /**
   * Aplicar a un grupo solo tiene sentido en los conceptos que llevan un
   * monto único en el catálogo. Sin calculo/valor el backend lo rechaza,
   * así que el botón ni se muestra.
   */
  accionesExtra: AccionPersonalizada<PaymentConcept>[] = [
    {
      id: 'grupo',
      titulo: 'Aplicar a un grupo de empleados',
      icono: 'people',
      visible: (c) => !!c.calculo && c.valor != null,
    },
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(150)]],
    tipo: ['bonificacion' as TipoConcepto, [Validators.required]],
    calculo: [''],
    valor: [null as number | null, [Validators.min(0)]],
    descripcion: ['', [Validators.maxLength(255)]],
    aplica_a_todos: [false],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarEmpleados();
    // El backend exige cálculo y valor solo cuando el concepto es automático.
    this.form.get('aplica_a_todos')!.valueChanges.subscribe(() => this.ajustarObligatorios());
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  get esAutomatico(): boolean {
    return !!this.form.get('aplica_a_todos')?.value;
  }

  /** "S/ 113.00" o "13%" según el cálculo; "—" si el concepto no lleva monto. */
  valorLegible(concepto: PaymentConcept): string {
    if (concepto.valor === null || concepto.valor === undefined) return '—';
    const n = Number(concepto.valor);
    return concepto.calculo === 'porcentaje' ? `${n}%` : `S/ ${n.toFixed(2)}`;
  }

  private ajustarObligatorios(): void {
    const calculo = this.form.get('calculo')!;
    const valor = this.form.get('valor')!;

    if (this.esAutomatico) {
      calculo.setValidators([Validators.required]);
      valor.setValidators([Validators.required, Validators.min(0)]);
    } else {
      calculo.clearValidators();
      valor.setValidators([Validators.min(0)]);
    }
    calculo.updateValueAndValidity();
    valor.updateValueAndValidity();
  }

  /** Lo que se está viendo ahora mismo, en cristiano. */
  get etiquetaFiltro(): string {
    return this.filtroTipo ? `Solo ${TIPO_CONCEPTO_PLURAL[this.filtroTipo]}` : 'Todo el catálogo';
  }

  get mensajeVacio(): string {
    if (this.busqueda) return `No hay ningún concepto que diga "${this.busqueda}".`;
    if (this.filtroTipo) return 'No hay conceptos de este tipo. Prueba con otro o toca "Todos".';
    return 'Todavía no hay conceptos en el catálogo.';
  }

  filtrarPorTipo(tipo: TipoConcepto | ''): void {
    this.filtroTipo = tipo;
    // Al cambiar de filtro se vuelve a la primera página: quedarse en la
    // cuatro cuando el nuevo filtro solo tiene dos deja la tabla vacía.
    this.pagina = 0;
    this.cargar();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.conceptoService
      .listar({
        tipo: this.filtroTipo || undefined,
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
      })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.conceptos = res.data.content;
          this.totalConceptos = res.data.totalElements;
          // El total sin filtrar solo cambia al crear o borrar, así que se
          // guarda la primera vez y cuando se está viendo el catálogo entero.
          if (!this.filtroTipo && !this.busqueda) this.totalCatalogo = res.data.totalElements;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los conceptos.'));
        this.cargando = false;
      },
    });
  }

  nuevo(): void {
    this.conceptoEditando = null;
    this.form.reset({ tipo: 'bonificacion', calculo: '', valor: null, aplica_a_todos: false });
    this.ajustarObligatorios();
    this.modalVisible = true;
  }

  editar(concepto: PaymentConcept): void {
    this.conceptoEditando = concepto;
    this.form.patchValue({
      nombre: concepto.nombre,
      tipo: concepto.tipo,
      calculo: concepto.calculo ?? '',
      valor: concepto.valor ?? null,
      descripcion: concepto.descripcion ?? '',
      aplica_a_todos: !!concepto.aplica_a_todos,
    });
    this.ajustarObligatorios();
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.conceptoEditando = null;
    this.form.reset({ tipo: 'bonificacion', calculo: '', valor: null, aplica_a_todos: false });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const crudo = this.form.getRawValue();
    const payload: PaymentConceptPayload = {
      nombre: crudo.nombre!,
      tipo: crudo.tipo as TipoConcepto,
      // El backend valida `in:fijo,porcentaje`: una cadena vacía no pasa,
      // así que se manda null cuando no hay cálculo elegido.
      calculo: crudo.calculo ? (crudo.calculo as 'fijo' | 'porcentaje') : null,
      valor: crudo.valor === null || crudo.valor === undefined ? null : Number(crudo.valor),
      descripcion: crudo.descripcion || null,
      aplica_a_todos: !!crudo.aplica_a_todos,
    };

    this.guardando = true;

    const peticion = this.conceptoEditando
      ? this.conceptoService.update(this.conceptoEditando.id, payload)
      : this.conceptoService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.conceptoEditando ? 'Concepto actualizado' : 'Concepto creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el concepto.'));
      },
    });
  }

  eliminar(concepto: PaymentConcept): void {
    this.confirmService.confirmarEliminar(`el concepto "${concepto.nombre}"`, () => {
      this.conceptoService.delete(concepto.id).subscribe({
        next: () => {
          this.toastService.success('Eliminado', `El concepto "${concepto.nombre}" fue eliminado.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el concepto.'));
        },
      });
    });
  }

  // ────────── Aplicar un concepto a un grupo ──────────

  private cargarEmpleados(): void {
    forkJoin({
      empleados: this.empleadoService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      sedes: this.sedeService.getAll(),
    }).subscribe({
      next: ({ empleados, areas, cargos, sedes }) => {
        // Solo el personal activo: a un inactivo no se le arma planilla.
        if (empleados.success) this.empleados = empleados.data.filter((e) => e.estado !== 'inactivo');
        if (areas.success) this.areas = areas.data;
        if (cargos.success) this.cargos = cargos.data;
        if (sedes.success) this.sedes = sedes.data;
      },
      error: () => {
        // No es crítico: la pantalla sigue sirviendo para el CRUD del catálogo.
        this.toastService.error('Aviso', 'No se pudo cargar la lista de empleados.');
      },
    });
  }

  alAccionar(evento: { accion: string; fila: PaymentConcept }): void {
    if (evento.accion === 'grupo') this.abrirGrupo(evento.fila);
  }

  abrirGrupo(concepto: PaymentConcept): void {
    this.conceptoAAplicar = concepto;
    this.empleadosElegidos = [];
    this.alcance = 'elegidos';
    this.resultadoGrupo = null;
    this.formGrupo.reset({
      mes: new Date().getMonth() + 1,
      anio: new Date().getFullYear(),
    });
    this.modalGrupoVisible = true;
  }

  cerrarGrupo(): void {
    this.modalGrupoVisible = false;
    this.conceptoAAplicar = null;
    this.empleadosElegidos = [];
    this.resultadoGrupo = null;
  }

  /** A quiénes se les aplicará, según cómo esté el selector ahora. */
  private get destinatarios(): string[] {
    return this.alcance === 'todos' ? this.empleados.map((e) => e.id) : this.empleadosElegidos;
  }

  get cuantosAlcanzados(): number {
    return this.destinatarios.length;
  }

  /** El monto que se aplicará, tal como está en el catálogo. */
  get montoDelGrupo(): string {
    const c = this.conceptoAAplicar;
    if (!c || c.valor == null) return '—';
    return c.calculo === 'porcentaje' ? `${c.valor}% del sueldo` : `S/ ${Number(c.valor).toFixed(2)}`;
  }

  aplicarAGrupo(): void {
    if (!this.conceptoAAplicar) return;

    if (!this.destinatarios.length) {
      this.toastService.error('Falta elegir', 'Marca al menos un empleado.');
      return;
    }
    if (this.formGrupo.invalid) {
      this.formGrupo.markAllAsTouched();
      return;
    }

    const { mes, anio } = this.formGrupo.getRawValue();
    this.aplicando = true;
    this.resultadoGrupo = null;

    this.conceptoService
      .aplicarAGrupo(this.conceptoAAplicar.id, {
        mes: Number(mes),
        anio: Number(anio),
        empleado_ids: this.destinatarios,
      })
      .subscribe({
        next: (res) => {
          this.aplicando = false;
          if (!res.success) return;
          this.resultadoGrupo = res.data;
          const { aplicadas, omitidas } = res.data.resumen;
          this.toastService.resultadoMasivo({
            hechas: aplicadas,
            omitidas,
            exito: 'Concepto aplicado',
            nada: 'No se aplicó a ninguna planilla',
            cosas: 'planilla(s)',
            motivo: 'esos empleados no tienen planilla de ese mes',
          });
        },
        error: (err) => {
          this.aplicando = false;
          // 422 cuando el concepto es de cálculo especial o no tiene monto.
          this.toastService.error('No se aplicó', mensajeErrorApi(err, 'No se pudo aplicar el concepto.'));
        },
      });
  }
}
