import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
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
import { DashboardService, DashboardData } from '../../core/services/dashboard.service';
import { VacacionService, Vacacion } from '../../core/services/vacacion.service';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { PlanillaService } from '../../core/services/planilla.service';

export type DashboardViewTab = 'todos' | 'nomina' | 'personal' | 'vacaciones';
export type VistaRemuneracion = 'area' | 'sede';
export type ModoEvolucionAnual = 'general' | 'sede' | 'area';

@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule, FormsModule, NgApexchartsModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss'
})
export class DashboardViewComponent implements OnInit {

  cargando = true;
  actualizando = false;

  // ── Slicers / Filtros Power BI ────────────────────────────────────
  filtroMes: number = new Date().getMonth() + 1;
  filtroAnio: number = new Date().getFullYear();
  activeTab: DashboardViewTab = 'todos';

  // Toggle de visualización de Evolución Anual: General vs Sede vs Área
  modoEvolucionAnual: ModoEvolucionAnual = 'general';
  todasLasPlanillasAnio: any[] = [];

  aniosDisponibles: number[] = [];
  meses = [
    { num: 1, nombre: 'Enero' }, { num: 2, nombre: 'Febrero' },
    { num: 3, nombre: 'Marzo' }, { num: 4, nombre: 'Abril' },
    { num: 5, nombre: 'Mayo' }, { num: 6, nombre: 'Junio' },
    { num: 7, nombre: 'Julio' }, { num: 8, nombre: 'Agosto' },
    { num: 9, nombre: 'Setiembre' }, { num: 10, nombre: 'Octubre' },
    { num: 11, nombre: 'Noviembre' }, { num: 12, nombre: 'Diciembre' }
  ];

  // ── Métricas Calculadas en Vivo ──────────────────────────────────
  rawDashboardData: DashboardData | null = null;
  todosLosEmpleados: Empleado[] = [];
  todasLasVacaciones: Vacacion[] = [];

  variacionNomina: number | null = null;
  variacionPositiva = true;
  porcentajeCumplimientoFirmas = 0;
  totalBoletasPeriodo = 0;
  boletasFirmadasCount = 0;
  docentesCount = 0;
  administrativosCount = 0;
  vacacionesPendientes: Vacacion[] = [];
  personalEnVacacionesHoy: Vacacion[] = [];

  // ── KPIs Power BI ────────────────────────────────────────────────
  kpiNomina = { valor: 'S/ 0.00', sub: 'Periodo Actual', badge: '' };
  kpiPersonal = { total: 0, docentes: 0, admin: 0, altas: 0 };
  kpiFirmas = { porcentaje: 0, firmadas: 0, total: 0, pendientes: 0 };
  kpiVacaciones = { pendientes: 0, enVacaciones: 0 };

