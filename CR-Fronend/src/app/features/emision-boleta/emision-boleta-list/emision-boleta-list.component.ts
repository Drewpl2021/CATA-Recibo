import { inject, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AreaService, CargoService, SedeService } from '../../../core/services';
import { EmpleadoService } from '../../../core/services';
import { Empleado } from '../../../core/models';
import { BoletaService } from '../../../core/services';
import { PlanillaService } from '../../../core/services';
import { PayrollDetalleService } from '../../../core/services';
import { Planilla } from '../../../core/models';
import { ToastService } from '../../../core/services';
import { ConfirmService } from '../../../core/services';
import { Observable, of, map, switchMap, forkJoin } from 'rxjs';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { MESES_OPCIONES } from '../../../shared/constants';

export interface FormularioBoleta {
  remuneracionBasica: number | null;
  bonificacionCargo: number | null;
  asignacionFamiliar: number | null;
  vacacionesTruncas: number | null;
  gratificacionesFiestas: number | null;
  bonifExtraordTemporal: number | null;
  otrosConceptosSubsidio: number | null;
  compensacionTiempoServicios: number | null;
  bonificacion: number | null;
  onp13: number | null;
  sppFondoPensiones: number | null;
  sppPrimaSeguro: number | null;
  sppComision: number | null;
  ir5taCategoria: number | null;
  descuentoAlimentacion: number | null;
  descuentoBazar: number | null;
  descuentoAutorizadoDiezmo: number | null;
  descuentoOtros: number | null;
  descuentoEscolaridad: number | null;
  essalud9: number | null;
  sctr: number | null;
  adelanto: number | null;
  ciudad: string;
  fechaEmision: string;
  mes: number;
  anio: number;
}

@Component({
  selector: 'app-emision-boleta-list',
  standalone: true,
  imports: [CommonModule, FormsModule, PistaDirective, PageHeaderComponent, DataTableComponent, FiltrosComponent],
  templateUrl: './emision-boleta-list.component.html',
  styleUrl: './emision-boleta-list.component.scss'
})
export class EmisionBoletaListComponent implements OnInit {
  empleados: Empleado[] = [];
  cargandoEmpleados = false;

  /** El corte y el buscador los hace el backend; acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  totalEmpleados = 0;

  /**
   * De los trabajadores QUE SE ESTÁN VIENDO, cuáles ya tienen planilla de
   * este mes. Se pregunta solo por los ids de la página.
   */
  empleadosEditados = new Set<string>();

  /**
   * Cuantas planillas hay en todo el mes. Es distinto de empleadosEditados:
   * ese conjunto es de la página, y este número decide si el mes está sin
   * empezar (y toca enseñar el aviso de "generar la planilla del mes").
   */
  planillasDelMes = 0;

  /**
   * Cuántos trabajadores hay en total, sin contar lo que filtre la tabla.
   *
   * Hace falta aparte porque las cifras de arriba miden el AVANCE del mes
   * ("18 armadas, 4 sin armar") y eso no puede depender del filtro: al
   * pedir "a quién le falta" la lista queda en 4 y "sin armar" habría
   * salido 0, que es justo lo contrario de lo que pasa.
   */
  totalDelColegio = 0;

  /**
   * Si ya llegó la cuenta de las planillas del mes.
   *
   * El aviso de "este mes está sin empezar" no puede salir antes: la lista de
   * trabajadores y el conteo llegan por separado, y en ese hueco de medio
   * segundo planillasDelMes todavía vale 0 y el aviso parpadeaba en meses que
   * sí tenían boletas.
   */
  conteoListo = false;

