import { Component, HostListener, OnDestroy, OnInit, inject } from '@angular/core';
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
  ApexNonAxisChartSeries,
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
  PALETA_SERIES,
  PALETA_SOBRE_ACENTO,
  MESES_OPCIONES,
  nombreMes,
  fechaEnPalabras,
} from '../../../shared/constants';

/** Una barra de las doce de la banda. */
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

/** Un tramo del anillo de boletas. */
interface ArcoAnillo {
  clase: string;
  largo: number;
  inicio: number;
}

/** Una dona de «Cómo es el personal». */
interface Dona {
  titulo: string;
  series: ApexNonAxisChartSeries;
  etiquetas: string[];
  total: number;
}

/**
 * Panel de Control de RR.HH.
 *
 * Todas las cifras salen de GET /dashboard en una sola respuesta, y todas
 * respetan el mes y la sede elegidos.
 *
 * Arriba, la banda del mes: la nómina (que sube contando al llegar), los
 * doce meses del año como barras que llevan a su mes, y el anillo de las
 * boletas firmadas. Debajo, cuatro cifras con su mini gráfico y el detalle:
 * la comparación con el año anterior, los cumpleaños en un calendario, de
 * dónde sale el neto, el mapa de lo pagado por área y cómo es el personal.
 */
@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, FormsModule, NgApexchartsModule, IconComponent, PistaDirective],
  templateUrl: './dashboard-view.component.html',
})
export class DashboardViewComponent implements OnInit, OnDestroy {
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

  // ── La banda del mes ──────────────────────────────────────────────
  nomina = 0;
  /** Lo que se ve en la cifra grande mientras sube contando. */
  nominaMostrada = 0;
  private animacion = 0;
  nominaAnterior = 0;
  aportes = 0;
  planillas = 0;
  activos = 0;
  altas = 0;
  porVencer = 0;
  mesesDelAnio: MesDelAnio[] = [];
  /** Boletas del mes: cuántas en cada paso, sobre las planillas armadas. */
  boletas = { firmadas: 0, vistas: 0, sinAbrir: 0, sinEmitir: 0, base: 0 };
  /** El anillo de boletas: radio 54 → circunferencia 2πr. */
  readonly circunferencia = 2 * Math.PI * 54;
  arcos: ArcoAnillo[] = [];

