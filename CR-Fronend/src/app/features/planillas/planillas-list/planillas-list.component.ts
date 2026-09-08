import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
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
} from '../../../core/services';
import {
  Area,
  Cargo,
  Empleado,
  GeneracionMasivaPlanilla,
  Periodo,
  Planilla,
  Sede,
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
  ];

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  /**
   * Todo lo que llena un desplegable se pide UNA vez y completo, porque a un
   * <select> no se le pagina: empleados y periodos para los filtros de
   * arriba, y áreas/cargos/sedes para acotar el grupo al generar. Las
   * planillas, en cambio, vienen por páginas.
   */
  private cargarCatalogos(): void {
    forkJoin({
      empleados: this.empleadoService.getAll(),
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
    // Se propone el periodo que se está viendo en la lista, y de sus meses
    // el que coincida con el filtro; si no coincide ninguno, el primero.
    const propuesto = this.filtroPeriodo || this.periodos[0]?.id || '';
    const mesFiltro = Number(this.filtroMes);
    const anioFiltro = Number(this.filtroAnio);
    const deseado =
      mesFiltro && anioFiltro ? `${anioFiltro}-${String(mesFiltro).padStart(2, '0')}` : '';

    this.formGenerar.reset({ periodo_id: propuesto, mesAnio: deseado });
    this.alCambiarPeriodo();
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

  generar(): void {
    if (this.formGenerar.invalid) {
      this.formGenerar.markAllAsTouched();
      this.toastService.error(
        'Falta un dato',
        'Elige el periodo y el mes que se va a generar.'
      );
      return;
    }

    if (this.alcance === 'elegidos' && !this.empleadosElegidos.length) {
      this.toastService.error('Falta elegir', 'Marca al menos un empleado, o cambia a "todo el personal".');
      return;
    }

    const { periodo_id, mesAnio } = this.formGenerar.getRawValue();
    const [anio, mes] = String(mesAnio).split('-').map(Number);
    this.generando = true;
    this.resultadoGeneracion = null;

    // Sin lista, el backend alcanza a todo el personal activo.
    const soloEstos = this.alcance === 'elegidos' ? this.empleadosElegidos : undefined;

    this.periodoService.generarPlanillaMasiva(periodo_id!, Number(mes), Number(anio), soloEstos).subscribe({
      next: (res) => {
        this.generando = false;
        if (!res.success) return;
        this.resultadoGeneracion = res.data;
        const { generadas, omitidas } = res.data.resumen;
        this.toastService.resultadoMasivo({
          hechas: generadas,
          omitidas,
          exito: 'Planillas generadas',
          nada: 'No se generó ninguna planilla',
          cosas: 'planilla(s)',
          motivo: 'esos empleados ya tenían planilla de ese mes, o no tienen sueldo básico',
        });
        // La lista se pone al día con lo recién creado.
        this.filtroMes = Number(mes);
        this.filtroAnio = Number(anio);
        this.cargar();
      },
      error: (err) => {
        this.generando = false;
        // 422 si el mes cae fuera del rango del periodo.
        this.toastService.error('No se generó', mensajeErrorApi(err, 'No se pudieron generar las planillas.'));
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