  columnas: ColumnaTabla<Empleado>[] = [
    {
      campo: 'nombre', header: 'Nombres y apellidos', ancho: '28%',
      formatear: (_v, e) => `${e.nombre} ${e.apellido}`,
    },
    { campo: 'dni', header: 'DNI', ancho: '12%' },
    { campo: 'cargo.nombre', header: 'Cargo', ancho: '20%' },
    { campo: 'area.nombre', header: 'Área', ancho: '20%' },
    {
      // Se llamaba "Boleta del mes" y enseñaba si tenía PLANILLA. No es lo
      // mismo: la boleta es el papel que sale después, y decir que la de
      // alguien está "sin armar" cuando nadie le armó su planilla hacía
      // creer que el sistema ya le había hecho una.
      campo: 'id', header: 'Planilla del mes', tipo: 'badge', ancho: '15%',
      formatear: (_v, e) => (this.empleadosEditados.has(e.id) ? 'Armada' : 'Le falta'),
      badgeSeveridad: (_v, e) => (this.empleadosEditados.has(e.id) ? 'success' : 'warning'),
    },
  ];

  /**
   * Las cifras de arriba: cuánta gente hay y cuántas boletas van armadas de
   * ese mes. Las dos salen del backend contando TODO, no la página.
   */
  get cifras(): CifraCabecera[] {
    return [
      {
        icono: 'people',
        valor: this.totalEmpleados,
        // Con un filtro puesto ya no son "los trabajadores" sino los que
        // quedaron en la lista, y conviene que la cifra lo diga.
        etiqueta: this.hayFiltros ? 'En la lista' : 'Trabajadores',
        tono: 'brand',
      },
      { icono: 'receipt', valor: this.planillasDelMes, etiqueta: 'Con planilla', tono: 'success' },
      { icono: 'clock', valor: this.sinPlanilla, etiqueta: 'Les falta', tono: 'warning' },
    ];
  }

  get hayFiltros(): boolean {
    // La vista de arriba (con/sin planilla) no cuenta como filtro: si
    // contara, la cifra diría siempre "En la lista".
    return Object.keys(this.filtros).some((clave) => clave !== 'planilla');
  }

  accionesFila: AccionPersonalizada<Empleado>[] = [
    { id: 'editar', titulo: 'Revisar y editar los conceptos de su boleta', icono: 'money', etiqueta: 'Editar' },
  ];

  // Modal state
  showModal = false;
  empleadoSeleccionado: Empleado | null = null;
  formulario!: FormularioBoleta;
  planillaActual: Planilla | null = null;
  private _formularioOriginal: string = '';

  generandoPDF = false;
  generandoMasivo = false;

  // Global Period State
  mesGlobal: number = new Date().getMonth() + 1;
  anioGlobal: number = new Date().getFullYear();
  aniosDisponibles: number[] = [];

  // Mass emission modal
  private confirmService = inject(ConfirmService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);

  mesesDisponibles = MESES_OPCIONES.map((m) => ({ num: m.value, nombre: m.label }));

  /*
   * Los filtros de la tabla.
   *
   * Acá la pregunta mientras se emite es siempre la misma: "¿a quién le
   * falta?". Con 150 trabajadores y la mitad ya emitida, eso se buscaba
   * fila por fila mirando la columna de estado.
   *
   * Los de boleta y planilla son del MES que se está armando, así que
   * viajan con el mes y el año de arriba.
   */
  /**
   * Se abre con los que YA tienen su planilla del mes.
   *
   * Son los únicos que pueden tener boleta: la boleta sale de la planilla.
   * Con la lista completa, un colegio de 100 trabajadores enseñaba diez
   * páginas de gente sin nada que emitir.
   */
  filtros: ValoresFiltro = { planilla: 'con' };

  /** Cuál de los tres chips está marcado. '' es "todos". */
  get vista(): string {
    return this.filtros['planilla'] ?? '';
  }

  verSolo(valor: 'con' | 'sin' | ''): void {
    const filtros = { ...this.filtros };

    if (valor) {
      filtros['planilla'] = valor;
    } else {
      delete filtros['planilla'];
    }

    this.filtros = filtros;
    this.pagina = 0;
    this.cargarEmpleados();
  }

  /** A cuántos les falta la planilla del mes. */
  get sinPlanilla(): number {
    return Math.max(this.totalDelColegio - this.planillasDelMes, 0);
  }

