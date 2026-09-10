import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';

import {
  PlanillaService,
  EmpleadoService,
  PeriodoService,
  AreaService,
  CargoService,
  SedeService,
  BoletaService,
  ToastService,
  ConfirmService,
  PlanillaCorridaService,
  PaymentConceptService,
} from '../../../core/services';
import {
  Area,
  Cargo,
  Empleado,
  GeneracionMasivaPlanilla,
  Periodo,
  Planilla,
  Sede,
  PlanillaCorrida,
  PaymentConcept,
  AplicacionConceptoGrupo,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { MESES_OPCIONES, nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import {
  SelectorEmpleadosComponent,
  AlcanceGrupo,
} from '../../../shared/components/selector-empleados/selector-empleados.component';

/**
 * Planillas del mes (RR.HH. y Admin).
 *
 * Una planilla es el cálculo del sueldo de UN empleado en UN mes. Se crean
 * de dos maneras, y la pantalla da entrada a ambas:
 *
 *   · De una en una, con "Nueva planilla".
 *   · Todas juntas, desde Periodos → "Generar planillas".
 *
 * Y una vez calculadas, desde acá se emiten las BOLETAS en PDF de todo el
 * mes con "Emitir boletas".
 *
 * El sueldo base nunca se edita: sale del empleado. Lo que sí se ajusta son
 * sus conceptos, en la pantalla de detalle.
 */
@Component({
  selector: 'app-planillas-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorEmpleadosComponent,
  ],
  templateUrl: './planillas-list.component.html',
})
export class PlanillasListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private ruta = inject(ActivatedRoute);
  private corridaService = inject(PlanillaCorridaService);
  private conceptoService = inject(PaymentConceptService);
  private planillaService = inject(PlanillaService);
  private empleadoService = inject(EmpleadoService);
  private periodoService = inject(PeriodoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private boletaService = inject(BoletaService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  planillas: Planilla[] = [];
  empleados: Empleado[] = [];
  periodos: Periodo[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 15;
  pagina = 0;
  busqueda = '';
  /** Cuántas hay en total, según el backend — no el largo de la página. */
  total = 0;

  // ── Filtros (los resuelve el backend) ──
  filtroMes: number | '' = new Date().getMonth() + 1;
  filtroAnio: number | '' = new Date().getFullYear();
  filtroEmpleado = '';
  filtroPeriodo = '';

  /*
   * Dentro de qué planilla estamos.
   *
   *   corrida        -> las filas de "Planilla TIC"
   *   modoSinAgrupar -> las que no están en ninguna
   *
   * La pantalla es la misma en los dos casos porque la tabla es la misma:
   * cambia de dónde salen las filas y qué se puede hacer con ellas.
   */
  corridaId = '';
  corrida: PlanillaCorrida | null = null;
  modoSinAgrupar = false;

  // ── Generación masiva de planillas ──
  modalGenerarVisible = false;
  generando = false;
  resultadoGeneracion: GeneracionMasivaPlanilla | null = null;

  /** A quiénes se les arma la planilla: a todos, o a un grupo elegido. */
  alcance: AlcanceGrupo = 'todos';
  empleadosElegidos: string[] = [];

  // Catálogos para acotar el grupo
  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];

  /**
   * Mes y año dejaron de ser campos sueltos: ahora se elige un mes DE LOS
   * QUE EL PERIODO CUBRE, con el formato "2026-08". Antes se podía dejar
   * Septiembre con un periodo de Agosto, marcar a todo un grupo, darle a
   * generar y recién ahí el backend contestaba que el mes no caía en el
   * rango — con el trabajo de elegir a la gente ya hecho.
   */
  formGenerar = this.fb.group({
    periodo_id: ['', [Validators.required]],
    mesAnio: ['', [Validators.required]],
  });

  /** Los meses que cubre el periodo elegido; se recalculan al cambiarlo. */
  mesesDelPeriodo: { valor: string; etiqueta: string }[] = [];

  // ── Emisión masiva de boletas ──
  modalBoletasVisible = false;
  emitiendo = false;
  resultadoBoletas: { generadas: number; omitidas: number } | null = null;

  meses = MESES_OPCIONES;
  nombreMes = nombreMes;

  columnas: ColumnaTabla<Planilla>[] = [
    {
      campo: 'empleado.nombre',
      header: 'Empleado',
      ancho: '24%',
      formatear: (_v, fila) => this.nombreEmpleado(fila),
    },
    {
      campo: 'mes',
      header: 'Periodo',
      ancho: '14%',
      formatear: (_v, fila) => `${nombreMes(fila.mes)} ${fila.anio}`,
    },
    { campo: 'sueldo_base', header: 'Sueldo base', ancho: '14%', tipo: 'moneda' },
    { campo: 'bonificaciones', header: 'Bonificaciones', ancho: '14%', tipo: 'moneda' },
    { campo: 'descuentos', header: 'Descuentos', ancho: '14%', tipo: 'moneda' },
    { campo: 'total', header: 'Neto a pagar', ancho: '14%', tipo: 'moneda' },
  ];

  /** Los conceptos de la planilla se gestionan en su propia pantalla. */
  accionesExtra: AccionPersonalizada<Planilla>[] = [
    { id: 'detalle', titulo: 'Ver y ajustar sus conceptos', icono: 'receipt_long' },
    {
      id: 'sacar', titulo: 'Sacar de esta planilla (no borra su pago)', icono: 'remove_circle',
      severidad: 'warning',
      // Solo dentro de una planilla abierta: en "Sin agrupar" ya está fuera,
      // y de una cerrada no se saca a nadie.
      visible: () => !!this.corridaId && !this.estaCerrada,
    },
    {
      id: 'mover', titulo: 'Meter en una planilla', icono: 'folder',
      visible: () => this.modoSinAgrupar,
    },
  ];

    // ── Aplicar un concepto a TODA la planilla ──
  //
  // Es lo que evita tener que irse a Conceptos de Pago y volver a
  // re-seleccionar por área a la misma gente que ya está junta acá dentro
  // —y que, si moviste a alguien a mano, ya ni siquiera coincide—.
  modalConceptoVisible = false;
  aplicandoConcepto = false;
  conceptos: PaymentConcept[] = [];
  conceptoElegido = '';
  resultadoConcepto: AplicacionConceptoGrupo | null = null;

  /*
   * Con qué regla se aplica. Se propone la del catálogo al elegir el
   * concepto y se puede cambiar acá: un préstamo o un adelanto no tienen un
   * valor "de catálogo", y antes esos no se podían aplicar a un grupo.
   */
  conceptoCalculo: 'fijo' | 'porcentaje' = 'fijo';
  conceptoValor: number | null = null;

  /** A quiénes: toda la planilla, o los que se busquen y marquen. */
  alcanceConcepto: AlcanceGrupo = 'todos';
  elegidosConcepto: string[] = [];


  // ── Meter una planilla suelta en una corrida ──
  modalMoverVisible = false;
  moviendo = false;
  planillaAMover: Planilla | null = null;
  corridasDelMes: PlanillaCorrida[] = [];
  corridaDestino = '';

  ngOnInit(): void {
    this.corridaId = this.ruta.snapshot.paramMap.get('id') ?? '';
    this.modoSinAgrupar = this.ruta.snapshot.url.some((t) => t.path === 'sin-agrupar');

    if (this.modoSinAgrupar) {
      // El mes viene de la pantalla anterior para no perder de vista dónde
      // estabas: si allá mirabas septiembre, acá también.
      const q = this.ruta.snapshot.queryParamMap;
      this.filtroMes = q.get('mes') ? Number(q.get('mes')) : '';
      this.filtroAnio = q.get('anio') ? Number(q.get('anio')) : '';
      this.cargar();
    } else if (this.corridaId) {
      this.cargarCorrida();
    } else {
      this.cargar();
    }

    this.cargarCatalogos();
  }

  /**
   * Los datos de la planilla en la que estamos: su nombre para la cabecera y
   * su mes, que es el que fija la lista. Se cargan ANTES que las filas para
   * que la tabla salga ya acotada al mes correcto.
   */
  private cargarCorrida(): void {
    this.cargando = true;
    this.corridaService.getById(this.corridaId).subscribe({
      next: (res) => {
        if (res.success) {
          this.corrida = res.data;
          this.filtroMes = res.data.mes;
          this.filtroAnio = res.data.anio;
        }
        this.cargar();
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar esta planilla.'));
        this.volver();
      },
    });
  }

  /** De vuelta a la lista de planillas. */
  volver(): void {
    this.router.navigate(['/inicio/planillas']);
  }

  /** El título de la pantalla, según dónde estemos. */
  get tituloPantalla(): string {
    if (this.modoSinAgrupar) return 'Sin agrupar';
    return this.corrida?.nombre ?? 'Planillas';
  }

  get subtituloPantalla(): string {
    if (this.modoSinAgrupar) {
      return 'Trabajadores con planilla del mes que todavía no están en ninguna planilla con nombre.';
    }
    if (this.corrida) {
      return `Los trabajadores de esta planilla, ${nombreMes(this.corrida.mes)} ${this.corrida.anio}.`;
    }
    return 'Cálculo del sueldo de cada empleado, mes a mes.';
  }

  /** Lo que dice la tabla cuando no hay ni una fila. */
  get mensajeSinFilas(): string {
    if (this.modoSinAgrupar) {
      return 'Ninguna planilla quedó suelta: todas están dentro de una planilla con nombre.';
    }
    if (this.corridaId) {
      return 'Esta planilla todavía no tiene trabajadores. Agrégalos con el botón de arriba.';
    }
    return 'No hay planillas para este filtro.';
  }

  get tituloModalConcepto(): string {
    return `Aplicar un concepto a "${this.tituloPantalla}"`;
  }

  get tituloModalAgregar(): string {
    return `Agregar trabajadores a "${this.tituloPantalla}"`;
  }

  /** Una planilla cerrada se mira, no se toca. */
  get estaCerrada(): boolean {
    return this.corrida?.estado === 'cerrada';
  }

  /**
   * Todo lo que llena un desplegable se pide UNA vez y completo, porque a un
   * <select> no se le pagina: empleados y periodos para los filtros de
   * arriba, y áreas/cargos/sedes para acotar el grupo al generar. Las
   * planillas, en cambio, vienen por páginas.
   */
  private cargarCatalogos(): void {
    forkJoin({
      empleados: this.empleadoService.paraSelector(),
      periodos: this.periodoService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      sedes: this.sedeService.getAll(),
    }).subscribe({
      next: ({ empleados, periodos, areas, cargos, sedes }) => {
        if (empleados.success) this.empleados = empleados.data;
        if (periodos.success) this.periodos = periodos.data;
        if (areas.success) this.areas = areas.data;
        if (cargos.success) this.cargos = cargos.data;
        if (sedes.success) this.sedes = sedes.data;
      },
      error: () => {
        this.toastService.error('Aviso', 'No se pudieron cargar los datos de los filtros.');
      },
    });
  }

  /**
   * Los trabajadores QUE ESTÁN en esta planilla, para el buscador del
   * concepto: ofrecer a los 150 del colegio cuando la planilla tiene doce
   * sería ofrecer gente a la que no se le puede aplicar nada.
   */
  get empleadosDeLaPlanilla(): Empleado[] {
    const dentro = new Set(this.planillas.map((p) => p.empleado_id));
    return this.empleados.filter((e) => dentro.has(e.id));
  }

  /** Solo el personal activo entra en una generación de planillas. */
  get empleadosActivos(): Empleado[] {
    return this.empleados.filter((e) => e.estado !== 'inactivo');
  }

  nombreEmpleado(p: Planilla): string {
    const e = p.empleado;
    if (e) return `${e.nombre ?? ''} ${e.apellido ?? ''}`.trim();
    const enLista = this.empleados.find((x) => x.id === p.empleado_id);
    return enLista ? `${enLista.nombre} ${enLista.apellido}`.trim() : '—';
  }

  /** Suma de los netos de lo que se está viendo ahora mismo. */
  /**
   * Neto sumado de TODAS las planillas del filtro, no solo de la página que
   * se está viendo. Por eso lo calcula el backend y no un reduce de acá.
   */
  masaSalarial = 0;

  /** ¿Hay algún filtro puesto? Con todo vacío no hay nada que limpiar. */
  get hayFiltros(): boolean {
    return !!(this.filtroMes || this.filtroAnio || this.filtroEmpleado || this.filtroPeriodo);
  }

  /** Lo que se está viendo, en palabras: "Mayo 2026", "Todo 2026", etc. */
  get etiquetaPeriodo(): string {
    const partes: string[] = [];

    if (this.filtroMes && this.filtroAnio) {
      partes.push(`${nombreMes(Number(this.filtroMes))} ${this.filtroAnio}`);
    } else if (this.filtroMes) {
      partes.push(`${nombreMes(Number(this.filtroMes))} de todos los años`);
    } else if (this.filtroAnio) {
      partes.push(`Todo ${this.filtroAnio}`);
    } else {
      partes.push('Todos los periodos');
    }

    if (this.filtroEmpleado) {
      const e = this.empleados.find((x) => x.id === this.filtroEmpleado);
      if (e) partes.push(`${e.nombre} ${e.apellido}`.trim());
    }

    if (this.filtroPeriodo) {
      const p = this.periodos.find((x) => x.id === this.filtroPeriodo);
      if (p) partes.push(p.nombre);
    }

    return partes.join(' · ');
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

  cargar(): void {
    this.cargando = true;
    this.planillaService
      .listarPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        mes: this.filtroMes || undefined,
        anio: this.filtroAnio || undefined,
        empleado_id: this.filtroEmpleado || undefined,
        periodo_id: this.filtroPeriodo || undefined,
        corrida_id: this.corridaId || undefined,
        sin_corrida: this.modoSinAgrupar || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.planillas = res.data.content;
            this.total = res.data.totalElements;
            // La suma la manda el backend sobre TODAS las planillas que pasan
            // el filtro; sumando acá solo saldrían las diez de esta página.
            this.masaSalarial = res.data.masaSalarial ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.cargando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las planillas.'));
        },
      });
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filtroMes = '';
    this.filtroAnio = '';
    this.filtroEmpleado = '';
    this.filtroPeriodo = '';
    this.cargar();
  }

  nueva(): void {
    this.router.navigate(['/inicio/planillas/nuevo']);
  }

  editar(planilla: Planilla): void {
    this.router.navigate(['/inicio/planillas/editar', planilla.id]);
  }

  verDetalle(planilla: Planilla): void {
    this.router.navigate(['/inicio/planillas/detalle', planilla.id]);
  }

  alAccionar(evento: { accion: string; fila: Planilla }): void {
    if (evento.accion === 'detalle') this.verDetalle(evento.fila);
    if (evento.accion === 'sacar') this.sacarDeLaPlanilla(evento.fila);
    if (evento.accion === 'mover') this.abrirMover(evento.fila);
  }

  /**
   * Saca a alguien de esta planilla. NO borra su pago: la fila sigue ahí con
   * sus conceptos y sus montos, solo deja de estar agrupada.
   */
  sacarDeLaPlanilla(planilla: Planilla): void {
    this.confirmService
      .confirmar({
        titulo: 'Sacar de esta planilla',
        mensaje: `${this.nombreEmpleado(planilla)} saldrá de "${this.corrida?.nombre}" y pasará a `
          + '"Sin agrupar". Su planilla y sus conceptos no se tocan: solo deja de estar agrupado.',
        aceptarTexto: 'Sí, sacar',
      })
      .then((aceptado) => {
        if (!aceptado) return;
        this.corridaService.sacar([planilla.id!]).subscribe({
          next: () => {
            this.toastService.success('Listo', `${this.nombreEmpleado(planilla)} pasó a "Sin agrupar".`);
            this.cargar();
          },
          error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo sacar de la planilla.')),
        });
      });
  }

  /** El catálogo, para el desplegable de "aplicar concepto". */
  abrirConcepto(): void {
    this.conceptoElegido = '';
    this.conceptoValor = null;
    this.conceptoCalculo = 'fijo';
    this.alcanceConcepto = 'todos';
    this.elegidosConcepto = [];
    this.resultadoConcepto = null;
    this.modalConceptoVisible = true;

    if (this.conceptos.length) return;
    this.conceptoService.getAll().subscribe({
      next: (res) => {
        if (res.success) this.conceptos = res.data;
      },
      error: () => this.toastService.error('Error', 'No se pudo cargar el catálogo de conceptos.'),
    });
  }

  cerrarConcepto(): void {
    this.modalConceptoVisible = false;
    this.conceptoElegido = '';
    this.conceptoValor = null;
    this.alcanceConcepto = 'todos';
    this.elegidosConcepto = [];
    this.resultadoConcepto = null;
  }

  /**
   * Los que se pueden aplicar a un grupo: TODOS menos los seis de cálculo
   * especial, que el backend rechaza porque dependen de la ficha de cada
   * quien.
   *
   * Antes se exigía además que trajeran regla en el catálogo, y eso dejaba
   * fuera a diecinueve de veintidós: los adelantos, los préstamos, la
   * alimentación... justo los que se aplican a un grupo en la vida real. El
   * monto se pide en el momento.
   */
  get conceptosAplicables(): PaymentConcept[] {
    return this.conceptos.filter((c) => !c.calculo_especial);
  }

  /** Agrupados por tipo, que es como los tiene la boleta. */
  get conceptosPorTipo(): { tipo: string; etiqueta: string; conceptos: PaymentConcept[] }[] {
    const grupos = [
      { tipo: 'bonificacion', etiqueta: 'Ingresos' },
      { tipo: 'descuento', etiqueta: 'Descuentos' },
      { tipo: 'aportacion', etiqueta: 'Aportaciones del colegio' },
      { tipo: 'adelanto', etiqueta: 'Adelantos' },
    ];
    return grupos
      .map((g) => ({ ...g, conceptos: this.conceptosAplicables.filter((c) => c.tipo === g.tipo) }))
      .filter((g) => g.conceptos.length > 0);
  }

  /** Al elegir concepto se copia su regla del catálogo, si la tiene. */
  alElegirConcepto(): void {
    const c = this.conceptoSeleccionado;
    if (!c) return;
    this.conceptoCalculo = c.calculo === 'porcentaje' ? 'porcentaje' : 'fijo';
    this.conceptoValor = c.valor != null ? Number(c.valor) : null;
  }

  /** Los soles que va a salir, para enseñarlos mientras se escribe. */
  get montoPrevisto(): number {
    const valor = Number(this.conceptoValor ?? 0);
    if (!valor) return 0;
    if (this.conceptoCalculo !== 'porcentaje') return +valor.toFixed(2);
    // Sobre el básico medio de la planilla: cada trabajador tendrá el suyo.
    const base = this.total ? this.masaSalarial / this.total : 0;
    return +(base * (valor / 100)).toFixed(2);
  }

  get conceptoSeleccionado(): PaymentConcept | undefined {
    return this.conceptos.find((c) => c.id === this.conceptoElegido);
  }

  /** A cuántos les va a caer el concepto tal como está ahora. */
  get alcanceDelConcepto(): number {
    return this.aplicaSoloAMarcados ? this.elegidosConcepto.length : this.total;
  }

  get aplicaSoloAMarcados(): boolean {
    return this.alcanceConcepto === 'elegidos';
  }

  aplicarConcepto(): void {
    if (!this.conceptoElegido) {
      this.toastService.error('Falta elegir', 'Elige el concepto que vas a aplicar.');
      return;
    }

    if (this.aplicaSoloAMarcados && !this.elegidosConcepto.length) {
      this.toastService.error('Falta elegir', 'Busca y marca al menos un trabajador.');
      return;
    }

    if (this.conceptoValor == null || Number(this.conceptoValor) <= 0) {
      this.toastService.error('Falta el monto', 'Indica cuánto se le aplica a cada trabajador.');
      return;
    }

    this.aplicandoConcepto = true;
    this.resultadoConcepto = null;

    /*
     * Dos alcances:
     *
     *   toda la planilla -> solo el id de la corrida, y el backend resuelve
     *                       el mes y a quiénes desde ella
     *   los elegidos     -> sus empleado_id, con el mes de la planilla
     */
    const destino = this.aplicaSoloAMarcados
      ? {
          mes: Number(this.corrida?.mes ?? this.filtroMes),
          anio: Number(this.corrida?.anio ?? this.filtroAnio),
          empleado_ids: this.elegidosConcepto,
        }
      : { corrida_id: this.corridaId };

    const regla = { calculo: this.conceptoCalculo, valor: Number(this.conceptoValor) };

    this.conceptoService.aplicarAGrupo(this.conceptoElegido, { ...destino, ...regla }).subscribe({
      next: (res) => {
        this.aplicandoConcepto = false;
        if (!res.success) return;

        this.resultadoConcepto = res.data;
        const { aplicadas, omitidas } = res.data.resumen;

        this.toastService.resultadoMasivo({
          hechas: aplicadas,
          omitidas,
          exito: `"${res.data.concepto}" aplicado`,
          nada: 'No se aplicó a nadie',
          cosas: 'trabajador(es)',
          motivo: 'no tienen planilla de este mes',
        });

        if (res.data.corrida && this.corrida) {
          this.corrida.personas = res.data.corrida.personas;
          this.corrida.masa_salarial = res.data.corrida.masa_salarial;
        }
        this.cargar();
      },
      error: (err) => {
        this.aplicandoConcepto = false;
        this.toastService.error('No se aplicó', mensajeErrorApi(err, 'No se pudo aplicar el concepto.'));
      },
    });
  }

  /** Abre el cuadro para elegir a qué planilla se mete esta fila. */
  abrirMover(planilla: Planilla): void {
    this.planillaAMover = planilla;
    this.corridaDestino = '';
    this.corridasDelMes = [];
    this.modalMoverVisible = true;

    // Solo las del MISMO mes: el backend rechaza el resto, y ofrecerlas sería
    // enseñar opciones que van a fallar.
    this.corridaService
      .getPagina({ mes: planilla.mes, anio: planilla.anio, estado: 'abierta', page: 0, size: 100 })
      .subscribe({
        next: (res) => {
          if (res.success) this.corridasDelMes = res.data.content;
        },
        error: () => this.toastService.error('Error', 'No se pudieron cargar las planillas del mes.'),
      });
  }

  cerrarMover(): void {
    this.modalMoverVisible = false;
    this.planillaAMover = null;
    this.corridaDestino = '';
  }

  confirmarMover(): void {
    if (!this.planillaAMover || !this.corridaDestino) {
      this.toastService.error('Falta elegir', 'Elige a qué planilla lo vas a meter.');
      return;
    }

    this.moviendo = true;
    this.corridaService.mover(this.corridaDestino, [this.planillaAMover.id!]).subscribe({
      next: (res) => {
        this.moviendo = false;
        this.toastService.success('Movido', `Ahora está en "${res.data.corrida.nombre}".`);
        this.cerrarMover();
        this.cargar();
      },
      error: (err) => {
        this.moviendo = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo mover.'));
      },
    });
  }

  eliminar(planilla: Planilla): void {
    this.confirmService.confirmarEliminar(
      `la planilla de ${this.nombreEmpleado(planilla)} de ${nombreMes(planilla.mes)} ${planilla.anio}`,
      () => {
        this.planillaService.delete(planilla.id!).subscribe({
          next: () => {
            this.toastService.success('Eliminada', 'La planilla fue dada de baja.');
            this.cargar();
          },
          error: (err) => {
            this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar la planilla.'));
          },
        });
      }
    );
  }

  // ────────── Generación masiva de planillas ──────────

  abrirGenerar(): void {
    this.resultadoGeneracion = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];
    this.modalGenerarVisible = true;
  }

  cerrarGenerar(): void {
    this.modalGenerarVisible = false;
    this.resultadoGeneracion = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];
  }

  /** A cuántos alcanzará la generación tal como está configurada ahora. */
  get cuantosAlcanzados(): number {
    return this.alcance === 'todos' ? this.empleadosActivos.length : this.empleadosElegidos.length;
  }

  /**
   * Desglosa un periodo en los meses que abarca. Un periodo de un año da
   * doce opciones; uno de un mes, una sola. Las fechas se construyen en
   * horario local: `new Date('2026-08-01')` sería medianoche UTC, que en
   * Perú (UTC-5) cae el 31 de julio, y el periodo empezaría un mes antes.
   */
  private mesesQueCubre(periodo: Periodo): { valor: string; etiqueta: string }[] {
    const aFecha = (texto: string) => {
      const [anio, mes, dia] = String(texto).slice(0, 10).split('-').map(Number);
      return new Date(anio, mes - 1, dia);
    };

    const inicio = aFecha(periodo.fecha_inicio);
    const fin = aFecha(periodo.fecha_fin);
    const opciones: { valor: string; etiqueta: string }[] = [];

    const cursor = new Date(inicio.getFullYear(), inicio.getMonth(), 1);
    const ultimo = new Date(fin.getFullYear(), fin.getMonth(), 1);

    while (cursor <= ultimo && opciones.length < 60) {
      const mes = cursor.getMonth() + 1;
      const anio = cursor.getFullYear();
      opciones.push({
        valor: `${anio}-${String(mes).padStart(2, '0')}`,
        etiqueta: `${nombreMes(mes)} ${anio}`,
      });
      cursor.setMonth(cursor.getMonth() + 1);
    }

    return opciones;
  }

  /**
   * Al cambiar de periodo se rehace la lista de meses y se conserva el que
   * estaba elegido si sigue siendo válido; si no, se pone el primero.
   */
  alCambiarPeriodo(): void {
    const id = this.formGenerar.get('periodo_id')!.value;
    const periodo = this.periodos.find((p) => p.id === id);

    this.mesesDelPeriodo = periodo ? this.mesesQueCubre(periodo) : [];

    const actual = this.formGenerar.get('mesAnio')!.value;
    const sigueValiendo = this.mesesDelPeriodo.some((m) => m.valor === actual);

    if (!sigueValiendo) {
      this.formGenerar.patchValue({ mesAnio: this.mesesDelPeriodo[0]?.valor ?? '' });
    }
  }

  /** El rango del periodo elegido, en cristiano, para mostrarlo bajo el campo. */
  get rangoDelPeriodo(): string {
    if (!this.mesesDelPeriodo.length) return '';
    const primero = this.mesesDelPeriodo[0].etiqueta;
    const ultimo = this.mesesDelPeriodo[this.mesesDelPeriodo.length - 1].etiqueta;
    return primero === ultimo
      ? `Este periodo solo cubre ${primero}.`
      : `Este periodo va de ${primero} a ${ultimo}.`;
  }

  /**
   * Le arma la planilla del mes a más gente DENTRO de esta corrida.
   *
   * El mes no se pregunta: es el de la planilla en la que estamos. Al que ya
   * la tiene se le salta, así que darle dos veces no duplica ni pisa lo que
   * se haya ajustado a mano.
   */
  generar(): void {
    if (!this.corridaId) {
      this.toastService.error('Sin planilla', 'Entra a una planilla para agregarle trabajadores.');
      return;
    }

    if (this.alcance === 'elegidos' && !this.empleadosElegidos.length) {
      this.toastService.error('Falta elegir', 'Marca al menos un trabajador, o cambia a "todo el personal".');
      return;
    }

    this.generando = true;
    this.resultadoGeneracion = null;

    // Sin lista, el backend alcanza a todo el personal activo.
    const grupo = this.alcance === 'elegidos' ? { empleado_ids: this.empleadosElegidos } : {};

    this.corridaService.generar(this.corridaId, grupo).subscribe({
      next: (res) => {
        this.generando = false;
        if (!res.success) return;

        this.resultadoGeneracion = res.data as any;
        const { generadas, omitidas } = res.data.resumen;

        this.toastService.resultadoMasivo({
          hechas: generadas,
          omitidas,
          exito: 'Trabajadores agregados',
          nada: 'No se agregó a nadie',
          cosas: 'planilla(s)',
          motivo: 'ya tenían planilla de ese mes, no tienen sueldo puesto, o todavía no habían ingresado',
        });

        if (res.data.corrida) this.corrida = res.data.corrida;
        this.cargar();
      },
      error: (err) => {
        this.generando = false;
        this.toastService.error('No se agregó', mensajeErrorApi(err, 'No se pudieron agregar los trabajadores.'));
      },
    });
  }

  // ────────── Emisión masiva de boletas ──────────

  abrirBoletas(): void {
    this.resultadoBoletas = null;
    this.modalBoletasVisible = true;
  }

  cerrarBoletas(): void {
    this.modalBoletasVisible = false;
    this.resultadoBoletas = null;
  }

  /** El mes que se va a emitir: el del filtro, o el actual si está vacío. */
  get mesAEmitir(): number {
    return Number(this.filtroMes) || new Date().getMonth() + 1;
  }

  get anioAEmitir(): number {
    return Number(this.filtroAnio) || new Date().getFullYear();
  }

  /**
   * Emite el PDF de la boleta de todos los empleados que YA tengan planilla
   * de ese mes; a quien no la tenga se le omite. Es idempotente: si la
   * boleta ya existe, no se vuelve a generar.
   */
  emitirBoletas(): void {
    this.emitiendo = true;
    this.resultadoBoletas = null;

    this.boletaService.generarMasivo(this.mesAEmitir, this.anioAEmitir).subscribe({
      next: (res) => {
        this.emitiendo = false;
        this.resultadoBoletas = { generadas: res.generadas ?? 0, omitidas: res.omitidas ?? 0 };
        this.toastService.resultadoMasivo({
          hechas: this.resultadoBoletas.generadas,
          omitidas: this.resultadoBoletas.omitidas,
          exito: 'Boletas emitidas',
          nada: 'No se emitió ninguna boleta',
          cosas: 'boleta(s)',
          motivo: 'esos empleados no tienen planilla de ese mes, o ya tenían su boleta',
        });
      },
      error: (err) => {
        this.emitiendo = false;
        this.toastService.error('No se emitieron', mensajeErrorApi(err, 'No se pudieron generar las boletas.'));
      },
    });
  }
}
