import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
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

import { DashboardService, ToastService } from '../../../core/services';
import { ContratoPorVencer, Dashboard, DatoGrafico } from '../../../core/models';
import { fechaLegible, mensajeErrorApi } from '../../../core/utils';
import {
  PALETA_SERIES,
  PALETA_SERIE_UNICA,
  PALETA_DEGRADADO_BARRA,
  PALETA_ESTADO,
  nombreMes,
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
  imports: [CommonModule, NgApexchartsModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss',
})
export class DashboardViewComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private toastService = inject(ToastService);

  cargando = true;
  etiquetaPeriodo = '';

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

  fechaHoy = new Date().toLocaleDateString('es-PE', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
  });

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;

    this.dashboardService.obtener().subscribe({
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