  /** Lo que dice la tabla cuando no hay filas, según lo que se esté viendo. */
  get mensajeTabla(): string {
    if (this.vista === 'con') {
      return 'Todavía no hay ninguna planilla armada de este mes. Mira a quiénes les falta y ármalas.';
    }
    if (this.vista === 'sin') {
      return 'No le falta la planilla a nadie: están todas armadas.';
    }

    return 'No se encontraron trabajadores.';
  }

  camposFiltro: CampoFiltro[] = [
    {
      clave: 'boleta', etiqueta: 'Boleta del mes', tipo: 'opciones', vacio: 'No importa',
      opciones: [
        { valor: 'sin', etiqueta: 'Le falta' },
        { valor: 'sin_firmar', etiqueta: 'Emitida, sin firmar' },
        { valor: 'con', etiqueta: 'Ya emitida' },
      ],
    },
    { clave: 'sede_id', etiqueta: 'Sede', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'area_id', etiqueta: 'Área', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
    {
      clave: 'tipo_contrato', etiqueta: 'Tipo de contrato', tipo: 'opciones', vacio: 'Todos',
      opciones: [
        { valor: 'indeterminado', etiqueta: 'Indeterminado' },
        { valor: 'plazo_fijo', etiqueta: 'Plazo fijo' },
        { valor: 'suplencia', etiqueta: 'Suplencia' },
        { valor: 'practicas', etiqueta: 'Prácticas' },
      ],
    },
    {
      clave: 'sistema_pensiones', etiqueta: 'Pensión', tipo: 'opciones', vacio: 'Todas',
      opciones: [
        { valor: 'ONP', etiqueta: 'ONP' },
        { valor: 'AFP', etiqueta: 'AFP' },
        { valor: 'ninguno', etiqueta: 'No aporta' },
      ],
    },
    {
      clave: 'sin_sueldo', etiqueta: 'Sin sueldo puesto', tipo: 'si-no',
      ayuda: 'A quien no tiene sueldo no se le puede armar la boleta.',
    },
  ];

  alFiltrar(): void {
    this.pagina = 0;
    this.cargarEmpleados();
  }

  private catalogosListos = false;

  /** Sedes, áreas y cargos: solo si se abre el panel. */
  cargarCatalogos(): void {
    if (this.catalogosListos) return;
    this.catalogosListos = true;

    forkJoin({
      sedes: this.sedeService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
    }).subscribe({
      next: ({ sedes, areas, cargos }) => {
        this.ponerOpciones('sede_id', sedes.data);
        this.ponerOpciones('area_id', areas.data);
        this.ponerOpciones('cargo_id', cargos.data);
      },
      error: () => {
        this.catalogosListos = false;
        this.toastService.error('Filtros', 'No se pudieron cargar las sedes, áreas y cargos.');
      },
    });
  }

  private ponerOpciones(clave: string, lista: { id: string; nombre: string }[]): void {
    const campo = this.camposFiltro.find((c) => c.clave === clave);
    if (!campo) return;

    campo.opciones = (lista ?? [])
      .map((x) => ({ valor: x.id, etiqueta: x.nombre }))
      .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
  }

  constructor(
    private empleadoService: EmpleadoService,
    private boletaService: BoletaService,
    private planillaService: PlanillaService,
    private detalleService: PayrollDetalleService,
    private toastService: ToastService
  ) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    for (let i = currentYear - 5; i <= currentYear + 5; i++) {
      this.aniosDisponibles.push(i);
    }
    this.formulario = this.getFormularioVacio();
    this.cargarEmpleados();

    // Una fila basta: lo que interesa es el total que manda el backend.
    this.empleadoService.getPagina({ page: 0, size: 1 }).subscribe({
      next: (res) => { if (res.success) this.totalDelColegio = res.data.totalElements; },
      error: () => {},
    });
  }