  // ── Las cuatro cifras con su mini gráfico ─────────────────────────
  private chispa: ApexChart = { type: 'area', height: 46, sparkline: { enabled: true }, animations: { enabled: false } };
  chispaNominaChart: ApexChart = { ...this.chispa };
  chispaNominaSeries: ApexAxisChartSeries = [];
  chispaNominaColores = [PALETA_MARCA.b500];
  chispaAltasChart: ApexChart = { ...this.chispa, type: 'bar' };
  chispaAltasSeries: ApexAxisChartSeries = [];
  chispaAltasColores = [PALETA_ESTADO.exito];
  chispaAltasPlot: ApexPlotOptions = { bar: { columnWidth: '60%', borderRadius: 2 } };
  chispaStroke: ApexStroke = { curve: 'monotoneCubic', width: 2 };
  chispaFill: ApexFill = { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.02 } };
  chispaTooltip: ApexTooltip = { enabled: false };

  // ── Cumpleaños ────────────────────────────────────────────────────
  semanas: DiaCalendario[][] = [];
  diasSemana = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
  /** Un día marcado en el calendario: la lista se queda solo con ese día. */
  diaElegido: number | null = null;

  // ── Opciones comunes de ApexCharts ────────────────────────────────
  private base: ApexChart = {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    zoom: { enabled: false },
    animations: { enabled: true, speed: 500 },
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
  /** Las barras en degradado: del institucional al claro. */
  degradadoBarra: ApexFill = {
    type: 'gradient',
    gradient: { shade: 'light', type: 'horizontal', gradientToColors: [PALETA_MARCA.b500], stops: [0, 100] },
  };

  // ── 1. La nómina mes a mes, contra el año anterior ────────────────
  tendenciaSeries: ApexAxisChartSeries = [];
  tendenciaChart: ApexChart = {
    ...this.base,
    type: 'line',
    height: 380,
    dropShadow: { enabled: true, top: 6, left: 0, blur: 8, opacity: 0.18, color: PALETA_MARCA.b700 },
  };
  tendenciaColores = [PALETA_MARCA.b700, PALETA_NEUTRO];
  tendenciaStroke: ApexStroke = { curve: 'monotoneCubic', width: [3.5, 2], dashArray: [0, 6] };
  tendenciaFill: ApexFill = {
    type: ['gradient', 'solid'],
    gradient: { shadeIntensity: 1, opacityFrom: 0.42, opacityTo: 0.02, stops: [0, 95] },
  };
  tendenciaMarkers: ApexMarkers = { size: 0, hover: { size: 6 } };
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
  cascadaPlot: ApexPlotOptions = { bar: { horizontal: false, columnWidth: '62%', borderRadius: 6 } };
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

  // ── 3. Lo pagado por área: un mapa de bloques ─────────────────────
  //
  // Cada área es un rectángulo del tamaño de lo que cuesta: se ve de golpe
  // que las planas docentes son el grueso, cosa que con trece barras en
  // fila había que leer.
  areaSeries: ApexAxisChartSeries = [];
  areaChart: ApexChart = { ...this.base, type: 'treemap', height: 360 };
  areaPlot: ApexPlotOptions = {
    treemap: { enableShades: true, shadeIntensity: 0.55, reverseNegativeShade: true, distributed: false },
  };
  areaColores = [PALETA_MARCA.b700];
  areaEtiquetas: ApexDataLabels = {
    enabled: true,
    style: { fontSize: '13px', fontWeight: 700 },
    formatter: (texto: string, op: { value: number }) => [texto, this.enMiles(op.value)] as unknown as string,
    offsetY: -2,
  };
  areaTooltip: ApexTooltip = {};
  private personasPorArea: number[] = [];

  // ── 4. Cómo es el personal: tres donas con el total al centro ─────
  donas: Dona[] = [];
  donaChart: ApexChart = { ...this.base, type: 'donut', height: 250 };
  donaColores = [...PALETA_SERIES];
  donaLeyenda: ApexLegend = { position: 'bottom', fontSize: '12px', itemMargin: { horizontal: 6, vertical: 2 } };
  donaStroke: ApexStroke = { width: 0 };
  donaEtiquetas: ApexDataLabels = {
    enabled: true,
    formatter: (v: number) => (v >= 6 ? `${Math.round(v)}%` : ''),
    dropShadow: { enabled: false },
    style: { fontSize: '11px', fontWeight: 700 },
  };
  donaPlot: ApexPlotOptions = {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          name: { fontSize: '12px', offsetY: 18 },
          value: { fontSize: '26px', fontWeight: 800, offsetY: -14 },
          total: { show: true, showAlways: true, label: 'personas', fontSize: '12px' },
        },
      },
    },
  };

  // ── 5. Edad o antigüedad ──────────────────────────────────────────
  vistaPersonal: 'edad' | 'antiguedad' = 'edad';
  private edades: DatoGrafico[] = [];
  private antiguedad: DatoGrafico[] = [];
  personalSeries: ApexAxisChartSeries = [];
  personalChart: ApexChart = { ...this.base, height: 260 };
  personalPlot: ApexPlotOptions = {
    bar: { borderRadius: 6, columnWidth: '58%', distributed: true, dataLabels: { position: 'top' } },
  };
  personalXaxis: ApexXAxis = {
    categories: [],
    labels: { rotate: 0, hideOverlappingLabels: false },
    axisBorder: { show: false },
    axisTicks: { show: false },
  };
  personalYaxis: ApexYAxis = { labels: { formatter: (v) => String(Math.round(v)) } };
  personalEtiquetas: ApexDataLabels = { enabled: true, offsetY: -22, style: { fontSize: '13px', fontWeight: 800 } };
  /** De claro a profundo: a más años, más intenso; el último tramo en dorado. */
  personalColores = [PALETA_MARCA.b500, PALETA_MARCA.b600, PALETA_MARCA.b700, PALETA_MARCA.b900, PALETA_ACENTO];

  // ── 6. Los conceptos que más pesan ────────────────────────────────
  conceptosSeries: ApexAxisChartSeries = [];
  conceptosChart: ApexChart = { ...this.base, height: 260 };
  conceptosPlot: ApexPlotOptions = {
    bar: { horizontal: true, borderRadius: 5, barHeight: '62%', dataLabels: { position: 'top' } },
  };
  conceptosXaxis: ApexXAxis = { categories: [], tickAmount: 3, labels: { formatter: (v) => this.enMiles(v) } };
  conceptosYaxis: ApexYAxis = { labels: { maxWidth: 160, style: { fontSize: '12px' } } };
  conceptosColores = [PALETA_ACENTO];
  conceptosFill: ApexFill = {
    type: 'gradient',
    gradient: { shade: 'light', type: 'horizontal', gradientToColors: [PALETA_ESTADO.aviso], stops: [0, 100] },
  };
  conceptosEtiquetas: ApexDataLabels = {
    enabled: true,
    offsetX: 34,
    formatter: (v: number) => this.enMiles(v),
    style: { fontSize: '11px', fontWeight: 700 },
  };
  /** En el celular el nombre se acorta y el eje lleva menos marcas. */
  angostoResponsive: ApexResponsive[] = [
    { breakpoint: 640, options: { yaxis: { labels: { maxWidth: 110 } }, xaxis: { tickAmount: 2 } } },
  ];

  // ── 7. Altas y bajas ──────────────────────────────────────────────
  movimientoSeries: ApexAxisChartSeries = [];
  movimientoChart: ApexChart = { ...this.base, height: 270, stacked: true };
  movimientoPlot: ApexPlotOptions = { bar: { columnWidth: '48%', borderRadius: 4 } };
  movimientoXaxis: ApexXAxis = { categories: [], axisBorder: { show: false }, axisTicks: { show: false } };
  movimientoYaxis: ApexYAxis = { labels: { formatter: (v) => String(Math.abs(Math.round(v))) } };
  movimientoColores = [PALETA_ESTADO.exito, PALETA_ESTADO.peligro];
  movimientoTooltip: ApexTooltip = { shared: true, intersect: false, y: { formatter: (v) => String(Math.abs(v)) } };
  movimientoLeyenda: ApexLegend = { position: 'top', horizontalAlign: 'right', fontSize: '12px' };
  movimientoEtiquetas: ApexDataLabels = {
    enabled: true,
    formatter: (v: number) => (v ? String(Math.abs(v)) : ''),
    style: { fontSize: '11px', fontWeight: 700 },
  };
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

  ngOnDestroy(): void {
    cancelAnimationFrame(this.animacion);
  }

  // ════════ Filtros ════════

  alFiltrar(): void {
    this.cargar();
  }

  moverMes(pasos: number): void {
    const fecha = new Date(this.filtroAnio, this.filtroMes - 1 + pasos, 1);
    this.irAMes(fecha.getMonth() + 1, fecha.getFullYear());
  }

  /** Una de las doce barras de la banda: lleva a ese mes del mismo año. */
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

    // ── La banda ──
    this.nominaAnterior = r.nominaMesAnterior ?? 0;
    this.aportes = r.aportesColegio ?? 0;
    this.planillas = r.planillasDelMes;
    this.activos = r.empleadosActivos;
    this.altas = r.altasDelMes;
    this.porVencer = r.contratosPorVencer;
    this.contarHasta(r.nominaDelMes);

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
    this.armarAnillo();

    // ── Las cuatro cifras ──
    this.chispaNominaSeries = [{ name: 'Nómina', data: tendencia.map((m) => m.valor) }];

    // ── Tendencia contra el año anterior ──
    const anterior = d.tendenciaAnterior ?? [];
    this.tendenciaSeries = [
      { name: String(this.filtroAnio), type: 'area', data: tendencia.map((m) => m.valor) },
      { name: String(this.filtroAnio - 1), type: 'line', data: anterior.map((m) => m.valor) },
    ];
    this.tendenciaXaxis = { ...this.tendenciaXaxis, categories: tendencia.map((m) => m.etiqueta) };
    const elegido = tendencia[this.filtroMes - 1];
    this.tendenciaMarca = elegido
      ? {
          xaxis: [{ x: elegido.etiqueta, strokeDashArray: 0, borderColor: PALETA_ACENTO, borderWidth: 2 }],
          points: elegido.valor
            ? [
                {
                  x: elegido.etiqueta,
                  y: elegido.valor,
                  marker: { size: 7, fillColor: PALETA_ACENTO, strokeColor: PALETA_MARCA.b100, strokeWidth: 3 },
                  label: {
                    text: this.enMiles(elegido.valor),
                    borderColor: PALETA_ACENTO,
                    offsetY: -6,
                    style: { background: PALETA_ACENTO, color: PALETA_SOBRE_ACENTO, fontSize: '12px', fontWeight: 800, padding: { left: 8, right: 8, top: 3, bottom: 4 } },
                  },
                },
              ]
            : [],
        }
      : {};
    const hasta = (lista: DatoGrafico[]) => lista.slice(0, this.filtroMes).reduce((t, m) => t + m.valor, 0);
    this.acumulado = hasta(tendencia);
    this.acumuladoAnterior = hasta(anterior);

    // ── Cascada ──
    this.armarCascada(d.composicionNomina ?? [], r.nominaDelMes, this.aportes);

    // ── Por área ──
    // Las siete más grandes con nombre; el resto junto en «Otras áreas»:
    // trece bloques dejaban los chicos sin poder leerse.
    const todas = d.remuneracionPorArea ?? [];
    const resto = todas.slice(7);
    const areas = resto.length > 1
      ? [
          ...todas.slice(0, 7),
          {
            etiqueta: `Otras ${resto.length} áreas`,
            valor: resto.reduce((t, a) => t + a.valor, 0),
            personas: resto.reduce((t, a) => t + (a.personas ?? 0), 0),
          },
        ]
      : todas;
    this.personasPorArea = areas.map((a) => a.personas ?? 0);
    this.areaSeries = [{ name: 'Pagado', data: areas.map((a) => ({ x: a.etiqueta, y: a.valor })) }];
    this.areaTooltip = {
      y: {
        formatter: (v: number, { dataPointIndex }: { dataPointIndex: number }) => {
          const n = this.personasPorArea[dataPointIndex] ?? 0;
          return `${this.enSoles(v)} entre ${n} ${n === 1 ? 'persona' : 'personas'}`;
        },
      },
    };

    // ── Cómo es el personal ──
    this.donas = [
      this.dona('Sistema de pensiones', d.sistemaPensiones ?? []),
      this.dona('Tipo de contrato', d.tipoContrato ?? []),
      this.dona('Sede', d.personalPorSede ?? []),
    ];
    this.edades = d.edades ?? [];
    this.antiguedad = d.antiguedad ?? [];
    this.pintarPersonal();

    // ── Conceptos ──
    const tops = d.topConceptos ?? [];
    this.conceptosSeries = [{ name: 'Monto', data: tops.map((x) => x.valor) }];
    this.conceptosXaxis = { ...this.conceptosXaxis, categories: tops.map((x) => x.etiqueta) };
    this.conceptosChart = { ...this.conceptosChart, height: Math.max(220, tops.length * 44 + 50) };

    // ── Altas y bajas ──
    this.movimiento = d.movimientoPersonal ?? [];
    this.movimientoSeries = [
      { name: 'Entraron', data: this.movimiento.map((m) => m.altas) },
      { name: 'Se fueron', data: this.movimiento.map((m) => -m.bajas) },
    ];
    this.movimientoXaxis = { ...this.movimientoXaxis, categories: this.movimiento.map((m) => m.etiqueta) };
    this.totalAltas = this.movimiento.reduce((t, m) => t + m.altas, 0);
    this.totalBajas = this.movimiento.reduce((t, m) => t + m.bajas, 0);
    this.chispaAltasSeries = [{ name: 'Entraron', data: this.movimiento.map((m) => m.altas) }];

    // ── Cumpleaños ──
    this.cumpleanos = d.cumpleanos ?? [];
    this.diaElegido = null;
    this.armarCalendario();
  }

  /**
   * La cifra grande sube contando hasta la nómina del mes: el único
   * movimiento que la pantalla hace sola, y es para llevar la vista ahí.
   * Con «reducir movimiento» del sistema, aparece de una.
   */
  private contarHasta(destino: number): void {
    cancelAnimationFrame(this.animacion);
    const desde = this.nominaMostrada;
    this.nomina = destino;
    const quieto = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (quieto || desde === destino) {
      this.nominaMostrada = destino;
      return;
    }
    const inicio = performance.now();
    const duracion = 900;
    const paso = (ahora: number) => {
      const t = Math.min(1, (ahora - inicio) / duracion);
      const suave = 1 - Math.pow(1 - t, 3);
      this.nominaMostrada = desde + (destino - desde) * suave;
      if (t < 1) this.animacion = requestAnimationFrame(paso);
    };
    this.animacion = requestAnimationFrame(paso);
  }

  // ════════ La banda ════════

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

  get porcentajeFirmadas(): number {
    return this.boletas.base ? Math.round((this.boletas.firmadas / this.boletas.base) * 100) : 0;
  }

  /** Lo que pone el colegio, como parte del costo total (para la barrita). */
  get parteAportes(): number {
    const total = this.nomina + this.aportes;
    return total ? (this.aportes / total) * 100 : 0;
  }

  /** Los tres arcos del anillo, uno detrás de otro, con un respiro entre ellos. */
  private armarAnillo(): void {
    const b = this.boletas;
    const tramos: [string, number][] = [
      ['anillo__arco--firmadas', b.firmadas],
      ['anillo__arco--vistas', b.vistas],
      ['anillo__arco--sin-abrir', b.sinAbrir],
    ];
    const respiro = tramos.filter(([, n]) => n > 0).length > 1 ? 3 : 0;
    let inicio = 0;
    this.arcos = [];
    for (const [clase, n] of tramos) {
      if (!n || !b.base) continue;
      const largo = (n / b.base) * this.circunferencia;
      this.arcos.push({ clase, largo: Math.max(0, largo - respiro), inicio });
      inicio += largo;
    }
  }

  describirBoletas(): string {
    const b = this.boletas;
    return `${b.firmadas} firmadas, ${b.vistas} vistas, ${b.sinAbrir} sin abrir y ${b.sinEmitir} sin emitir`;
  }

  /** "+S/ 4,200.00 frente a 2025": cuánto más (o menos) va pagado que el año pasado. */
  diferenciaAcumulada(): string {
    const dif = this.acumulado - this.acumuladoAnterior;
    return `${dif >= 0 ? '+' : '−'}${this.enSoles(Math.abs(dif))} frente a ${this.filtroAnio - 1}`;
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

  private dona(titulo: string, datos: DatoGrafico[]): Dona {
    const orden = [...datos].sort((a, b) => b.valor - a.valor);
    return {
      titulo,
      series: orden.map((d) => d.valor),
      etiquetas: orden.map((d) => d.etiqueta),
      total: orden.reduce((t, d) => t + d.valor, 0),
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

  /** El siguiente que cumple (sin contar los de hoy), para la cuenta regresiva. */
  get proximoCumple(): { persona: CumpleanosDelMes; dias: number } | null {
    const hoy = new Date();
    const esEsteMes = hoy.getFullYear() === this.filtroAnio && hoy.getMonth() + 1 === this.filtroMes;
    if (!esEsteMes) return null;
    const persona = this.cumpleanos.find((c) => !c.ya_paso && !c.es_hoy);
    return persona ? { persona, dias: persona.dia - hoy.getDate() } : null;
  }

  /** "RQ" de Rosa Quispe. */
  iniciales(nombre: string): string {
    const partes = nombre.trim().split(/\s+/);
    return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? partes[partes.length - 1][0] : '')).toUpperCase();
  }

  /** Un color de la paleta por persona, siempre el mismo para el mismo nombre. */
  colorDe(nombre: string): string {
    // Suma simple de las letras: con un hash de multiplicar por 31 casi todos
    // caían en el mismo color (31 deja el mismo resto entre 4).
    let h = 0;
    for (const letra of nombre) h += letra.charCodeAt(0);
    return 'avatar--' + (h % 4);
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
    return serie.some((s) =>
      (s.data as unknown[]).some((v) => {
        const n = typeof v === 'object' && v !== null ? Number((v as { y: unknown }).y) : Number(v);
        return n !== 0 && !isNaN(n);
      }),
    );
  }

  get pendientesConTrabajo(): PendienteRrhh[] {
    return this.pendientes.filter((p) => p.cuantos > 0);
  }
}
