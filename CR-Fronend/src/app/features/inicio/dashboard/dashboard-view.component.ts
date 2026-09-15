import { Component, HostListener, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import {
  NgApexchartsModule,
  ApexChart,
  ApexNonAxisChartSeries,
  ApexAxisChartSeries,
  ApexXAxis,
  ApexYAxis,
  ApexPlotOptions,
  ApexDataLabels,
  ApexLegend,
  ApexTooltip,
  ApexFill,
  ApexStroke,
  ApexResponsive,
  ApexGrid,
} from 'ng-apexcharts';

import { DashboardService, SedeService, ToastService } from '../../../core/services';
import { ContratoPorVencer, CumpleanosDelMes, Dashboard, DatoGrafico, PendienteRrhh, Sede } from '../../../core/models';
import { fechaLegible, mensajeErrorApi } from '../../../core/utils';
import {
  PALETA_SERIES,
  PALETA_SERIE_UNICA,
  PALETA_DEGRADADO_BARRA,
  PALETA_ESTADO,
  PALETA_MARCA,
  PALETA_ACENTO,
  MESES_OPCIONES,
  nombreMes,
  fechaEnPalabras,
} from '../../../shared/constants';

/**
 * Panel de Control de RR.HH.
 *
 * Todas las cifras salen de GET /dashboard. Antes estaban escritas a mano
 * acá dentro (127 empleados, S/ 148,300 de nómina), así que la pantalla
 * enseñaba lo mismo aunque el colegio no tuviera ni un trabajador dado de
 * alta.
 *
 * Dos gráficos cambiaron de tema porque el dato que pedían no existe en la
 * base: "distribución por sexo" (no se guarda el sexo) y "por nivel
 * educativo" (nivel_estudios es el grado académico del trabajador, no el
 * nivel donde enseña). En su sitio van el sistema de pensiones y el estado
 * de firma de las boletas del mes.
 */
@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, FormsModule, NgApexchartsModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss',
})
export class DashboardViewComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  cargando = true;
  etiquetaPeriodo = '';

  /*
   * Los filtros del panel.
   *
   * Antes la pantalla siempre enseñaba el mes en curso y toda la nómina
   * junta, aunque el backend ya sabía filtrar: para saber cuánto costó
   * agosto, o cuánto cuesta Jerusalén, había que salir a otra pantalla y
   * sacar la cuenta.
   */
  filtroMes: number = new Date().getMonth() + 1;
  filtroAnio: number = new Date().getFullYear();
  filtroSede = '';

  meses = MESES_OPCIONES;
  sedes: Sede[] = [];
  anios: number[] = [];

  /** Sin conceptos aplicados no hay nada que graficar: se esconde. */
  hayTopConceptos = false;

  /** Lo que RR.HH. tiene pendiente de hacer. */
  pendientes: PendienteRrhh[] = [];
  cumpleanos: CumpleanosDelMes[] = [];

  /** Solo los que tienen algo pendiente: los que están en cero no son noticia. */
  get pendientesConTrabajo(): PendienteRrhh[] {
    return this.pendientes.filter((p) => p.cuantos > 0);
  }

  get todoAlDia(): boolean {
    return !this.cargando && this.pendientesConTrabajo.length === 0;
  }

  // ── KPIs ──────────────────────────────────────────────────────────
  kpis: { label: string; value: string; sub: string; icon: string; color: string; bg: string }[] = [];

  // ── GRÁFICO 1: Remuneración por Área (barras horizontales) ────────
  barChartSeries: ApexAxisChartSeries = [{ name: 'Pagado', data: [] }];
  barChartOptions: ApexChart = { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  /**
   * En una barra HORIZONTAL el eje X es el de los montos y el Y el de las
   * categorías. Estaban al revés: el formateador de soles se aplicaba a los
   * nombres de las áreas, y por eso el eje mostraba "S/ NaNk" en cada fila.
   */
  barChartXaxis: ApexXAxis = {
    categories: [],
    labels: { formatter: (v) => this.enMiles(v), style: { fontSize: '11px' } },
  };
  barChartYaxis: ApexYAxis = { labels: { style: { fontSize: '12px' } } };
  barChartPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } };
  barChartDataLabels: ApexDataLabels = { enabled: false };
  barChartColors = PALETA_SERIE_UNICA;
  barChartGrid: ApexGrid = { xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } };
  barChartTooltip: ApexTooltip = { y: { formatter: (v) => this.enSoles(v) } };
  barChartFill: ApexFill = {
    type: 'gradient',
    gradient: { shade: 'light', type: 'horizontal', gradientToColors: PALETA_DEGRADADO_BARRA, stops: [0, 100] },
  };

  // ── GRÁFICO 2: Sistema de pensiones (dona) ────────────────────────
  donutSeries: ApexNonAxisChartSeries = [];
  donutChart: ApexChart = { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' };
  donutLabels: string[] = [];
  donutColors = [...PALETA_SERIES];
  donutLegend: ApexLegend = { position: 'bottom', fontSize: '13px', labels: {} };
  donutDataLabels: ApexDataLabels = { enabled: true, formatter: (val: number) => val.toFixed(1) + '%' };
  donutResponsive: ApexResponsive[] = [{ breakpoint: 480, options: { chart: { height: 220 } } }];

  // ── GRÁFICO 3: Tipo de Contrato (pastel) ─────────────────────────
  pieContrato: ApexNonAxisChartSeries = [];
  pieContratoChart: ApexChart = { type: 'pie', height: 260, fontFamily: 'Inter, sans-serif' };
  pieContratoLabels: string[] = [];
  pieContratoColors = [PALETA_SERIES[0], PALETA_ESTADO.exito, PALETA_SERIES[1], PALETA_ESTADO.aviso];
  pieLegend: ApexLegend = { position: 'bottom', fontSize: '12px', labels: {} };

  // ── GRÁFICO 4: Tendencia de Nómina (área) ────────────────────────
  lineNominaSeries: ApexAxisChartSeries = [{ name: 'Nómina total', data: [] }];
  lineNominaChart: ApexChart = { type: 'area', height: 200, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  lineNominaXaxis: ApexXAxis = { categories: [], labels: { style: { fontSize: '11px' } } };
  lineNominaYaxis: ApexYAxis = { labels: { formatter: (v) => this.enMiles(v), style: { fontSize: '11px' } } };
  lineNominaColors = PALETA_SERIE_UNICA;
  lineNominaStroke: ApexStroke = { curve: 'smooth', width: 3 };
  lineNominaFill: ApexFill = { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } };
  lineNominaDataLabels: ApexDataLabels = { enabled: false };
  lineNominaGrid: ApexGrid = { padding: { left: 10, right: 10 } };
  lineNominaTooltip: ApexTooltip = { y: { formatter: (v) => this.enSoles(v) } };
  /** Cuánto subió o bajó respecto al mes anterior, para el chip de la tarjeta. */
  variacionNomina = 0;

  // ── GRÁFICO 6: A dónde se va la plata (barras apiladas) ──────────
  //
  // Es LA pregunta de una planilla y el panel no la contestaba: enseñaba el
  // neto y el reparto por área, pero no cuánto de ese gasto es sueldo,
  // cuánto se añade, cuánto se retiene y cuánto pone el colegio encima.
  composicionSeries: ApexAxisChartSeries = [{ name: 'Monto', data: [] }];
  composicionChart: ApexChart = { type: 'bar', height: 250, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  composicionXaxis: ApexXAxis = { categories: [], labels: { style: { fontSize: '11px' } } };
  composicionYaxis: ApexYAxis = { labels: { formatter: (v) => this.enMiles(v), style: { fontSize: '11px' } } };
  composicionPlot: ApexPlotOptions = {
    bar: { borderRadius: 6, columnWidth: '52%', distributed: true },
  };
  /*
   * Un color por concepto y no por serie: lo que suma va en el azul de
   * marca, lo que resta en rojo y ámbar, y lo que pone el colegio en verde.
   * Así se lee de un vistazo qué entra y qué sale sin leer las etiquetas.
   */
  composicionColores = [
    PALETA_MARCA.b700,      // sueldo básico
    PALETA_ESTADO.exito,    // bonificaciones
    PALETA_ESTADO.peligro,  // descuentos
    PALETA_ESTADO.aviso,    // adelantos
    PALETA_MARCA.b500,      // aporta el colegio
  ];
  composicionDataLabels: ApexDataLabels = { enabled: false };
  composicionLegend: ApexLegend = { show: false };
  composicionTooltip: ApexTooltip = { y: { formatter: (v) => this.enSoles(v) } };
  composicionGrid: ApexGrid = { yaxis: { lines: { show: true } } };

  // ── GRÁFICO 7: Personal por sede (dona) ──────────────────────────
  sedeSeries: ApexNonAxisChartSeries = [];
  sedeLabels: string[] = [];
  sedeChart: ApexChart = { type: 'donut', height: 240, fontFamily: 'Inter, sans-serif' };
  sedeColores = [PALETA_MARCA.b700, PALETA_ACENTO, PALETA_MARCA.b500, PALETA_ESTADO.exito];
  sedeLegend: ApexLegend = { position: 'bottom', fontSize: '12px', labels: {} };
  sedeDataLabels: ApexDataLabels = { enabled: true, formatter: (v: number) => v.toFixed(0) + '%' };

  // ── GRÁFICO 8: Antigüedad del personal (barras) ──────────────────
  //
  // Dice dos cosas que RR.HH. mira: quién está por cumplir años de servicio
  // y si la plantilla es estable o rota mucho.
  antiguedadSeries: ApexAxisChartSeries = [{ name: 'Trabajadores', data: [] }];
  antiguedadChart: ApexChart = { type: 'bar', height: 240, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  antiguedadXaxis: ApexXAxis = { categories: [], labels: { style: { fontSize: '10.5px' } } };
  antiguedadYaxis: ApexYAxis = { labels: { formatter: (v) => String(Math.round(v)), style: { fontSize: '11px' } } };
  antiguedadPlot: ApexPlotOptions = { bar: { borderRadius: 6, columnWidth: '50%', distributed: true } };
  /* Del azul claro al oscuro: cuanto más lleva la persona, más intenso. */
  antiguedadColores = [
    PALETA_MARCA.b500, PALETA_MARCA.b600, PALETA_MARCA.b700, PALETA_MARCA.b900, PALETA_ACENTO,
  ];
  antiguedadDataLabels: ApexDataLabels = { enabled: true, style: { fontSize: '11px' } };
  antiguedadLegend: ApexLegend = { show: false };

  // ── GRÁFICO 9: Los conceptos que más pesan (barras horizontales) ──
  //
  // Sin los de ley: la pensión y EsSalud siempre estarían arriba porque le
  // tocan a todo el mundo. Lo que dice algo es qué OTRA cosa está costando.
  conceptosSeries: ApexAxisChartSeries = [{ name: 'Monto', data: [] }];
  conceptosChart: ApexChart = { type: 'bar', height: 240, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  conceptosXaxis: ApexXAxis = { categories: [], labels: { formatter: (v) => this.enMiles(v), style: { fontSize: '11px' } } };
  conceptosYaxis: ApexYAxis = { labels: { style: { fontSize: '11px' } } };
  conceptosPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 5, barHeight: '58%', distributed: true } };
  conceptosColores = [
    PALETA_ACENTO, PALETA_MARCA.b700, PALETA_ESTADO.exito,
    PALETA_MARCA.b500, PALETA_ESTADO.aviso, PALETA_MARCA.b600,
  ];
  conceptosDataLabels: ApexDataLabels = { enabled: false };
  conceptosLegend: ApexLegend = { show: false };
  conceptosTooltip: ApexTooltip = { y: { formatter: (v) => this.enSoles(v) } };

  // ── GRÁFICO 5: Firma de boletas del mes (radial) ─────────────────
  nivelSeries: ApexNonAxisChartSeries = [];
  nivelChart: ApexChart = { type: 'radialBar', height: 260, fontFamily: 'Inter, sans-serif' };
  nivelLabels: string[] = ['Firmadas', 'Vistas', 'Pendientes'];
  nivelColors = [PALETA_ESTADO.exito, PALETA_SERIES[0], PALETA_ESTADO.aviso];
  nivelPlot: ApexPlotOptions = {
    radialBar: {
      hollow: { size: '20%' },
      track: { margin: 5 },
      dataLabels: {
        show: true,
        name: { fontSize: '12px' },
        value: { fontSize: '16px', fontWeight: '700', formatter: (v: number) => v + '%' },
        total: { show: true, label: 'Boletas', fontSize: '12px', formatter: () => String(this.totalBoletas) },
      },
    },
  };
  private totalBoletas = 0;

  // ── TABLA: contratos que se acaban ───────────────────────────────
  vencimientos: ContratoPorVencer[] = [];

  fechaHoy = fechaEnPalabras();

  ngOnInit(): void {
    const actual = new Date().getFullYear();
    for (let a = actual + 1; a >= actual - 4; a--) this.anios.push(a);

    this.sedeService.getAll().subscribe({
      next: (res) => {
        if (res.success) this.sedes = res.data;
      },
      // Si falla, el filtro de sede se queda vacío y el panel sigue mostrando
      // todo: no es motivo para dejar la pantalla en blanco.
      error: () => {},
    });

    this.cargar();
  }

  /** El usuario movió un filtro. */
  alFiltrar(): void {
    this.cargar();
  }

  // ────────── El periodo que se está mirando ──────────

  /**
   * Se movía el mes con un desplegable de doce y otro de años. Pero lo que
   * hace RR.HH. casi siempre es mirar el mes de al lado —"¿y en agosto?"—,
   * y eso tenía que costar un clic, no tres.
   */
  nombreMes = nombreMes;

  calendarioAbierto = false;
  anioDelCalendario = new Date().getFullYear();

  moverMes(pasos: number): void {
    const fecha = new Date(this.filtroAnio, this.filtroMes - 1 + pasos, 1);
    this.filtroMes = fecha.getMonth() + 1;
    this.filtroAnio = fecha.getFullYear();
    this.anioDelCalendario = this.filtroAnio;
    this.cargar();
  }

  /** Para el título de las flechas: se sabe a dónde llevan antes de pulsar. */
  etiquetaMesVecino(pasos: number): string {
    const fecha = new Date(this.filtroAnio, this.filtroMes - 1 + pasos, 1);
    return `${nombreMes(fecha.getMonth() + 1)} ${fecha.getFullYear()}`;
  }

  /**
   * El clic que abre la rejilla se queda aquí: si sube hasta el document, el
   * cierre de "clic fuera" la cerraría en el mismo golpe y no abriría nunca.
   */
  alternarCalendario(evento: MouseEvent): void {
    evento.stopPropagation();
    this.calendarioAbierto = !this.calendarioAbierto;
    if (this.calendarioAbierto) this.anioDelCalendario = this.filtroAnio;
  }

  moverAnioDelCalendario(pasos: number): void {
    this.anioDelCalendario += pasos;
  }

  elegirMes(mes: number): void {
    this.filtroMes = mes;
    this.filtroAnio = this.anioDelCalendario;
    this.calendarioAbierto = false;
    this.cargar();
  }

  esMesElegido(mes: number): boolean {
    return this.filtroMes === mes && this.filtroAnio === this.anioDelCalendario;
  }

  /** El mes en curso lleva un punto: sirve de brújula al pasear por los años. */
  esMesDeHoy(mes: number): boolean {
    const hoy = new Date();
    return hoy.getMonth() + 1 === mes && hoy.getFullYear() === this.anioDelCalendario;
  }

  /** Un clic fuera o un Escape cierran la rejilla de meses. */
  @HostListener('document:click')
  cerrarCalendario(): void {
    this.calendarioAbierto = false;
  }

  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    this.calendarioAbierto = false;
  }

  elegirSede(id: string): void {
    this.filtroSede = id;
    this.cargar();
  }

  get sedeElegida(): Sede | undefined {
    return this.sedes.find((s) => s.id === this.filtroSede);
  }

  /** Vuelve al mes en curso y a toda la nómina. */
  limpiarFiltros(): void {
    const hoy = new Date();
    this.filtroMes = hoy.getMonth() + 1;
    this.filtroAnio = hoy.getFullYear();
    this.anioDelCalendario = this.filtroAnio;
    this.filtroSede = '';
    this.cargar();
  }

  get hayFiltros(): boolean {
    const hoy = new Date();
    return (
      this.filtroMes !== hoy.getMonth() + 1 ||
      this.filtroAnio !== hoy.getFullYear() ||
      !!this.filtroSede
    );
  }

  /** Lo que se está viendo, en palabras. */
  get loQueSeVe(): string {
    const sede = this.sedes.find((s) => s.id === this.filtroSede);
    return `${nombreMes(this.filtroMes)} ${this.filtroAnio}` + (sede ? ` · ${sede.nombre}` : '');
  }

  /**
   * Del pendiente a la pantalla donde se resuelve. Por URL y no por
   * navigate(): la ruta puede traer un filtro ("?filtro=boletas_por_firmar")
   * y navigate() lo codificaría como parte del camino.
   */
  irAResolver(pendiente: PendienteRrhh): void {
    this.router.navigateByUrl(pendiente.ruta);
  }

  private cargar(): void {
    this.cargando = true;

    this.dashboardService.obtener(this.filtroMes, this.filtroAnio, this.filtroSede || null).subscribe({
      next: (res) => {
        if (res.success) this.pintar(res.data);
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las cifras del panel.'));
      },
    });
  }

  /** Vuelca lo que mandó el backend en cada gráfico. */
  private pintar(d: Dashboard): void {
    const r = d.resumen;
    this.etiquetaPeriodo = `${nombreMes(d.periodo.mes)} ${d.periodo.anio}`;
    this.pendientes = d.pendientes ?? [];
    this.cumpleanos = d.cumpleanos ?? [];

    const comp = d.composicionNomina ?? [];
    this.composicionSeries = [{ name: 'Monto', data: comp.map((c) => c.valor) }];
    this.composicionXaxis = { ...this.composicionXaxis, categories: comp.map((c) => c.etiqueta) };

    const sedes = d.personalPorSede ?? [];
    this.sedeSeries = sedes.map((x) => x.valor);
    this.sedeLabels = sedes.map((x) => x.etiqueta);

    const ant = d.antiguedad ?? [];
    this.antiguedadSeries = [{ name: 'Trabajadores', data: ant.map((x) => x.valor) }];
    this.antiguedadXaxis = { ...this.antiguedadXaxis, categories: ant.map((x) => x.etiqueta) };

    const tops = d.topConceptos ?? [];
    this.conceptosSeries = [{ name: 'Monto', data: tops.map((x) => x.valor) }];
    this.conceptosXaxis = { ...this.conceptosXaxis, categories: tops.map((x) => x.etiqueta) };
    this.hayTopConceptos = tops.length > 0;

    // Las boletas se cuentan contra las planillas del mes: a quien no se le
    // armó planilla no se le puede emitir boleta, así que ese es el 100%.
    const porcentajeBoletas = r.planillasDelMes
      ? Math.round((r.boletasEmitidas / r.planillasDelMes) * 100)
      : 0;

    this.kpis = [
      {
        label: 'Personal activo',
        value: String(r.empleadosActivos),
        sub: r.altasDelMes ? `+${r.altasDelMes} este mes` : 'Sin altas este mes',
        icon: 'people', color: 'var(--brand-700)', bg: 'var(--brand-100)',
      },
      {
        label: 'Nómina del mes',
        value: this.enSoles(r.nominaDelMes),
        sub: this.etiquetaPeriodo,
        icon: 'money', color: 'var(--success-text)', bg: 'var(--success-bg)',
      },
      {
        label: 'Boletas emitidas',
        value: `${porcentajeBoletas}%`,
        sub: `${r.boletasEmitidas} de ${r.planillasDelMes} planillas`,
        icon: 'file', color: 'var(--warning-text)', bg: 'var(--warning-bg)',
      },
      {
        label: 'Contratos por vencer',
        value: String(r.contratosPorVencer),
        sub: 'Próximos 30 días',
        icon: 'warning', color: 'var(--danger-text)', bg: 'var(--danger-bg)',
      },
    ];

    // Remuneración por área
    this.barChartSeries = [{ name: 'Pagado', data: d.remuneracionPorArea.map((a) => a.valor) }];
    this.barChartXaxis = { ...this.barChartXaxis, categories: this.etiquetas(d.remuneracionPorArea) };

    // Sistema de pensiones
    this.donutSeries = d.sistemaPensiones.map((s) => s.valor);
    this.donutLabels = this.etiquetas(d.sistemaPensiones);

    // Tipo de contrato
    this.pieContrato = d.tipoContrato.map((t) => t.valor);
    this.pieContratoLabels = this.etiquetas(d.tipoContrato);

    // Tendencia de la nómina
    this.lineNominaSeries = [{ name: 'Nómina total', data: d.tendenciaNomina.map((m) => m.valor) }];
    this.lineNominaXaxis = { ...this.lineNominaXaxis, categories: this.etiquetas(d.tendenciaNomina) };
    this.variacionNomina = this.calcularVariacion(d.tendenciaNomina);

    // Firma de boletas: el radial va en porcentaje sobre el total emitido
    const f = d.firmaBoletas;
    this.totalBoletas = f.firmadas + f.vistas + f.pendientes;
    const porcentaje = (n: number) => (this.totalBoletas ? Math.round((n / this.totalBoletas) * 100) : 0);
    this.nivelSeries = [porcentaje(f.firmadas), porcentaje(f.vistas), porcentaje(f.pendientes)];

    this.vencimientos = d.contratosPorVencer;
  }

  /** Cuánto cambió el último mes con datos respecto al anterior, en %. */
  private calcularVariacion(meses: DatoGrafico[]): number {
    const conDatos = meses.filter((m) => m.valor > 0);
    if (conDatos.length < 2) return 0;

    const ultimo = conDatos[conDatos.length - 1].valor;
    const previo = conDatos[conDatos.length - 2].valor;
    if (!previo) return 0;

    return Math.round(((ultimo - previo) / previo) * 1000) / 10;
  }

  private etiquetas(datos: DatoGrafico[]): string[] {
    return datos.map((d) => d.etiqueta);
  }

  /** La fecha de vencimiento en formato peruano, sin desfase horario. */
  fechaVence(valor: string): string {
    return fechaLegible(valor);
  }

  /** "S/ 9,048.40" */
  enSoles(valor: number): string {
    return 'S/ ' + Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /** "S/ 9k" para los ejes, donde no cabe el número entero. */
  private enMiles(valor: number | string): string {
    const n = Number(valor);
    if (!isFinite(n)) return String(valor);
    return n >= 1000 ? `S/ ${Math.round(n / 1000)}k` : `S/ ${Math.round(n)}`;
  }

  /** ¿Hay algo que pintar en este gráfico? */
  hayDatos(serie: ApexNonAxisChartSeries | number[]): boolean {
    return (serie as number[]).some((v) => Number(v) > 0);
  }

  get hayRemuneracion(): boolean {
    return ((this.barChartSeries[0]?.data as number[]) ?? []).length > 0;
  }

  get hayTendencia(): boolean {
    return this.hayDatos((this.lineNominaSeries[0]?.data as number[]) ?? []);
  }
}
