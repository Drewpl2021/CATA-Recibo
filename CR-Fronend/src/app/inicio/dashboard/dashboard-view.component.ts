import { Component, OnInit } from '@angular/core';
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
  ApexGrid
} from 'ng-apexcharts';

@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, NgApexchartsModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss'
})
export class DashboardViewComponent implements OnInit {

  // ── KPIs ──────────────────────────────────────────────────────────
  kpis = [
    { label: 'Total Empleados', value: '127', sub: '+3 este mes', icon: 'people', color: '#1e40af', bg: '#dbeafe' },
    { label: 'Nómina del Mes', value: 'S/ 148,300', sub: 'Julio 2026', icon: 'money', color: '#15803d', bg: '#dcfce7' },
    { label: 'Boletas Emitidas', value: '91%', sub: '116 / 127', icon: 'file', color: '#d97706', bg: '#fef3c7' },
    { label: 'Contratos por Vencer', value: '5', sub: 'Próximos 30 días', icon: 'warning', color: '#dc2626', bg: '#fee2e2' },
  ];

  // ── GRÁFICO 1: Remuneración por Área (barras horizontales) ────────
  barChartSeries: ApexAxisChartSeries = [{
    name: 'Remuneración',
    data: [72400, 38200, 18500, 11000, 8200]
  }];
  barChartOptions: ApexChart = { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' };
  barChartXaxis: ApexXAxis = {
    categories: ['Docentes', 'Administrativos', 'Inicial', 'Mantenimiento', 'Directivos'],
    labels: { style: { colors: '#64748b', fontSize: '12px' } }
  };
  barChartYaxis: ApexYAxis = { labels: { formatter: (v) => 'S/ ' + (v / 1000).toFixed(0) + 'k', style: { colors: '#64748b' } } };
  barChartPlot: ApexPlotOptions = { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } };
  barChartDataLabels: ApexDataLabels = { enabled: false };
  barChartColors = ['#1e40af'];
  barChartGrid: ApexGrid = { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } };
  barChartTooltip: ApexTooltip = { y: { formatter: (v) => 'S/ ' + v.toLocaleString('es-PE') } };
  barChartFill: ApexFill = { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: ['#3b82f6'], stops: [0, 100] } };

  // ── GRÁFICO 2: Distribución por Sexo (dona) ───────────────────────
  donutSeries: ApexNonAxisChartSeries = [68, 59];
  donutChart: ApexChart = { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' };
  donutLabels = ['Masculino', 'Femenino'];
  donutColors = ['#1e40af', '#f59e0b'];
  donutLegend: ApexLegend = { position: 'bottom', fontSize: '13px', labels: { colors: '#475569' } };
  donutDataLabels: ApexDataLabels = { enabled: true, formatter: (val: number) => val.toFixed(1) + '%' };
  donutResponsive: ApexResponsive[] = [{ breakpoint: 480, options: { chart: { height: 220 } } }];

  // ── GRÁFICO 3: Tipo de Contrato (pastel) ─────────────────────────
  pieContrato: ApexNonAxisChartSeries = [58, 45, 24];
  pieContratoChart: ApexChart = { type: 'pie', height: 260, fontFamily: 'Inter, sans-serif' };
  pieContratoLabels = ['Plazo Fijo', 'Indeterminado', 'Honorarios'];
  pieContratoColors = ['#1e40af', '#10b981', '#f59e0b'];
  pieLegend: ApexLegend = { position: 'bottom', fontSize: '12px', labels: { colors: '#475569' } };

  // ── GRÁFICO 4: Tendencia de Nómina (línea) ───────────────────────
  lineNominaSeries: ApexAxisChartSeries = [{
    name: 'Nómina Total',
    data: [132000, 135400, 131200, 138700, 142100, 145800, 148300]
  }];
  lineNominaChart: ApexChart = { type: 'area', height: 200, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', sparkline: { enabled: false } };
  lineNominaXaxis: ApexXAxis = {
    categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
  };
  lineNominaYaxis: ApexYAxis = { labels: { formatter: (v) => 'S/ ' + (v / 1000).toFixed(0) + 'k', style: { colors: '#94a3b8', fontSize: '11px' } } };
  lineNominaColors = ['#1e40af'];
  lineNominaStroke: ApexStroke = { curve: 'smooth', width: 3 };
  lineNominaFill: ApexFill = { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } };
  lineNominaDataLabels: ApexDataLabels = { enabled: false };
  lineNominaGrid: ApexGrid = { borderColor: '#f1f5f9', padding: { left: 10, right: 10 } };
  lineNominaTooltip: ApexTooltip = { y: { formatter: (v) => 'S/ ' + v.toLocaleString('es-PE') } };

  // ── TABLA: Próximos Vencimientos ─────────────────────────────────
  vencimientos = [
    { nombre: 'Carlos Mendoza R.', cargo: 'Docente Historia', fecha: '10 Jul 2026', urgencia: 'urgente' },
    { nombre: 'Ana Suárez P.', cargo: 'Auxiliar Inicial', fecha: '22 Jul 2026', urgencia: 'proximo' },
    { nombre: 'Luis Torres A.', cargo: 'Mantenimiento', fecha: '30 Jul 2026', urgencia: 'proximo' },
    { nombre: 'Rosa Huanca V.', cargo: 'Docente Primaria', fecha: '05 Ago 2026', urgencia: 'normal' },
    { nombre: 'Jorge Quispe M.', cargo: 'Secretaría', fecha: '15 Ago 2026', urgencia: 'normal' },
  ];

  // ── DISTRIBUCIÓN por Nivel Educativo ─────────────────────────────
  nivelSeries: ApexNonAxisChartSeries = [48, 42, 37];
  nivelChart: ApexChart = { type: 'radialBar', height: 260, fontFamily: 'Inter, sans-serif' };
  nivelLabels = ['Secundaria', 'Primaria', 'Inicial'];
  nivelColors = ['#1e40af', '#10b981', '#f59e0b'];
  nivelPlot: ApexPlotOptions = {
    radialBar: {
      hollow: { size: '20%' },
      track: { background: '#f1f5f9', margin: 5 },
      dataLabels: {
        show: true,
        name: { fontSize: '12px', color: '#64748b' },
        value: { fontSize: '16px', fontWeight: '700', color: '#1e293b', formatter: (v: number) => v + '%' },
        total: { show: true, label: 'Total', fontSize: '12px', color: '#94a3b8', formatter: () => '127' }
      }
    }
  };

  // Fecha actual para el header
  fechaHoy = new Date().toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

  ngOnInit(): void {}
}