  // ── GRÁFICO 1: Remuneración por Área / Sede (Barras Horizontales) ──
  barChartSeries: ApexAxisChartSeries = [{ name: 'Masa Salarial', data: [0] }];
  barChartOptions: ApexChart = {
    type: 'bar',
    height: 280,
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif'
  };
  barChartLegend: ApexLegend = { show: false };
  barChartXaxis: ApexXAxis = {
    categories: ['Sin datos'],
    labels: {
      formatter: (val) => val ? 'S/ ' + Number(val).toLocaleString('es-PE') : 'S/ 0',
      style: { colors: '#64748b', fontSize: '11px', fontWeight: 500 }
    }
  };
  barChartYaxis: ApexYAxis = {
    labels: {
      style: { colors: '#1e293b', fontSize: '13px', fontWeight: 600 }
    }
  };
  barChartPlot: ApexPlotOptions = {
    bar: { horizontal: true, borderRadius: 6, barHeight: '52%', distributed: true }
  };
  barChartDataLabels: ApexDataLabels = {
    enabled: true,
    textAnchor: 'start',
    formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }),
    style: { colors: ['#0f172a'], fontSize: '11px', fontWeight: '700' },
    offsetX: 10
  };
  barChartColors = ['#2563eb', '#3b82f6', '#0ea5e9', '#06b6d4', '#10b981', '#f59e0b', '#8b5cf6'];
  barChartGrid: ApexGrid = { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } };
  barChartTooltip: ApexTooltip = {
    theme: 'dark',
    y: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }
  };

  // ── GRÁFICO 2: Cumplimiento de Firmas (Gauge RadialBar) ───────────
  radialFirmasSeries: ApexNonAxisChartSeries = [0];
  radialFirmasChart: ApexChart = { type: 'radialBar', height: 260, fontFamily: 'Inter, sans-serif' };
  radialFirmasColors = ['#10b981'];
  radialFirmasPlot: ApexPlotOptions = {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: { size: '68%', background: 'transparent' },
      track: { background: '#f1f5f9', strokeWidth: '100%' },
      dataLabels: {
        name: { fontSize: '13px', color: '#64748b', offsetY: -10, show: true },
        value: {
          fontSize: '28px',
          fontWeight: '800',
          color: '#0f172a',
          offsetY: 6,
          formatter: (v: number) => v + '%'
        }
      }
    }
  };

  // ── GRÁFICO 3: Sistema de Pensiones (Donut) ──────────────────────
  donutSeries: ApexNonAxisChartSeries = [1];
  donutChart: ApexChart = { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' };
  donutLabels = ['Sin datos'];
  donutColors = ['#1e40af', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
  donutLegend: ApexLegend = { position: 'bottom', fontSize: '12px', labels: { colors: '#475569' } };
  donutDataLabels: ApexDataLabels = { enabled: true, formatter: (val: number) => val.toFixed(0) + '%' };
  donutResponsive: ApexResponsive[] = [{ breakpoint: 480, options: { chart: { height: 220 } } }];

  // ── GRÁFICO 4: Tendencia de Nómina Anual (Área Smooth con Zoom) ──
  lineNominaSeries: ApexAxisChartSeries = [{ name: 'Nómina Total', data: [0] }];
  lineNominaChart: ApexChart = {
    type: 'area',
    height: 260,
    toolbar: {
      show: true,
      tools: {
        download: false,
        selection: false, // Desactivado (marcado en rojo)
        zoom: false,      // Desactivado (marcado en rojo)
        zoomin: true,     // Botón '+'
        zoomout: true,    // Botón '-'
        pan: false,       // Desactivado (marcado en rojo)
        reset: true       // Botón '↺' para restablecer zoom
      }
    },
    zoom: {
      enabled: true,
      type: 'x',
      autoScaleYaxis: true
    },
    fontFamily: 'Inter, sans-serif'
  };
  lineNominaLegend: ApexLegend = {
    show: false,
    position: 'top',
    horizontalAlign: 'right',
    fontSize: '12px',
    labels: { colors: '#64748b' }
  };
  lineNominaXaxis: ApexXAxis = {
    categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'],
    labels: { style: { colors: '#94a3b8', fontSize: '11px' } }
  };
  lineNominaYaxis: ApexYAxis = {
    labels: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE'), style: { colors: '#94a3b8', fontSize: '11px' } }
  };
  lineNominaColors = ['#2563eb'];
  lineNominaStroke: ApexStroke = { curve: 'smooth', width: 3 };
  lineNominaFill: ApexFill = {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 95, 100] }
  };
  lineNominaDataLabels: ApexDataLabels = { enabled: false };
  lineNominaGrid: ApexGrid = { borderColor: '#f1f5f9', padding: { left: 10, right: 10 } };
  lineNominaTooltip: ApexTooltip = {
    theme: 'dark',
    y: { formatter: (v) => 'S/ ' + Number(v).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }
  };

  // ── GRÁFICO 5: Modalidad Contractual (Donut) ─────────────────────
  pieContrato: ApexNonAxisChartSeries = [1];
  pieContratoChart: ApexChart = { type: 'donut', height: 230, fontFamily: 'Inter, sans-serif' };
  pieContratoLabels = ['Sin datos'];
  pieContratoColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
  pieLegend: ApexLegend = { position: 'bottom', fontSize: '11px', labels: { colors: '#475569' } };

  // ── Contratos por Vencer (Smart Table) ───────────────────────────
  vencimientos: Array<{ nombre: string; cargo: string; fecha: string; dias: number; urgencia: string }> = [];

  // ── Drill-Down Modal ─────────────────────────────────────────────
  showDrilldownModal = false;
  drilldownTitulo = '';
  drilldownEmpleados: Empleado[] = [];

  fechaHoy = new Date().toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  ultimaSincronizacion = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

  constructor(
    private dashboardService: DashboardService,
    private vacacionService: VacacionService,
    private empleadoService: EmpleadoService,
    private planillaService: PlanillaService
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 3; i <= currentYear + 1; i++) {
      this.aniosDisponibles.push(i);
    }
    this.cargarTodoElDashboard();
  }

  cargarTodoElDashboard(): void {
    this.cargando = true;
    this.actualizando = true;

    forkJoin({
      dashRes: this.dashboardService.getDashboard(this.filtroMes, this.filtroAnio),
      empleadosRes: this.empleadoService.getEmpleados(),
      vacacionesRes: this.vacacionService.getVacaciones(),
      planillasRes: this.planillaService.getPlanillas({ anio: this.filtroAnio })
    }).subscribe({
      next: ({ dashRes, empleadosRes, vacacionesRes, planillasRes }) => {
        this.rawDashboardData = dashRes.success ? dashRes.data : null;
        this.todosLosEmpleados = empleadosRes.success ? empleadosRes.data : [];
        this.todasLasVacaciones = vacacionesRes.success ? vacacionesRes.data : [];
        this.todasLasPlanillasAnio = planillasRes.success ? planillasRes.data : [];

        this.procesarPowerBIData();
        this.actualizarGraficoRemuneracionArea();
        this.actualizarGraficoEvolucionAnual();

        this.cargando = false;
        this.actualizando = false;
        this.ultimaSincronizacion = new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      },
      error: (err) => {
        console.error('Error al sincronizar datos del dashboard:', err);
        this.cargando = false;
        this.actualizando = false;
      }
    });
  }

  private procesarPowerBIData(): void {
    if (!this.rawDashboardData) return;
    const data = this.rawDashboardData;
    const mesNombre = this.meses.find(m => m.num === Number(data.periodo.mes))?.nombre || '';

    // ── 1. Desglose de Empleados (Docentes vs Administrativos) ──
    this.docentesCount = this.todosLosEmpleados.filter(e => {
      const c = (e.cargo?.nombre || '').toLowerCase();
      const a = (e.area?.nombre || '').toLowerCase();
      return c.includes('docente') || c.includes('profesor') || a.includes('docencia') || a.includes('primaria') || a.includes('secundaria') || a.includes('inicial');
    }).length;
    this.administrativosCount = Math.max(0, this.todosLosEmpleados.length - this.docentesCount);

    // ── 2. Variación de Nómina con Mes Anterior ──
    if (data.tendenciaNomina && data.tendenciaNomina.length >= 12) {
      const mesIdx = Number(data.periodo.mes) - 1;
      const actual = data.tendenciaNomina[mesIdx]?.valor ?? 0;
      const anterior = mesIdx > 0 ? (data.tendenciaNomina[mesIdx - 1]?.valor ?? 0) : 0;
      if (anterior > 0) {
        const diff = ((actual - anterior) / anterior) * 100;
        this.variacionNomina = Number(diff.toFixed(1));
        this.variacionPositiva = diff >= 0;
      } else {
        this.variacionNomina = null;
      }
    }

    // ── 3. Cumplimiento de Boletas del Mes ──
    const { firmadas = 0, vistas = 0, pendientes = 0 } = data.firmaBoletas || {};
    this.totalBoletasPeriodo = firmadas + vistas + pendientes;
    this.boletasFirmadasCount = firmadas;
    this.porcentajeCumplimientoFirmas = this.totalBoletasPeriodo > 0
      ? Math.round((firmadas / this.totalBoletasPeriodo) * 100)
      : 0;

    // ── 4. Vacaciones y Ausencias en Vivo ──
    const hoyStr = new Date().toISOString().split('T')[0];
    this.personalEnVacacionesHoy = this.todasLasVacaciones.filter(v =>
      v.estado === 'aprobado' && v.fecha_inicio <= hoyStr && v.fecha_fin >= hoyStr
    );
    this.vacacionesPendientes = this.todasLasVacaciones.filter(v => v.estado === 'pendiente');

    // ── 5. Actualizar Tarjetas KPI ──
    this.kpiNomina = {
      valor: 'S/ ' + Number(data.resumen.nominaDelMes ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2 }),
      sub: `${mesNombre} ${data.periodo.anio}`,
      badge: this.variacionNomina !== null
        ? `${this.variacionPositiva ? '▲ +' : '▼ '}${Math.abs(this.variacionNomina)}% vs mes ant.`
        : 'Periodo base'
    };

    this.kpiPersonal = {
      total: data.resumen.empleadosActivos ?? this.todosLosEmpleados.length,
      docentes: this.docentesCount,
      admin: this.administrativosCount,
      altas: data.resumen.altasDelMes ?? 0
    };

    this.kpiFirmas = {
      porcentaje: this.porcentajeCumplimientoFirmas,
      firmadas,
      total: this.totalBoletasPeriodo,
      pendientes: pendientes + vistas
    };

    this.kpiVacaciones = {
      pendientes: this.vacacionesPendientes.length,
      enVacaciones: this.personalEnVacacionesHoy.length
    };

    // ── 6. Gráfico 2: Radial Gauge de Cumplimiento ──
    this.radialFirmasSeries = [this.porcentajeCumplimientoFirmas];
    this.radialFirmasColors = [this.porcentajeCumplimientoFirmas >= 80 ? '#10b981' : this.porcentajeCumplimientoFirmas >= 50 ? '#3b82f6' : '#f59e0b'];

    // ── 7. Gráfico 3: Sistema de Pensiones ──
    if (data.sistemaPensiones && data.sistemaPensiones.length > 0) {
      this.donutLabels = data.sistemaPensiones.map(s => s.etiqueta);
      this.donutSeries = data.sistemaPensiones.map(s => s.valor);
    }

    // ── 8. Gráfico 4: Tendencia Anual ──
    if (data.tendenciaNomina && data.tendenciaNomina.length > 0) {
      this.lineNominaXaxis = {
        categories: data.tendenciaNomina.map(t => t.etiqueta),
        labels: { style: { colors: '#94a3b8', fontSize: '11px', fontWeight: 500 } }
      };
      this.lineNominaSeries = [{
        name: 'Nómina Pagada',
        data: data.tendenciaNomina.map(t => t.valor)
      }];
    }

    // ── 9. Gráfico 5: Tipo de Contrato ──
    if (data.tipoContrato && data.tipoContrato.length > 0) {
      this.pieContratoLabels = data.tipoContrato.map(c => c.etiqueta);
      this.pieContrato = data.tipoContrato.map(c => c.valor);
    } else {
      this.pieContratoLabels = ['Sin contratos registrados'];
      this.pieContrato = [1];
    }

    // ── 10. Contratos por Vencer ──
    this.vencimientos = data.contratosPorVencer || [];
  }

  // ── Gráfico 1: Remuneración por Área de Trabajo ───────────────────
  actualizarGraficoRemuneracionArea(): void {
    const datos = this.rawDashboardData?.remuneracionPorArea || [];

    if (datos.length > 0) {
      this.barChartXaxis = {
        categories: datos.map(a => a.etiqueta),
        labels: {
          formatter: (val) => val ? 'S/ ' + Number(val).toLocaleString('es-PE') : 'S/ 0',
          style: { colors: '#64748b', fontSize: '11px', fontWeight: 500 }
        }
      };
      this.barChartSeries = [{
        name: 'Masa Salarial',
        data: datos.map(a => a.valor)
      }];
    } else {
      this.barChartXaxis = { categories: ['Sin registros'], labels: { style: { colors: '#64748b' } } };
      this.barChartSeries = [{ name: 'Masa Salarial', data: [0] }];
    }
  }

  // ── Gráfico 4: Evolución Anual (General vs Por Sede vs Por Área) ──
  cambiarModoEvolucion(modo: ModoEvolucionAnual): void {
    this.modoEvolucionAnual = modo;
    this.actualizarGraficoEvolucionAnual();
  }

  actualizarGraficoEvolucionAnual(): void {
    const mesesLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
    this.lineNominaXaxis = {
      categories: mesesLabels,
      labels: { style: { colors: '#94a3b8', fontSize: '11px', fontWeight: 500 } }
    };

    if (this.modoEvolucionAnual === 'general') {
      // 1. General: Total de nómina del colegio mes a mes
      const datosMeses = new Array(12).fill(0);
      if (this.rawDashboardData?.tendenciaNomina && this.rawDashboardData.tendenciaNomina.length > 0) {
        this.rawDashboardData.tendenciaNomina.forEach((t, i) => {
          if (i < 12) datosMeses[i] = t.valor;
        });
      } else {
        this.todasLasPlanillasAnio.forEach(p => {
          const m = Number(p.mes);
          if (m >= 1 && m <= 12) {
            datosMeses[m - 1] += Number(p.total || 0);
          }
        });
      }

      this.lineNominaSeries = [{
        name: 'Nómina Total',
        data: datosMeses.map(v => Number(v.toFixed(2)))
      }];
      this.lineNominaColors = ['#2563eb'];
      this.lineNominaLegend = { show: false };

    } else if (this.modoEvolucionAnual === 'sede') {
      // 2. Por Sede: Desglose mes a mes de cada sede física
      const sedesMap: Record<string, number[]> = {};

      // Inicializar sedes existentes en los empleados
      this.todosLosEmpleados.forEach(e => {
        const sedeNom = e.sede?.nombre || 'Otras Sedes';
        if (!sedesMap[sedeNom]) {
          sedesMap[sedeNom] = new Array(12).fill(0);
        }
      });

      // Sumar planillas emitidas en el año según la sede de cada trabajador
      this.todasLasPlanillasAnio.forEach(p => {
        const emp = this.todosLosEmpleados.find(e => e.id === p.empleado_id);
        const sedeNom = emp?.sede?.nombre || 'Otras Sedes';
        if (!sedesMap[sedeNom]) {
          sedesMap[sedeNom] = new Array(12).fill(0);
        }
        const m = Number(p.mes);
        if (m >= 1 && m <= 12) {
          sedesMap[sedeNom][m - 1] += Number(p.total || 0);
        }
      });

      const seriesGeneradas = Object.entries(sedesMap)
        .filter(([_, arr]) => arr.some(v => v > 0) || Object.keys(sedesMap).length <= 3)
        .map(([nombre, arr]) => ({
          name: `Sede ${nombre}`,
          data: arr.map(v => Number(v.toFixed(2)))
        }));

      this.lineNominaSeries = seriesGeneradas.length > 0
        ? seriesGeneradas
        : [{ name: 'Sin registros', data: new Array(12).fill(0) }];
      this.lineNominaColors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'];
      this.lineNominaLegend = {
        show: true,
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        labels: { colors: '#64748b' }
      };

    } else if (this.modoEvolucionAnual === 'area') {
      // 3. Por Área: Desglose mes a mes de cada área funcional
      const areasMap: Record<string, number[]> = {};

      this.todosLosEmpleados.forEach(e => {
        const areaNom = e.area?.nombre || 'General';
        if (!areasMap[areaNom]) {
          areasMap[areaNom] = new Array(12).fill(0);
        }
      });

      this.todasLasPlanillasAnio.forEach(p => {
        const emp = this.todosLosEmpleados.find(e => e.id === p.empleado_id);
        const areaNom = emp?.area?.nombre || 'General';
        if (!areasMap[areaNom]) {
          areasMap[areaNom] = new Array(12).fill(0);
        }
        const m = Number(p.mes);
        if (m >= 1 && m <= 12) {
          areasMap[areaNom][m - 1] += Number(p.total || 0);
        }
      });

      const seriesGeneradas = Object.entries(areasMap)
        .filter(([_, arr]) => arr.some(v => v > 0) || Object.keys(areasMap).length <= 4)
        .map(([nombre, arr]) => ({
          name: nombre,
          data: arr.map(v => Number(v.toFixed(2)))
        }));

      this.lineNominaSeries = seriesGeneradas.length > 0
        ? seriesGeneradas
        : [{ name: 'Sin registros', data: new Array(12).fill(0) }];
      this.lineNominaColors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899'];
      this.lineNominaLegend = {
        show: true,
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        labels: { colors: '#64748b' }
      };
    }
  }

  // ── Restablecer Zoom del Gráfico Anual ───────────────────────────
  resetearZoomNomina(): void {
    this.actualizarGraficoEvolucionAnual();
  }

  // ── Acciones y Drill-Downs ────────────────────────────────────────
  cambiarTab(tab: DashboardViewTab): void {
    this.activeTab = tab;
  }

  abrirDrilldownPorItem(etiqueta: string): void {
    this.abrirDrilldownPorArea(etiqueta);
  }

  abrirDrilldownPorArea(nombreArea: string): void {
    this.drilldownTitulo = `Personal en Área: ${nombreArea}`;
    this.drilldownEmpleados = this.todosLosEmpleados.filter(e =>
      (e.area?.nombre || 'Sin área').toLowerCase() === nombreArea.toLowerCase()
    );
    this.showDrilldownModal = true;
  }

  abrirDrilldownPorSede(nombreSede: string): void {
    this.drilldownTitulo = `Personal en Sede: ${nombreSede}`;
    this.drilldownEmpleados = this.todosLosEmpleados.filter(e =>
      (e.sede?.nombre || 'Sin sede').toLowerCase() === nombreSede.toLowerCase()
    );
    this.showDrilldownModal = true;
  }

  abrirDrilldownGeneral(): void {
    this.drilldownTitulo = 'Personal Activo del Colegio';
    this.drilldownEmpleados = this.todosLosEmpleados.filter(e => e.estado?.toLowerCase() === 'activo');
    this.showDrilldownModal = true;
  }

  cerrarDrilldown(): void {
    this.showDrilldownModal = false;
  }

  imprimirReporte(): void {
    window.print();
  }

  getNombreMesActual(): string {
    return this.meses.find(m => m.num === Number(this.filtroMes))?.nombre || '';
  }
}
