import { Component, HostListener, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  NgApexchartsModule,
  ApexAnnotations,
  ApexAxisChartSeries,
  ApexChart,
  ApexDataLabels,
  ApexFill,
  ApexGrid,
  ApexLegend,
  ApexMarkers,
  ApexPlotOptions,
  ApexResponsive,
  ApexStroke,
  ApexTooltip,
  ApexXAxis,
  ApexYAxis,
} from 'ng-apexcharts';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { DashboardService, SedeService, ToastService } from '../../../core/services';
import {
  ContratoPorVencer,
  CumpleanosDelMes,
  Dashboard,
  DatoGrafico,
  MovimientoMes,
  PendienteRrhh,
  Sede,
} from '../../../core/models';
import { fechaLegible, guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import {
  PALETA_ACENTO,
  PALETA_ESTADO,
  PALETA_MARCA,
  PALETA_NEUTRO,
  MESES_OPCIONES,
  nombreMes,
  fechaEnPalabras,
} from '../../../shared/constants';

/** Una barra de las doce del bloque de arriba. */
interface MesDelAnio {
  mes: number;
  etiqueta: string;
  valor: number;
  /** Alto de la barra, en % del mes más caro. */
  alto: number;
}

/** Un día del calendario de cumpleaños (null = hueco antes del día 1). */
interface DiaCalendario {
  dia: number | null;
  cumple: CumpleanosDelMes[];
  esHoy: boolean;
  yaPaso: boolean;
}

/** Un escalón de la cascada de la nómina: de dónde sale el neto. */
interface PasoCascada {
  etiqueta: string;
  /** Para el eje, donde el nombre entero no cabe. */
  corta: string;
  /** Con signo: lo que resta va en negativo. */
  monto: number;
  desde: number;
  hasta: number;
  color: string;
  /** Neto y costo total: van desde cero y cierran la cuenta. */
  esTotal: boolean;
}

/** Una de las barras de proporción de «Cómo es el personal». */
interface Proporcion {
  titulo: string;
  total: number;
  tramos: { etiqueta: string; valor: number; porcentaje: number }[];
}

/**
 * Panel de Control de RR.HH.
 *
 * Todas las cifras salen de GET /dashboard en una sola respuesta, y todas
 * respetan el mes y la sede elegidos.
 *
 * Arriba va la nómina del mes en el azul de la marca: es la pregunta con la
 * que RR.HH. abre el panel. Al lado, los doce meses del año como barras que
 * llevan a ese mes de un clic, y cómo van las boletas (emitidas, vistas,
 * firmadas). Debajo, el detalle: la comparación con el año anterior, los
 * cumpleaños en un calendario, de dónde sale el neto y cómo es el personal.
 */
@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, FormsModule, NgApexchartsModule, IconComponent, PistaDirective],
  templateUrl: './dashboard-view.component.html',
})
export class DashboardViewComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private router = inject(Router);

  cargando = true;
  /** Ya se pintó una vez: los cambios de filtro atenúan en vez de vaciar. */
  hayDatos = false;

  // ── Filtros ───────────────────────────────────────────────────────
  filtroMes: number = new Date().getMonth() + 1;
  filtroAnio: number = new Date().getFullYear();
  filtroSede = '';

  meses = MESES_OPCIONES;
  sedes: Sede[] = [];
  nombreMes = nombreMes;
  fechaHoy = fechaEnPalabras();

  calendarioAbierto = false;
  anioDelCalendario = new Date().getFullYear();

  /** Se está descargando el Excel del panel. */
  exportando = false;

  // ── Lo que llegó del backend ──────────────────────────────────────
  pendientes: PendienteRrhh[] = [];
  cumpleanos: CumpleanosDelMes[] = [];
  vencimientos: ContratoPorVencer[] = [];
  movimiento: MovimientoMes[] = [];

  // ── El bloque de arriba ───────────────────────────────────────────
  nomina = 0;
  nominaAnterior = 0;
  aportes = 0;
  planillas = 0;
  activos = 0;
  altas = 0;
  porVencer = 0;
  mesesDelAnio: MesDelAnio[] = [];
  /** Boletas del mes: cuántas en cada paso, sobre las planillas armadas. */
  boletas = { firmadas: 0, vistas: 0, sinAbrir: 0, sinEmitir: 0, base: 0 };

  // ── Cumpleaños ────────────────────────────────────────────────────
  semanas: DiaCalendario[][] = [];
  diasSemana = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
  /** Un día marcado en el calendario: la lista se queda solo con ese día. */
  diaElegido: number | null = null;

  // ── Cómo es el personal ───────────────────────────────────────────
  proporciones: Proporcion[] = [];

  /** Edad o antigüedad: las dos van en la misma tarjeta. */
  vistaPersonal: 'edad' | 'antiguedad' = 'edad';
  private edades: DatoGrafico[] = [];
  private antiguedad: DatoGrafico[] = [];

  // ── Opciones comunes de ApexCharts ────────────────────────────────
  private base: ApexChart = {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    zoom: { enabled: false },
    animations: { enabled: true, speed: 450 },
  };
  sinEtiquetas: ApexDataLabels = { enabled: false };
  sinLeyenda: ApexLegend = { show: false };
  rejilla: ApexGrid = {
    strokeDashArray: 4,
    padding: { left: 8, right: 8 },
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } },
  };
  rejillaHorizontal: ApexGrid = {
    strokeDashArray: 4,
    padding: { left: 4, right: 16 },
    xaxis: { lines: { show: true } },
    yaxis: { lines: { show: false } },
  };
  tooltipSoles: ApexTooltip = { y: { formatter: (v) => this.enSoles(v) } };

  // ── 1. La nómina mes a mes, contra el año anterior ────────────────
  tendenciaSeries: ApexAxisChartSeries = [];
  tendenciaChart: ApexChart = { ...this.base, type: 'line', height: 330 };
  tendenciaColores = [PALETA_MARCA.b700, PALETA_NEUTRO];
  tendenciaStroke: ApexStroke = { curve: 'monotoneCubic', width: [3, 2], dashArray: [0, 6] };
  tendenciaFill: ApexFill = {
    type: ['gradient', 'solid'],
    gradient: { shadeIntensity: 1, opacityFrom: 0.32, opacityTo: 0.02, stops: [0, 95] },
  };
  tendenciaMarkers: ApexMarkers = { size: 0, hover: { size: 5 } };
  tendenciaXaxis: ApexXAxis = { categories: [], axisBorder: { show: false }, axisTicks: { show: false } };
  tendenciaYaxis: ApexYAxis = { labels: { formatter: (v) => this.enMiles(v) } };
  tendenciaLeyenda: ApexLegend = { position: 'top', horizontalAlign: 'right', fontSize: '12px' };
  tendenciaTooltip: ApexTooltip = { shared: true, intersect: false, y: { formatter: (v) => this.enSoles(v) } };
  tendenciaMarca: ApexAnnotations = {};
  /** Lo pagado en el año hasta el mes elegido, y lo mismo del año anterior. */
  acumulado = 0;
  acumuladoAnterior = 0;

  // ── 2. De dónde sale el neto (cascada) ────────────────────────────
  cascada: PasoCascada[] = [];
  cascadaSeries: ApexAxisChartSeries = [];
  cascadaChart: ApexChart = { ...this.base, type: 'rangeBar', height: 290 };
  cascadaPlot: ApexPlotOptions = { bar: { horizontal: false, columnWidth: '58%', borderRadius: 4 } };
  cascadaXaxis: ApexXAxis = {
    type: 'category',
    labels: { rotate: 0, trim: false, hideOverlappingLabels: false, style: { fontSize: '11px' } },
    tooltip: { enabled: false },
    axisBorder: { show: false },
    axisTicks: { show: false },
  };
  cascadaYaxis: ApexYAxis = { min: 0, labels: { formatter: (v) => this.enMiles(v) } };
  cascadaTooltip: ApexTooltip = {
    custom: ({ dataPointIndex }: { dataPointIndex: number }) => {
      const p = this.cascada[dataPointIndex];
      if (!p) return '';
      const signo = p.esTotal ? '' : p.monto < 0 ? '− ' : '+ ';
      return `<div class="grafico-globo"><span>${p.etiqueta}</span><strong>${signo}${this.enSoles(Math.abs(p.monto))}</strong></div>`;
    },
  };

  // ── 3. Lo pagado por área ─────────────────────────────────────────
  areaSeries: ApexAxisChartSeries = [];
  areaChart: ApexChart = { ...this.base, height: 260 };
  areaPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } };
  areaXaxis: ApexXAxis = { categories: [], labels: { formatter: (v) => this.enMiles(v) } };
  areaYaxis: ApexYAxis = { labels: { maxWidth: 200, style: { fontSize: '12px' } } };
  areaColores = [PALETA_MARCA.b700];
  /** En el celular el nombre del área se acorta y el eje lleva menos marcas. */
  areaResponsive: ApexResponsive[] = [
    { breakpoint: 640, options: { yaxis: { labels: { maxWidth: 110 } }, xaxis: { tickAmount: 2 } } },
  ];
  areaTooltip: ApexTooltip = {};
  private personasPorArea: number[] = [];

  // ── 4. Edad o antigüedad ──────────────────────────────────────────
  personalSeries: ApexAxisChartSeries = [];
  personalChart: ApexChart = { ...this.base, height: 250 };
  personalPlot: ApexPlotOptions = {
    bar: { borderRadius: 5, columnWidth: '56%', dataLabels: { position: 'top' } },
  };
  personalXaxis: ApexXAxis = { categories: [], labels: { rotate: 0, hideOverlappingLabels: false }, axisBorder: { show: false }, axisTicks: { show: false } };
  personalYaxis: ApexYAxis = { labels: { formatter: (v) => String(Math.round(v)) } };
  personalEtiquetas: ApexDataLabels = { enabled: true, offsetY: -20, style: { fontSize: '12px', fontWeight: 700 } };
  personalColores = [PALETA_MARCA.b500];

  // ── 5. Los conceptos que más pesan ────────────────────────────────
  conceptosSeries: ApexAxisChartSeries = [];
  conceptosChart: ApexChart = { ...this.base, height: 250 };
  conceptosPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } };
  conceptosXaxis: ApexXAxis = { categories: [], tickAmount: 3, labels: { formatter: (v) => this.enMiles(v) } };
  conceptosYaxis: ApexYAxis = { labels: { maxWidth: 160, style: { fontSize: '12px' } } };
  conceptosColores = [PALETA_ACENTO];

  // ── 6. Altas y bajas ──────────────────────────────────────────────
  movimientoSeries: ApexAxisChartSeries = [];
  movimientoChart: ApexChart = { ...this.base, height: 250, stacked: true };
  movimientoPlot: ApexPlotOptions = { bar: { columnWidth: '52%', borderRadius: 3 } };
  movimientoXaxis: ApexXAxis = { categories: [], axisBorder: { show: false }, axisTicks: { show: false } };
  movimientoYaxis: ApexYAxis = { labels: { formatter: (v) => String(Math.abs(Math.round(v))) } };
  movimientoColores = [PALETA_ESTADO.exito, PALETA_ESTADO.peligro];
  movimientoTooltip: ApexTooltip = { shared: true, intersect: false, y: { formatter: (v) => String(Math.abs(v)) } };
  movimientoLeyenda: ApexLegend = { position: 'top', horizontalAlign: 'right', fontSize: '12px' };
  totalAltas = 0;
  totalBajas = 0;

  ngOnInit(): void {
    this.sedeService.getAll().subscribe({
      next: (res) => {
        if (res.success) this.sedes = res.data;
      },
      // Sin sedes el filtro se queda vacío y el panel sigue con todo.
      error: () => {},
    });

    this.cargar();
  }

  // ════════ Filtros ════════

  alFiltrar(): void {
    this.cargar();
  }

  moverMes(pasos: number): void {
    const fecha = new Date(this.filtroAnio, this.filtroMes - 1 + pasos, 1);
    this.irAMes(fecha.getMonth() + 1, fecha.getFullYear());
  }

  /** Una de las doce barras de arriba: lleva a ese mes del mismo año. */
  irAMes(mes: number, anio = this.filtroAnio): void {
    if (mes === this.filtroMes && anio === this.filtroAnio) return;
    this.filtroMes = mes;
    this.filtroAnio = anio;
    this.anioDelCalendario = anio;
    this.cargar();
  }

  etiquetaMesVecino(pasos: number): string {
    const fecha = new Date(this.filtroAnio, this.filtroMes - 1 + pasos, 1);
    return `${nombreMes(fecha.getMonth() + 1)} ${fecha.getFullYear()}`;
  }

  alternarCalendario(evento: MouseEvent): void {
    // El clic se queda aquí: si sube, el «clic fuera» lo cerraría al instante.
    evento.stopPropagation();
    this.calendarioAbierto = !this.calendarioAbierto;
    if (this.calendarioAbierto) this.anioDelCalendario = this.filtroAnio;
  }

  moverAnioDelCalendario(pasos: number): void {
    this.anioDelCalendario += pasos;
  }

  elegirMes(mes: number): void {
    this.calendarioAbierto = false;
    this.irAMes(mes, this.anioDelCalendario);
  }

  esMesElegido(mes: number): boolean {
    return this.filtroMes === mes && this.filtroAnio === this.anioDelCalendario;
  }

  esMesDeHoy(mes: number): boolean {
    const hoy = new Date();
    return hoy.getMonth() + 1 === mes && hoy.getFullYear() === this.anioDelCalendario;
  }

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

  /** Vuelve al mes en curso y a todas las sedes. */
  limpiarFiltros(): void {
    const hoy = new Date();
    this.filtroSede = '';
    this.filtroMes = hoy.getMonth() + 1;
    this.filtroAnio = hoy.getFullYear();
    this.anioDelCalendario = this.filtroAnio;
    this.cargar();
  }

  get mesMovido(): boolean {
    const hoy = new Date();
    return this.filtroMes !== hoy.getMonth() + 1 || this.filtroAnio !== hoy.getFullYear();
  }

  get hayFiltros(): boolean {
    return this.mesMovido || !!this.filtroSede;
  }

  /** Lo que se está viendo, en palabras. */
  get loQueSeVe(): string {
    const sede = this.sedeElegida;
    return `${nombreMes(this.filtroMes)} ${this.filtroAnio}` + (sede ? `, ${sede.nombre}` : '');
  }

  // ════════ Acciones ════════

  irAResolver(pendiente: PendienteRrhh): void {
    // Por URL: la ruta puede traer un filtro (?filtro=...) que navigate() codificaría.
    this.router.navigateByUrl(pendiente.ruta);
  }

  irAContratos(): void {
    this.router.navigateByUrl('/inicio/contratos');
  }

  exportar(): void {
    this.exportando = true;
    this.dashboardService.exportar(this.filtroMes, this.filtroAnio, this.filtroSede || null).subscribe({
      next: (blob) => {
        guardarArchivo(blob, `Panel de control ${this.loQueSeVe.replace(', ', ' - ')}.xlsx`);
        this.exportando = false;
        this.toastService.success('Reporte descargado', `El panel de ${this.loQueSeVe}, con sus gráficos.`);
      },
      error: (err) => {
        this.exportando = false;
        this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudo generar el reporte.'));
      },
    });
  }

  // ════════ Carga ════════

  private cargar(): void {
    this.cargando = true;
    this.dashboardService.obtener(this.filtroMes, this.filtroAnio, this.filtroSede || null).subscribe({
      next: (res) => {
        if (res.success) this.pintar(res.data);
        this.cargando = false;
        this.hayDatos = true;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error(
          'No se cargó el panel',
          mensajeErrorApi(err, 'No se pudieron traer las cifras. Vuelve a intentarlo en un momento.'),
        );
      },
    });
  }

  private pintar(d: Dashboard): void {
    const r = d.resumen;
    this.pendientes = d.pendientes ?? [];
    this.vencimientos = d.contratosPorVencer ?? [];

    // ── El bloque de arriba ──
    this.nomina = r.nominaDelMes;
    this.nominaAnterior = r.nominaMesAnterior ?? 0;
    this.aportes = r.aportesColegio ?? 0;
    this.planillas = r.planillasDelMes;
    this.activos = r.empleadosActivos;
    this.altas = r.altasDelMes;
    this.porVencer = r.contratosPorVencer;

    const tendencia = d.tendenciaNomina ?? [];
    const tope = Math.max(...tendencia.map((m) => m.valor), 1);
    this.mesesDelAnio = tendencia.map((m, i) => ({
      mes: i + 1,
      etiqueta: m.etiqueta,
      valor: m.valor,
      // Un mes con planilla nunca se ve vacío: al menos una rayita.
      alto: m.valor > 0 ? Math.max(6, Math.round((m.valor / tope) * 100)) : 0,
    }));

    const f = d.firmaBoletas;
    const emitidas = f.firmadas + f.vistas + f.pendientes;
    this.boletas = {
      firmadas: f.firmadas,
      vistas: f.vistas,
      sinAbrir: f.pendientes,
      sinEmitir: Math.max(0, r.planillasDelMes - emitidas),
      base: Math.max(r.planillasDelMes, emitidas),
    };

    // ── Tendencia contra el año anterior ──
    const anterior = d.tendenciaAnterior ?? [];
    this.tendenciaSeries = [
      { name: String(this.filtroAnio), type: 'area', data: tendencia.map((m) => m.valor) },
      { name: String(this.filtroAnio - 1), type: 'line', data: anterior.map((m) => m.valor) },
    ];
    this.tendenciaXaxis = { ...this.tendenciaXaxis, categories: tendencia.map((m) => m.etiqueta) };
    const elegido = tendencia[this.filtroMes - 1]?.etiqueta;
    this.tendenciaMarca = elegido
      ? { xaxis: [{ x: elegido, strokeDashArray: 0, borderColor: PALETA_ACENTO, borderWidth: 2 }] }
      : {};
    const hasta = (lista: DatoGrafico[]) => lista.slice(0, this.filtroMes).reduce((t, m) => t + m.valor, 0);
    this.acumulado = hasta(tendencia);
    this.acumuladoAnterior = hasta(anterior);

    // ── Cascada ──
    this.armarCascada(d.composicionNomina ?? [], r.nominaDelMes, this.aportes);

    // ── Por área: el alto crece con las áreas, para que nunca se apiñen ──
    const areas = d.remuneracionPorArea ?? [];
    this.personasPorArea = areas.map((a) => a.personas ?? 0);
    this.areaSeries = [{ name: 'Pagado', data: areas.map((a) => a.valor) }];
    this.areaXaxis = { ...this.areaXaxis, categories: areas.map((a) => a.etiqueta) };
    this.areaChart = { ...this.areaChart, height: Math.max(220, areas.length * 42 + 50) };
    this.areaTooltip = {
      y: {
        formatter: (v: number, { dataPointIndex }: { dataPointIndex: number }) => {
          const n = this.personasPorArea[dataPointIndex] ?? 0;
          return `${this.enSoles(v)} entre ${n} ${n === 1 ? 'persona' : 'personas'}`;
        },
      },
    };

    // ── Cómo es el personal ──
    this.proporciones = [
      this.proporcion('Sistema de pensiones', d.sistemaPensiones ?? []),
      this.proporcion('Tipo de contrato', d.tipoContrato ?? []),
      this.proporcion('Sede', d.personalPorSede ?? []),
    ].filter((p) => p.total > 0);
    this.edades = d.edades ?? [];
    this.antiguedad = d.antiguedad ?? [];
    this.pintarPersonal();

    // ── Conceptos ──
    const tops = d.topConceptos ?? [];
    this.conceptosSeries = [{ name: 'Monto', data: tops.map((x) => x.valor) }];
    this.conceptosXaxis = { ...this.conceptosXaxis, categories: tops.map((x) => x.etiqueta) };
    this.conceptosChart = { ...this.conceptosChart, height: Math.max(200, tops.length * 40 + 50) };

    // ── Altas y bajas ──
    this.movimiento = d.movimientoPersonal ?? [];
    this.movimientoSeries = [
      { name: 'Entraron', data: this.movimiento.map((m) => m.altas) },
      { name: 'Se fueron', data: this.movimiento.map((m) => -m.bajas) },
    ];
    this.movimientoXaxis = { ...this.movimientoXaxis, categories: this.movimiento.map((m) => m.etiqueta) };
    this.totalAltas = this.movimiento.reduce((t, m) => t + m.altas, 0);
    this.totalBajas = this.movimiento.reduce((t, m) => t + m.bajas, 0);

    // ── Cumpleaños ──
    this.cumpleanos = d.cumpleanos ?? [];
    this.diaElegido = null;
    this.armarCalendario();
  }

  // ════════ El bloque de arriba ════════

  /** Cuánto cambió la nómina contra el mes anterior, en %. */
  get variacion(): number | null {
    if (!this.nominaAnterior || !this.nomina) return null;
    return Math.round(((this.nomina - this.nominaAnterior) / this.nominaAnterior) * 1000) / 10;
  }

  get variacionAbs(): number {
    return Math.abs(this.variacion ?? 0);
  }

  get nombreMesAnterior(): string {
    return nombreMes(this.filtroMes === 1 ? 12 : this.filtroMes - 1).toLowerCase();
  }

  get promedio(): number {
    return this.planillas ? this.nomina / this.planillas : 0;
  }

  /** Ancho de un tramo de la barra de boletas, en %. */
  anchoBoletas(cuantas: number): number {
    return this.boletas.base ? (cuantas / this.boletas.base) * 100 : 0;
  }

  get porcentajeFirmadas(): number {
    return this.boletas.base ? Math.round((this.boletas.firmadas / this.boletas.base) * 100) : 0;
  }

  /** La barra de boletas, en palabras, para el lector de pantalla. */
  describirBoletas(): string {
    const b = this.boletas;
    return `${b.firmadas} firmadas, ${b.vistas} vistas, ${b.sinAbrir} sin abrir y ${b.sinEmitir} sin emitir`;
  }

  /** "+S/ 4,200.00 frente a 2025": cuánto más (o menos) va pagado que el año pasado. */
  diferenciaAcumulada(): string {
    const dif = this.acumulado - this.acumuladoAnterior;
    return `${dif >= 0 ? '+' : '−'}${this.enSoles(Math.abs(dif))} frente a ${this.filtroAnio - 1}`;
  }

  describirProporcion(p: Proporcion): string {
    return `${p.titulo}: ` + p.tramos.map((t) => `${t.etiqueta} ${t.valor}`).join(', ');
  }

  pistaMes(m: MesDelAnio): string {
    const nombre = `${nombreMes(m.mes)} ${this.filtroAnio}`;
    return m.valor ? `Ver ${nombre}: ${this.enSoles(m.valor)}` : `Ver ${nombre}: sin planillas`;
  }

  // ════════ Cascada ════════

  private armarCascada(comp: DatoGrafico[], nominaReal: number, aportes: number): void {
    const valor = (nombre: string) => comp.find((c) => c.etiqueta === nombre)?.valor ?? 0;
    const basico = valor('Sueldo básico');
    const bonos = valor('Bonificaciones');
    const descuentos = valor('Descuentos');
    const adelantos = valor('Adelantos');

    if (!basico && !nominaReal) {
      this.cascada = [];
      this.cascadaSeries = [];
      return;
    }

    // El neto que cierra la cuenta es el de las planillas, el mismo de
    // arriba. Lo que no explican los cuatro conceptos (asignaciones que no
    // son bonificación, redondeos) va en su propio escalón para que cuadre.
    const otros = Math.round((nominaReal - (basico + bonos - descuentos - adelantos)) * 100) / 100;

    const pasos: PasoCascada[] = [];
    let nivel = 0;
    const paso = (etiqueta: string, corta: string, monto: number, color: string) => {
      if (Math.abs(monto) < 0.5) return;
      const desde = nivel;
      nivel += monto;
      pasos.push({ etiqueta, corta, monto, desde: Math.min(desde, nivel), hasta: Math.max(desde, nivel), color, esTotal: false });
    };
    const total = (etiqueta: string, corta: string, color: string) =>
      pasos.push({ etiqueta, corta, monto: nivel, desde: 0, hasta: nivel, color, esTotal: true });

    paso('Sueldo básico', 'Básico', basico, PALETA_MARCA.b700);
    paso('Bonificaciones', 'Bonos', bonos, PALETA_ESTADO.exito);
    if (otros > 0) paso('Otros ingresos', 'Otros', otros, PALETA_ESTADO.exito);
    paso('Descuentos', 'Desc.', -descuentos, PALETA_ESTADO.peligro);
    paso('Adelantos', 'Adel.', -adelantos, PALETA_ESTADO.aviso);
    if (otros < 0) paso('Otros descuentos', 'Otros', otros, PALETA_ESTADO.peligro);
    total('Neto a pagar', 'Neto', PALETA_MARCA.b900);
    if (aportes) {
      paso('Aporta el colegio', 'Aportes', aportes, PALETA_MARCA.b500);
      total('Costo total', 'Costo', PALETA_ACENTO);
    }

    this.cascada = pasos;
    this.cascadaSeries = [
      { name: 'Monto', data: pasos.map((p) => ({ x: p.corta, y: [p.desde, p.hasta], fillColor: p.color })) },
    ];
  }

  // ════════ Personal ════════

  private proporcion(titulo: string, datos: DatoGrafico[]): Proporcion {
    const total = datos.reduce((t, d) => t + d.valor, 0);
    return {
      titulo,
      total,
      tramos: [...datos]
        .sort((a, b) => b.valor - a.valor)
        .map((d) => ({ etiqueta: d.etiqueta, valor: d.valor, porcentaje: total ? (d.valor / total) * 100 : 0 })),
    };
  }

  cambiarVistaPersonal(vista: 'edad' | 'antiguedad'): void {
    this.vistaPersonal = vista;
    this.pintarPersonal();
  }

  private pintarPersonal(): void {
    const datos = this.vistaPersonal === 'edad' ? this.edades : this.antiguedad;
    this.personalSeries = [{ name: 'Personas', data: datos.map((d) => d.valor) }];
    this.personalXaxis = { ...this.personalXaxis, categories: datos.map((d) => d.etiqueta) };
  }

  get hayPersonal(): boolean {
    return [...this.edades, ...this.antiguedad].some((d) => d.valor > 0);
  }

  // ════════ Cumpleaños ════════

  private armarCalendario(): void {
    const hoy = new Date();
    const dias = new Date(this.filtroAnio, this.filtroMes, 0).getDate();
    // Lunes primero, como el calendario de pared.
    const hueco = (new Date(this.filtroAnio, this.filtroMes - 1, 1).getDay() + 6) % 7;
    const esEsteMes = hoy.getFullYear() === this.filtroAnio && hoy.getMonth() + 1 === this.filtroMes;
    const mesPasado =
      this.filtroAnio < hoy.getFullYear() ||
      (this.filtroAnio === hoy.getFullYear() && this.filtroMes < hoy.getMonth() + 1);
    const vacio = (): DiaCalendario => ({ dia: null, cumple: [], esHoy: false, yaPaso: false });

    const celdas: DiaCalendario[] = Array.from({ length: hueco }, vacio);
    for (let dia = 1; dia <= dias; dia++) {
      celdas.push({
        dia,
        cumple: this.cumpleanos.filter((c) => c.dia === dia),
        esHoy: esEsteMes && hoy.getDate() === dia,
        yaPaso: mesPasado || (esEsteMes && dia < hoy.getDate()),
      });
    }
    while (celdas.length % 7) celdas.push(vacio());

    this.semanas = [];
    for (let i = 0; i < celdas.length; i += 7) this.semanas.push(celdas.slice(i, i + 7));
  }

  elegirDia(dia: DiaCalendario): void {
    if (!dia.dia || !dia.cumple.length) return;
    this.diaElegido = this.diaElegido === dia.dia ? null : dia.dia;
  }

  /** La pista de un día con cumpleaños: quiénes, o cómo volver al mes. */
  pistaDia(dia: DiaCalendario): string {
    if (this.diaElegido === dia.dia) return 'Ver otra vez todo el mes';
    return dia.cumple.map((c) => c.nombre).join(', ');
  }

  get cumpleanosVisibles(): CumpleanosDelMes[] {
    return this.diaElegido ? this.cumpleanos.filter((c) => c.dia === this.diaElegido) : this.cumpleanos;
  }

  get cumpleanosHoy(): CumpleanosDelMes[] {
    return this.cumpleanos.filter((c) => c.es_hoy);
  }

  get cumpleanosPorVenir(): number {
    return this.cumpleanos.filter((c) => !c.ya_paso).length;
  }

  /** "RQ" de Rosa Quispe. */
  iniciales(nombre: string): string {
    const partes = nombre.trim().split(/\s+/);
    return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
  }

  /** "Jueves 20". */
  diaEnPalabras(dia: number): string {
    const nombre = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][
      new Date(this.filtroAnio, this.filtroMes - 1, dia).getDay()
    ];
    return `${nombre} ${dia}`;
  }

  // ════════ Formatos ════════

  fechaVence(valor: string): string {
    return fechaLegible(valor);
  }

  /** "en 12 días", "mañana", "hoy". */
  cuandoVence(dias: number): string {
    if (dias <= 0) return 'hoy';
    if (dias === 1) return 'mañana';
    return `en ${dias} días`;
  }

  /** "S/ 9,048.40" */
  enSoles(valor: number): string {
    return 'S/ ' + Number(valor).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /** "148,300" sin céntimos: en la cifra grande los céntimos estorban. */
  enSolesRedondo(valor: number): string {
    return Math.round(valor).toLocaleString('es-PE');
  }

  /** "S/ 9k" para los ejes, donde no cabe el número entero. */
  enMiles(valor: number | string): string {
    const n = Number(valor);
    if (!isFinite(n)) return String(valor);
    const abs = Math.abs(n);
    if (abs >= 1_000_000) return `S/ ${(n / 1_000_000).toFixed(1)}M`;
    return abs >= 1000 ? `S/ ${Math.round(n / 1000)}k` : `S/ ${Math.round(n)}`;
  }

  hayValores(serie: ApexAxisChartSeries): boolean {
    return serie.some((s) => (s.data as unknown[]).some((v) => Number(v) !== 0));
  }

  get pendientesConTrabajo(): PendienteRrhh[] {
    return this.pendientes.filter((p) => p.cuantos > 0);
  }
}