  cargarEmpleados(): void {
    this.cargandoEmpleados = true;
    this.empleadoService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        // El mes que se está armando: los filtros de boleta y planilla son
        // de ESE periodo, no del trabajador.
        mes: this.mesGlobal,
        anio: this.anioGlobal,
        ...this.filtros,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.empleados = res.data.content;
            this.totalEmpleados = res.data.totalElements;
            this.cargarEstadoBoletas();
            return;
          }
          this.cargandoEmpleados = false;
        },
        error: (err) => {
          this.toastService.error('Error', err?.error?.message || 'No se pudo cargar la lista de trabajadores.');
          this.cargandoEmpleados = false;
        },
      });
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargarEmpleados();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargarEmpleados();
  }

  onGlobalPeriodChange(): void {
    this.pagina = 0;
    this.cargarEmpleados();
  }

  /**
   * Cuáles de los trabajadores en pantalla ya tienen planilla del mes.
   *
   * Se pregunta por los ids de la página (?empleado_ids=), no por todo el
   * colegio: antes esto se traía las 150 planillas del mes para marcar diez
   * filas. Y aparte, un conteo suelto de cuántas hay en total, que es lo que
   * decide si el mes está sin empezar.
   */
  cargarEstadoBoletas(): void {
    const ids = this.empleados.map((e) => e.id);
    if (ids.length === 0) {
      this.empleadosEditados.clear();
      this.cargandoEmpleados = false;
      return;
    }

    this.planillaService
      .getPagina({
        mes: this.mesGlobal,
        anio: this.anioGlobal,
        empleado_ids: ids.join(','),
        page: 0,
        size: ids.length,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.empleadosEditados = new Set(res.data.content.map((p) => p.empleado_id));
          }
          this.cargandoEmpleados = false;
        },
        error: () => {
          // Si falla, las filas salen como "sin armar": se sigue pudiendo editar.
          this.empleadosEditados.clear();
          this.cargandoEmpleados = false;
        },
      });

    this.conteoListo = false;
    this.planillaService
      .getPagina({ mes: this.mesGlobal, anio: this.anioGlobal, page: 0, size: 1 })
      .subscribe({
        next: (res) => {
          if (res.success) this.planillasDelMes = res.data.totalElements;
          this.conteoListo = true;
        },
        error: () => {
          this.planillasDelMes = 0;
          this.conteoListo = true;
        },
      });
  }

  nombreMes(num: number): string {
    return this.mesesDisponibles.find(m => m.num === num)?.nombre || '';
  }

  abrirModal(empleado: Empleado): void {
    this.empleadoSeleccionado = empleado;
    this.formulario = this.getFormularioVacio();
    this.showModal = true;
    document.body.style.overflow = 'hidden';
    
    // Cargar la planilla del empleado para el periodo GLOBAL seleccionado
    this.cargarPlanillaDelEmpleado(empleado.id, this.mesGlobal, this.anioGlobal, empleado);
  }

  cargarPlanillaDelEmpleado(empleadoId: string, mes: number, anio: number, empleado: Empleado): void {
    this.planillaService.listar({ empleado_id: empleadoId, mes, anio }).subscribe({
      next: (res) => {
        if (res.success && res.data.length > 0) {
          this.planillaActual = res.data[0];

          // El básico sale de la planilla; el resto, de sus líneas de concepto.
          // Laravel manda los decimales como string ("2500.00") — se convierten aqui.
          this.formulario.remuneracionBasica = this.planillaActual.sueldo_base != null ? Number(this.planillaActual.sueldo_base) : null;

          this.cargarConceptosEnFormulario(this.planillaActual.id!);
        } else {
          this.planillaActual = null;
          // Si no existe, cargar el sueldo_base inicial del empleado
          this.formulario.remuneracionBasica = empleado.sueldo_base ?? null;
          this.formulario.bonificacion = null;
          this.formulario.descuentoOtros = null;
          this.formulario.bonificacionCargo = null;
          this.formulario.vacacionesTruncas = null;
          this.formulario.bonifExtraordTemporal = null;
          this.formulario.otrosConceptosSubsidio = null;
          this.formulario.compensacionTiempoServicios = null;
          this.formulario.ir5taCategoria = null;
          this.formulario.descuentoAlimentacion = null;
          this.formulario.descuentoBazar = null;
          this.formulario.descuentoAutorizadoDiezmo = null;
          this.formulario.descuentoEscolaridad = null;
          this.formulario.adelanto = null;
        }

        // Recalcular montos dinámicos/previsionales
        this.recalcularMontosDinamicos();
        this._formularioOriginal = JSON.stringify(this.formulario);
      },
      error: (err) => {
        console.error('Error cargando planilla del empleado', err);
        // Fallback simple
        this.formulario.remuneracionBasica = empleado.sueldo_base ?? null;
        this.recalcularMontosDinamicos();
        this._formularioOriginal = JSON.stringify(this.formulario);
      }
    });
  }

  onPeriodoChange(): void {
    if (this.empleadoSeleccionado) {
      this.cargarPlanillaDelEmpleado(
        this.empleadoSeleccionado.id,
        this.formulario.mes,
        this.formulario.anio,
        this.empleadoSeleccionado
      );
    }
  }

  recalcularMontosDinamicos(): void {
    if (!this.empleadoSeleccionado) return;
    const sueldo = this.formulario.remuneracionBasica ?? 0;
    const mes = this.formulario.mes;

    // Asignación Familiar S/ 113.00 si tiene hijos
    this.formulario.asignacionFamiliar = this.empleadoSeleccionado.tiene_hijos ? 113.00 : 0.00;
    
    // Gratificación de julio y diciembre
    this.formulario.gratificacionesFiestas = [7, 12].includes(Number(mes)) ? sueldo : 0.00;

    // Aportes de Pensión (ONP / AFP)
    if (this.empleadoSeleccionado.sistema_pensiones === 'ONP') {
      this.formulario.onp13 = Number((sueldo * 0.13).toFixed(2));
      this.formulario.sppFondoPensiones = null;
      this.formulario.sppPrimaSeguro = null;
      this.formulario.sppComision = null;
    } else if (this.empleadoSeleccionado.sistema_pensiones === 'AFP') {
      this.formulario.onp13 = null;
      this.formulario.sppFondoPensiones = Number((sueldo * 0.10).toFixed(2));
      this.formulario.sppPrimaSeguro = Number((sueldo * 0.0137).toFixed(2));
      
      const afp = this.empleadoSeleccionado.afp;
      const tasaComision = afp === 'Habitat' ? 0.0147 :
                           afp === 'Integra' ? 0.0155 :
                           afp === 'Prima' ? 0.0160 :
                           afp === 'Profuturo' ? 0.0169 : 0;
      this.formulario.sppComision = Number((sueldo * tasaComision).toFixed(2));
    } else {
      this.formulario.onp13 = null;
      this.formulario.sppFondoPensiones = null;
      this.formulario.sppPrimaSeguro = null;
      this.formulario.sppComision = null;
    }

    // Essalud (9%)
    this.formulario.essalud9 = Number((sueldo * 0.09).toFixed(2));
  }

  cerrarModal(): void {
    // Cerrar no marca nada. La columna dice si el trabajador YA TIENE su
    // planilla del mes guardada, y tocar el formulario sin guardar no la
    // crea: antes bastaba con abrir y cambiar un número para que la fila
    // dijera "editado" aunque en la base de datos no hubiera nada.
    this.showModal = false;
    this.empleadoSeleccionado = null;
    this.planillaActual = null;
    document.body.style.overflow = '';
  }

  get totalIngresos(): number {
    const f = this.formulario;
    return [
      f.remuneracionBasica, f.bonificacionCargo, f.asignacionFamiliar,
      f.vacacionesTruncas, f.gratificacionesFiestas, f.bonifExtraordTemporal,
      f.otrosConceptosSubsidio, f.compensacionTiempoServicios, f.bonificacion
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalDescuentos(): number {
    const f = this.formulario;
    return [
      f.onp13, f.sppFondoPensiones, f.sppPrimaSeguro, f.sppComision,
      f.ir5taCategoria, f.descuentoAlimentacion, f.descuentoBazar,
      f.descuentoAutorizadoDiezmo, f.descuentoOtros, f.descuentoEscolaridad,
      f.adelanto
    ].reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalAportaciones(): number {
    return [(this.formulario.essalud9), (this.formulario.sctr)]
      .reduce((sum: number, v) => sum + (v ? Number(v) : 0), 0);
  }

  get totalNetoPagar(): number {
    return this.totalIngresos - this.totalDescuentos;
  }

  /** Qué campo de la pantalla corresponde a cada concepto del catálogo. */
  private readonly CAMPO_POR_CONCEPTO: Record<string, keyof FormularioBoleta> = {
    'Bonificación por Cargo': 'bonificacionCargo',
    'Asignación Familiar': 'asignacionFamiliar',
    'Vacaciones Truncas': 'vacacionesTruncas',
    'Gratificaciones Fiestas Patrias - Ley 29351 y 30334': 'gratificacionesFiestas',
    'Bonif. Extraord. Temporal - Ley 29351 y 30334': 'bonifExtraordTemporal',
    'Otros Conceptos (Ingresos)': 'otrosConceptosSubsidio',
    'Compensación por Tiempo de Servicios': 'compensacionTiempoServicios',
    'Bonificaciones': 'bonificacion',
    'ONP 13%': 'onp13',
    'SPP. Fondo Pensiones': 'sppFondoPensiones',
    'SPP. Prima de Seguro': 'sppPrimaSeguro',
    'SPP. Comisión': 'sppComision',
    'I.R. 5ta Categoría': 'ir5taCategoria',
    'Descuento Serv. Alimentación': 'descuentoAlimentacion',
    'Descuento Serv. Bazar': 'descuentoBazar',
    'Descuento Autorizado - Diezmo': 'descuentoAutorizadoDiezmo',
    'Otros Conceptos (Descuentos)': 'descuentoOtros',
    'Descuento - Pago de Escolaridad Mensual': 'descuentoEscolaridad',
    'ESSALUD 9%': 'essalud9',
    'SCTR': 'sctr',
    'Adelanto de Sueldo': 'adelanto',
  };

  /**
   * Rellena el formulario con las LÍNEAS de la planilla, no con las columnas.
   *
   * Antes el diezmo, la alimentación y los demás se leían de dos columnas que
   * los traían sumados —y que desde el cambio a conceptos valen siempre 0—,
   * así que al reabrir el cuadro los campos salían vacíos y parecía que lo
   * guardado se había perdido.
   *
   * Se piden con un tope alto porque el endpoint viene paginado: con el
   * tamaño por defecto se quedarían fuera las últimas líneas de una planilla
   * cargada de conceptos.
   */
  private cargarConceptosEnFormulario(planillaId: string): void {
    this.detalleService.paginaDePlanilla(planillaId, 0, 200).subscribe({
      next: (res) => {
        if (!res.success) return;

        for (const linea of res.data.content) {
          const campo = this.CAMPO_POR_CONCEPTO[linea.payment_concept?.nombre ?? ''];
          if (campo) {
            (this.formulario[campo] as number | null) = Number(linea.monto_calculado);
          }
        }

        // Lo que no tenga línea se queda en blanco, que es lo que significa.
        this._formularioOriginal = JSON.stringify(this.formulario);
      },
      error: () => {
        this.toastService.error('Aviso', 'No se pudieron cargar los conceptos ya guardados de esta planilla.');
      },
    });
  }

  /**
   * Guarda lo escrito COMO CONCEPTOS, cada uno con su nombre.
   *
   * Antes sumaba los doce campos en dos números —total de bonificaciones y
   * total de descuentos— y los guardaba en dos columnas de la planilla. Se
   * perdía justo lo que importa: cuál era el diezmo, cuál la alimentación,
   * cuál el adelanto. En la boleta salían dos cifras sin explicación.
   *
   * Ahora cada campo viaja con el nombre de su concepto del catálogo y el
   * backend sincroniza las líneas de una vez. Un campo vacío borra su línea,
   * que es lo que se espera al dejarlo en blanco.
   *
   * Los calculados (pensión, EsSalud, Renta de 5ta, Asignación Familiar) NO
   * se mandan: los pone el motor según la ficha, y el backend además los
   * rechaza si alguien lo intenta.
   */
  guardarPlanillaEnServidor(): Observable<{ success: boolean; data: Planilla }> {
    const f = this.formulario;

    const conceptos: { nombre: string; monto: number | null }[] = [
      { nombre: 'Bonificación por Cargo', monto: f.bonificacionCargo },
      { nombre: 'Vacaciones Truncas', monto: f.vacacionesTruncas },
      { nombre: 'Gratificaciones Fiestas Patrias - Ley 29351 y 30334', monto: f.gratificacionesFiestas },
      { nombre: 'Bonif. Extraord. Temporal - Ley 29351 y 30334', monto: f.bonifExtraordTemporal },
      { nombre: 'Otros Conceptos (Ingresos)', monto: f.otrosConceptosSubsidio },
      { nombre: 'Compensación por Tiempo de Servicios', monto: f.compensacionTiempoServicios },
      { nombre: 'Bonificaciones', monto: f.bonificacion },
      { nombre: 'Descuento Serv. Alimentación', monto: f.descuentoAlimentacion },
      { nombre: 'Descuento Serv. Bazar', monto: f.descuentoBazar },
      { nombre: 'Descuento Autorizado - Diezmo', monto: f.descuentoAutorizadoDiezmo },
      { nombre: 'Otros Conceptos (Descuentos)', monto: f.descuentoOtros },
      { nombre: 'Descuento - Pago de Escolaridad Mensual', monto: f.descuentoEscolaridad },
      { nombre: 'SCTR', monto: f.sctr },
      { nombre: 'Adelanto de Sueldo', monto: f.adelanto },
    ];

    /*
     * Primero tiene que existir la planilla; sus conceptos van después.
     *
     * Al crearla NO se manda el sueldo base: sale de la ficha del trabajador
     * y el backend lo prorratea si entró a mitad de mes. Mandarlo desde acá
     * era escribir un número que el servidor ya ignoraba.
     */
    const planilla$ = this.planillaActual?.id
      ? of({ success: true, data: this.planillaActual } as { success: boolean; data: Planilla })
      : this.planillaService.crear({
          empleado_id: this.empleadoSeleccionado!.id,
          mes: Number(f.mes),
          anio: Number(f.anio),
        } as Partial<Planilla>);

    return planilla$.pipe(
      switchMap((res) => {
        const id = res.data?.id;
        if (!id) return of(res);

        return this.planillaService
          .sincronizarConceptos(id, conceptos)
          .pipe(map((sync) => ({ success: sync.success, data: sync.data.planilla })));
      })
    );
  }

  guardarBorrador(): void {
    if (!this.empleadoSeleccionado) return;

    this.guardarPlanillaEnServidor().subscribe({
      next: (res) => {
        if (res.success) {
          this.planillaActual = res.data;
          this._formularioOriginal = JSON.stringify(this.formulario);
          this.empleadosEditados.add(this.empleadoSeleccionado!.id);
          this.toastService.success('Borrador Guardado', `Se guardó la planilla para ${this.empleadoSeleccionado?.nombre} ${this.empleadoSeleccionado?.apellido} en la base de datos.`);
        }
      },
      error: (err) => {
        console.error('Error guardando planilla', err);
        const msg = err?.error?.message || 'No se pudo guardar los datos de la planilla en el servidor.';
        this.toastService.error('Error al guardar', msg);
      }
    });
  }

  emitirBoleta(): void {
    if (!this.empleadoSeleccionado) return;

    this.generandoPDF = true;
    const { mes, anio } = this.formulario;

    // Primero guardamos en la BD para asegurarnos de que el PDF tenga los datos correctos
    this.guardarPlanillaEnServidor().subscribe({
      next: (res) => {
        if (res.success) {
          this.planillaActual = res.data;
          this.empleadosEditados.add(this.empleadoSeleccionado!.id);

          // Ahora generamos y descargamos el PDF
          this.boletaService.generarBoletaEmpleado(this.empleadoSeleccionado!.id, mes, anio).subscribe({
            next: (blob) => {
              const url = window.URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = `boleta_${this.empleadoSeleccionado!.dni}_${mes}_${anio}.pdf`;
              a.click();
              window.URL.revokeObjectURL(url);
              this.generandoPDF = false;
              this.cerrarModal();
            },
            error: (err) => {
              console.error('Error generando boleta', err);
              const msg = err?.error?.message || `No existe planilla para el mes ${this.nombreMes(mes)} ${anio}.`;
              this.toastService.error('Error al generar', msg);
              this.generandoPDF = false;
            }
          });
        }
      },
      error: (err) => {
        console.error('Error al registrar planilla antes de emitir', err);
        const msg = err?.error?.message || 'No se pudo registrar la planilla en la base de datos.';
        this.toastService.error('Error de registro', msg);
        this.generandoPDF = false;
      }
    });
  }

  aplicarBonosMasivos(): void {
    // Solo un botón dummy por ahora para la demo
    this.toastService.info(
      'Función en desarrollo',
      'La asignación masiva de Gratificación y CTS se implementará en la próxima versión del sistema.'
    );
  }

  emitirTodasLasBoletas(): void {
    // Contra el total del colegio y no contra la lista: emitir masivamente
    // va por todo el mes, así que un filtro puesto no puede bloquearlo.
    if (this.totalDelColegio === 0) {
      this.toastService.warning('Aviso', 'No hay trabajadores en la lista para emitir boletas.');
      return;
    }
    // El diálogo de confirmación compartido, como en el resto del sistema:
    // este tenía uno propio hecho a mano, con estilos sueltos.
    this.confirmService
      .confirmar({
        titulo: 'Emitir todas las boletas',
        mensaje: `Se emitirán las boletas de ${this.nombreMes(Number(this.mesGlobal))} ${this.anioGlobal} para todo el personal que tenga planilla ese mes.`,
        aceptarTexto: 'Sí, emitir todas',
        variante: 'default',
      })
      .then((aceptado) => { if (aceptado) this.confirmarEmisionMasiva(); });
  }

  confirmarEmisionMasiva(): void {
    this.generandoMasivo = true;
    this.boletaService.generarMasivo(this.mesGlobal, this.anioGlobal).subscribe({
      next: (res) => {
        this.toastService.resultadoMasivo({
          hechas: res.generadas ?? 0,
          omitidas: res.omitidas ?? 0,
          exito: 'Boletas emitidas',
          nada: 'No se emitió ninguna boleta',
          cosas: 'boleta(s)',
          motivo: 'esos empleados no tienen planilla de ese mes, o ya tenían su boleta',
        });
        this.generandoMasivo = false;
        // Recargar para que las filas y el aviso del mes queden al día.
        this.cargarEstadoBoletas();
      },
      error: (err) => {
        console.error('Error generando masivo', err);
        this.toastService.error('Error', 'Hubo un problema al generar las boletas masivamente.');
        this.generandoMasivo = false;
      }
    });
  }

  private getFormularioVacio(): FormularioBoleta {
    const now = new Date();
    return {
      remuneracionBasica: null, bonificacionCargo: null, asignacionFamiliar: null,
      vacacionesTruncas: null, gratificacionesFiestas: null, bonifExtraordTemporal: null,
      otrosConceptosSubsidio: null, compensacionTiempoServicios: null, bonificacion: null,
      onp13: null, sppFondoPensiones: null, sppPrimaSeguro: null, sppComision: null,
      ir5taCategoria: null, descuentoAlimentacion: null, descuentoBazar: null,
      descuentoAutorizadoDiezmo: null, descuentoOtros: null, descuentoEscolaridad: null,
      essalud9: null, sctr: null, adelanto: null,
      ciudad: 'CATA',
      fechaEmision: now.toLocaleDateString('es-PE'),
      mes: this.mesGlobal,
      anio: this.anioGlobal
    };
  }
}
