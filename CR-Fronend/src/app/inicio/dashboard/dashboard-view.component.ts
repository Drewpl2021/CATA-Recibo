import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
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
  ApexGrid
} from 'ng-apexcharts';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { PlanillaService, Planilla } from '../../core/services/planilla.service';

@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, NgApexchartsModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss'
})
export class DashboardViewComponent implements OnInit {

  cargando = true;

  // ── KPIs ──────────────────────────────────────────────────────────
  kpis = [
    { label: 'Total Empleados', value: '0', sub: 'Personal Activo', icon: 'people', color: '#1e40af', bg: '#dbeafe' },
    { label: 'Nómina del Mes', value: 'S/ 0.00', sub: 'Periodo Actual', icon: 'money', color: '#15803d', bg: '#dcfce7' },
    { label: 'Boletas Registradas', value: '0', sub: 'Historial acumulado', icon: 'file', color: '#d97706', bg: '#fef3c7' },
    { label: 'Sedes y Áreas', value: '0', sub: 'Estructura Colegio', icon: 'warning', color: '#dc2626', bg: '#fee2e2' },
  ];

  // ── GRÁFICO 1: Remuneración por Área (barras horizontales) ────────
  barChartSeries: ApexAxisChartSeries = [{
    name: 'Remuneración',
    data: [0]
  }];
  barChartOptions: ApexChart = { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  barChartXaxis: ApexXAxis = {
    categories: ['General'],
    labels: { style: { colors: '#64748b', fontSize: '12px' } }
  };
  barChartYaxis: ApexYAxis = { labels: { formatter: (v) => 'S/ ' + Number(v).toFixed(0), style: { colors: '#64748b' } } };
  barChartPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } };
  barChartDataLabels: ApexDataLabels = { enabled: false };
  barChartColors = ['#1e40af'];
  barChartGrid: ApexGrid = { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } };
  barChartTooltip: ApexTooltip = { y: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) } };
  barChartFill: ApexFill = { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: ['#3b82f6'], stops: [0, 100] } };

  // ── GRÁFICO 2: Distribución por Sexo / Categoría (dona) ───────────
  donutSeries: ApexNonAxisChartSeries = [1, 1];
  donutChart: ApexChart = { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' };
  donutLabels = ['Activo', 'Otros'];
  donutColors = ['#1e40af', '#10b981'];
  donutLegend: ApexLegend = { position: 'bottom', fontSize: '13px', labels: { colors: '#475569' } };
  donutDataLabels: ApexDataLabels = { enabled: true, formatter: (val: number) => val.toFixed(1) + '%' };
  donutResponsive: ApexResponsive[] = [{ breakpoint: 480, options: { chart: { height: 220 } } }];

  // ── GRÁFICO 3: Tipo de Contrato (pastel) ─────────────────────────
  pieContrato: ApexNonAxisChartSeries = [1];
  pieContratoChart: ApexChart = { type: 'pie', height: 260, fontFamily: 'Inter, sans-serif' };
  pieContratoLabels = ['Indeterminado'];
  pieContratoColors = ['#1e40af', '#10b981', '#f59e0b', '#8b5cf6'];
  pieLegend: ApexLegend = { position: 'bottom', fontSize: '12px', labels: { colors: '#475569' } };

  // ── GRÁFICO 4: Tendencia de Nómina (línea) ───────────────────────
  lineNominaSeries: ApexAxisChartSeries = [{
    name: 'Nómina Total',
    data: [0]
  }];
  lineNominaChart: ApexChart = { type: 'area', height: 200, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', sparkline: { enabled: false } };
  lineNominaXaxis: ApexXAxis = {
    categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set'],
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
  };
  lineNominaYaxis: ApexYAxis = { labels: { formatter: (v) => 'S/ ' + Number(v).toFixed(0), style: { colors: '#94a3b8', fontSize: '11px' } } };
  lineNominaColors = ['#1e40af'];
  lineNominaStroke: ApexStroke = { curve: 'smooth', width: 3 };
  lineNominaFill: ApexFill = { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } };
  lineNominaDataLabels: ApexDataLabels = { enabled: false };
  lineNominaGrid: ApexGrid = { borderColor: '#f1f5f9', padding: { left: 10, right: 10 } };
  lineNominaTooltip: ApexTooltip = { y: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) } };

  // ── TABLA: Personal del Colegio ──────────────────────────────────
  vencimientos: Array<{ nombre: string; cargo: string; fecha: string; urgencia: string }> = [];

  // ── DISTRIBUCIÓN por Sedes ───────────────────────────────────────
  nivelSeries: ApexNonAxisChartSeries = [100];
  nivelChart: ApexChart = { type: 'radialBar', height: 260, fontFamily: 'Inter, sans-serif' };
  nivelLabels = ['Principal'];
  nivelColors = ['#1e40af', '#10b981', '#f59e0b'];
  nivelPlot: ApexPlotOptions = {
    radialBar: {
      hollow: { size: '20%' },
      track: { background: '#f1f5f9', margin: 5 },
      dataLabels: {
        show: true,
        name: { fontSize: '12px', color: '#64748b' },
        value: { fontSize: '16px', fontWeight: '700', color: '#1e293b', formatter: (v: number) => v + '%' },
        total: { show: true, label: 'Total', fontSize: '12px', color: '#94a3b8', formatter: () => '1' }
      }
    }
  };

  fechaHoy = new Date().toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

  constructor(
    private empleadoService: EmpleadoService,
    private planillaService: PlanillaService
  ) {}

  ngOnInit(): void {
    this.cargarDatosReales();
  }

  cargarDatosReales(): void {
    this.cargando = true;

    forkJoin({
      empleadosRes: this.empleadoService.getEmpleados(),
      planillasRes: this.planillaService.getPlanillas()
    }).subscribe({
      next: ({ empleadosRes, planillasRes }) => {
        const empleados = empleadosRes.success ? empleadosRes.data : [];
        const planillas = planillasRes.success ? planillasRes.data : [];

        this.procesarMetricas(empleados, planillas);
        this.cargando = false;
      },
      error: (err) => {
        console.error('Error cargando métricas reales para el dashboard:', err);
        this.cargando = false;
      }
    });
  }

  private procesarMetricas(empleados: Empleado[], planillas: Planilla[]): void {
    // 1. KPI Total Empleados
    const totalEmp = empleados.length;
    this.kpis[0].value = totalEmp.toString();
    this.kpis[0].sub = `${empleados.filter(e => e.estado?.toLowerCase() === 'activo').length} activos en el colegio`;

    // 2. Planillas y Nómina
    let maxMes = 0;
    let maxAnio = 0;
    for (const p of planillas) {
      if (p.anio > maxAnio || (p.anio === maxAnio && p.mes > maxMes)) {
        maxAnio = p.anio;
        maxMes = p.mes;
      }
    }

    const mesesNombres = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
    const planillasUltimoMes = planillas.filter(p => p.mes === maxMes && p.anio === maxAnio);
    const totalNomina = planillasUltimoMes.reduce((acc, curr) => acc + Number(curr.total || 0), 0);

    this.kpis[1].value = 'S/ ' + totalNomina.toLocaleString('es-PE', { minimumFractionDigits: 2 });
    this.kpis[1].sub = maxMes ? `${mesesNombres[maxMes]} ${maxAnio}` : 'Sin planillas';

    // 3. Total Boletas Emitidas
    this.kpis[2].value = planillas.length.toString();
    this.kpis[2].sub = `${planillasUltimoMes.length} en ${mesesNombres[maxMes] || 'el periodo'}`;

    // 4. Áreas y Sedes
    const areasUnicas = new Set(empleados.map(e => e.area?.nombre).filter(Boolean));
    this.kpis[3].value = areasUnicas.size.toString();
    this.kpis[3].sub = `${areasUnicas.size} áreas activas`;

    // ── GRÁFICO 1: Remuneración por Área ──
    const areaMap: Record<string, number> = {};
    for (const e of empleados) {
      const areaNom = e.area?.nombre || 'Sin Área';
      const sueldo = Number(e.sueldo_base || 0);
      areaMap[areaNom] = (areaMap[areaNom] || 0) + sueldo;
    }

    const areaCategories = Object.keys(areaMap);
    const areaData = Object.values(areaMap);

    if (areaCategories.length > 0) {
      this.barChartXaxis = {
        categories: areaCategories,
        labels: { style: { colors: '#64748b', fontSize: '12px' } }
      };
      this.barChartSeries = [{
        name: 'Presupuesto Base',
        data: areaData
      }];
    }

    // ── GRÁFICO 3: Tipo de Contrato ──
    const contratoMap: Record<string, number> = {};
    for (const e of empleados) {
      const tipo = e.tipo_contrato ? e.tipo_contrato.toUpperCase() : 'NO ESPECIFICADO';
      contratoMap[tipo] = (contratoMap[tipo] || 0) + 1;
    }

    const contratoLabels = Object.keys(contratoMap);
    const contratoSeries = Object.values(contratoMap);

    if (contratoLabels.length > 0) {
      this.pieContratoLabels = contratoLabels;
      this.pieContrato = contratoSeries;
    }

    // ── GRÁFICO 4: Tendencia de Nómina Histórica ──
    const mesesAgrupados: Record<number, number> = {};
    for (let m = 1; m <= 12; m++) mesesAgrupados[m] = 0;

    for (const p of planillas) {
      if (p.mes >= 1 && p.mes <= 12) {
        mesesAgrupados[p.mes] += Number(p.total || 0);
      }
    }

    const dataNomina = Object.values(mesesAgrupados);
    this.lineNominaSeries = [{
      name: 'Nómina Pagada',
      data: dataNomina
    }];
    this.lineNominaXaxis = {
      categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'],
      labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
    };

    // ── TABLA: Personal Educativo ──
    this.vencimientos = empleados.map(e => ({
      nombre: `${e.nombre} ${e.apellido}`,
      cargo: e.cargo?.nombre || 'Docente',
      fecha: e.fecha_ingreso ? new Date(e.fecha_ingreso).toLocaleDateString('es-PE') : '—',
      urgencia: e.estado?.toLowerCase() === 'activo' ? 'normal' : 'urgente'
    }));
  }
}
