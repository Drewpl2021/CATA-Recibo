import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { forkJoin } from 'rxjs';

import {
  PlanillaCorridaService,
  PlanillaService,
  PeriodoService,
  EmpleadoService,
  AreaService,
  CargoService,
  SedeService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import {
  Area, Cargo, Empleado, Periodo, PlanillaCorrida, PlanillaCorridaPayload, ResultadoVariosMeses, Sede,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorEmpleadosComponent } from '../../../shared/components/selector-empleados/selector-empleados.component';

/**
 * Planillas: el primer nivel, las corridas del mes.
 *
 * Antes esta pantalla abría con las filas de los 150 trabajadores seguidas,
 * una detrás de otra y sin ninguna estructura. Pero RR.HH. no trabaja fila a
 * fila: trabaja por planillas —"la de los docentes", "la de TIC"—, las revisa
 * enteras y las paga juntas.
 *
 * Así que ahora se entra y se ven las planillas del mes con su nombre, cuánta
 * gente tiene cada una y cuánto suma. Al abrir una salen sus trabajadores.
 *
 * Las planillas que no están en ninguna corrida —las de antes de que esto
 * existiera— no se pierden: salen en una fila aparte, "Sin agrupar", desde
 * donde se pueden mover a la que les toque.
 */
@Component({
  selector: 'app-corridas-list',
  standalone: true,
  imports: [
    CommonModule, FormsModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorEmpleadosComponent,
  ],
  templateUrl: './corridas-list.component.html',
})
export class CorridasListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private router = inject(Router);
  private corridaService = inject(PlanillaCorridaService);
  private planillaService = inject(PlanillaService);
  private periodoService = inject(PeriodoService);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  corridas: PlanillaCorrida[] = [];
  cargando = false;

  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  total = 0;

  /** Lo que dice la cabecera, contado por el backend sobre todo el filtro. */
  personas = 0;
  masaSalarial = 0;
  sinAgrupar = 0;
  sinAgruparMasa = 0;

  // Filtros de arriba
  filtroMes: number | '' = new Date().getMonth() + 1;
  filtroAnio: number | '' = new Date().getFullYear();

  // Catálogos para el modal
  periodos: Periodo[] = [];
  empleados: Empleado[] = [];
  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];

  // Modal de crear / editar
  modalVisible = false;
  guardando = false;
  corridaEditando: PlanillaCorrida | null = null;
  resultadoMeses: ResultadoVariosMeses | null = null;

  alcance: 'todos' | 'elegidos' = 'todos';
  empleadosElegidos: string[] = [];

  meses = Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: nombreMes(i + 1) }));

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    // El periodo manda. Antes se pedía el mes suelto de una lista de tres
    // años, y era pedirle a RR.HH. un dato que el sistema ya sabe: si el año
    // escolar va de marzo a diciembre, los meses que se pueden pagar son
    // esos y no otros. Elegido el periodo, el mes sale de él.
    periodo_id: ['', [Validators.required]],
    // El mes que se va a generar, como "2026-09". Una planilla es de UN mes:
    // la de septiembre paga septiembre. Antes se marcaban varios a la vez con
    // fichas, y abrir el año entero de golpe dejaba creadas planillas de
    // meses que todavía no habían pasado.
    mesAnio: ['', [Validators.required]],
    observaciones: ['', [Validators.maxLength(255)]],
    generar: [true],
  });

  get cifras(): CifraCabecera[] {
    return [
      { icono: 'table_chart', valor: this.total, etiqueta: 'Planillas', tono: 'brand' },
      { icono: 'people', valor: this.personas, etiqueta: 'Trabajadores', tono: 'success' },
      { icono: 'folder', valor: this.sinAgrupar, etiqueta: 'Sin agrupar', tono: 'muted' },
    ];
  }

  columnas: ColumnaTabla<PlanillaCorrida>[] = [
    { campo: 'nombre', header: 'Planilla', ancho: '30%' },
    {
      campo: 'mes', header: 'Mes', ancho: '16%',
      formatear: (_v, c) => `${nombreMes(c.mes)} ${c.anio}`,
    },
    {
      campo: 'personas', header: 'Trabajadores', ancho: '14%',
      formatear: (v) => `${v ?? 0}`,
    },
    { campo: 'masa_salarial', header: 'Neto a pagar', ancho: '18%', tipo: 'moneda' },
    {
      campo: 'estado', header: 'Estado', ancho: '12%', tipo: 'badge',
      formatear: (v) => (v === 'cerrada' ? 'Cerrada' : 'Abierta'),
      badgeSeveridad: (v) => (v === 'cerrada' ? 'secondary' : 'success'),
    },
  ];

  acciones: AccionPersonalizada<PlanillaCorrida>[] = [
    { id: 'abrir', titulo: 'Ver los trabajadores de esta planilla', icono: 'people', etiqueta: 'Ver' },
  ];

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  /** A un desplegable no se le pagina: los catálogos vienen completos. */
  private cargarCatalogos(): void {
    forkJoin({
      periodos: this.periodoService.getAll(),
      empleados: this.empleadoService.paraSelector(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      sedes: this.sedeService.getAll(),
    }).subscribe({
      next: ({ periodos, empleados, areas, cargos, sedes }) => {
        if (periodos.success) this.periodos = periodos.data;
        if (empleados.success) this.empleados = empleados.data;
        if (areas.success) this.areas = areas.data;
        if (cargos.success) this.cargos = cargos.data;
        if (sedes.success) this.sedes = sedes.data;

        // Si a alguien le dio tiempo de abrir el modal antes de que llegaran
        // los periodos, se le pone el primero ahora: si no, se queda con el
        // desplegable en blanco y sin meses que marcar.
        if (this.modalVisible && !this.corridaEditando && !this.form.get('periodo_id')!.value) {
          this.form.patchValue({ periodo_id: this.periodos[0]?.id ?? '' });
          this.alCambiarPeriodo();
        }
      },
      error: () => this.toastService.error('Aviso', 'No se pudieron cargar los datos de los filtros.'),
    });
  }

  get empleadosActivos(): Empleado[] {
    return this.empleados.filter((e) => e.estado !== 'inactivo');
  }

  get cuantosAlcanzados(): number {
    return this.alcance === 'todos' ? this.empleadosActivos.length : this.empleadosElegidos.length;
  }

  /** Lo que se está viendo, en palabras. */
  get etiquetaPeriodo(): string {
    if (this.filtroMes && this.filtroAnio) return `${nombreMes(Number(this.filtroMes))} ${this.filtroAnio}`;
    if (this.filtroAnio) return `Todo ${this.filtroAnio}`;
    return 'Todos los meses';
  }

  irAPagina(pagina: number): void {
    this.pagina = pagina;
    this.cargar();
  }

  buscar(termino: string): void {
    this.busqueda = termino;
    this.pagina = 0;
    this.cargar();
  }

  alFiltrar(): void {
    this.pagina = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.corridaService
      .getPagina({
        page: this.pagina,
        size: this.TAMANO_PAGINA,
        search: this.busqueda || undefined,
        mes: this.filtroMes || undefined,
        anio: this.filtroAnio || undefined,
      })
      .subscribe({
        next: (res) => {
          if (res.success) {
            this.corridas = res.data.content;
            this.total = res.data.totalElements;
            this.personas = (res.data as any).personas ?? 0;
            this.masaSalarial = (res.data as any).masaSalarial ?? 0;
            this.sinAgrupar = (res.data as any).sinAgrupar ?? 0;
            this.sinAgruparMasa = (res.data as any).sinAgruparMasa ?? 0;
          }
          this.cargando = false;
        },
        error: (err) => {
          this.cargando = false;
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar las planillas.'));
        },
      });
  }

  // ────────── Entrar a una planilla ──────────

  abrir(corrida: PlanillaCorrida): void {
    this.router.navigate(['/inicio/planillas/corrida', corrida.id]);
  }

  /** Las que no están en ninguna planilla, en su propia pantalla. */
  abrirSinAgrupar(): void {
    this.router.navigate(['/inicio/planillas/sin-agrupar'], {
      queryParams: { mes: this.filtroMes || null, anio: this.filtroAnio || null },
    });
  }

  alAccionar(evento: { accion: string; fila: PlanillaCorrida }): void {
    if (evento.accion === 'abrir') this.abrir(evento.fila);
  }

  // ────────── Crear y editar ──────────

  nueva(): void {
    this.corridaEditando = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];

    this.resultadoMeses = null;

    this.form.reset({
      nombre: '',
      periodo_id: this.periodos[0]?.id ?? '',
      mesAnio: '',
      observaciones: '',
      generar: true,
    });

    // Del periodo salen sus meses, y queda propuesto el que corre: abrir
    // esto en septiembre y encontrar septiembre puesto es lo que se espera.
    this.alCambiarPeriodo();
    this.modalVisible = true;
  }

  editar(corrida: PlanillaCorrida): void {
    this.corridaEditando = corrida;
    this.resultadoMeses = null;
    this.form.reset({
      nombre: corrida.nombre,
      periodo_id: corrida.periodo_id ?? '',
      mesAnio: `${corrida.anio}-${String(corrida.mes).padStart(2, '0')}`,
      observaciones: corrida.observaciones ?? '',
      generar: false,
    });
    this.alCambiarPeriodo();
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.corridaEditando = null;
    this.resultadoMeses = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /**
   * Los meses que cubre el periodo elegido.
   *
   * Un periodo de un año escolar da diez opciones; uno de un mes, una sola.
   * Las fechas se arman en horario local a propósito: `new Date('2026-03-01')`
   * es medianoche UTC, que en Perú (UTC-5) cae el 28 de febrero, y el periodo
   * arrancaría un mes antes.
   */
  mesesDelPeriodo: { valor: string; etiqueta: string }[] = [];

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
   * Al cambiar de periodo se rehacen sus meses y se propone uno.
   *
   * Se propone el mes EN CURSO, que es el que se paga casi siempre: llegar a
   * la pantalla en septiembre y encontrar septiembre ya puesto ahorra el
   * paso. Si el periodo no lo cubre —un año escolar ya cerrado— se deja su
   * primer mes. Y si lo que había elegido sigue valiendo, no se toca.
   */
  alCambiarPeriodo(): void {
    const id = this.form.get('periodo_id')!.value;
    const periodo = this.periodos.find((p) => p.id === id);

    this.mesesDelPeriodo = periodo ? this.mesesQueCubre(periodo) : [];

    const elegido = this.form.get('mesAnio')!.value as string;
    if (elegido && this.mesesDelPeriodo.some((m) => m.valor === elegido)) return;

    this.form.patchValue({ mesAnio: this.mesPorDefecto() });
  }

  /** El mes en curso si el periodo lo cubre; si no, el primero que tenga. */
  private mesPorDefecto(): string {
    const hoy = new Date();
    const enCurso = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;

    return this.mesesDelPeriodo.some((m) => m.valor === enCurso)
      ? enCurso
      : this.mesesDelPeriodo[0]?.valor ?? '';
  }

  // ────────── El mes que se va a generar ──────────

  /**
   * El mes elegido, en el formato de lista que espera el backend.
   *
   * Se manda como lista de uno y no como `mes`/`anio` sueltos porque el
   * backend ya atiende varios meses por esa vía y es el camino que ya está
   * probado: cambiarlo obligaría a tocar el controlador sin necesidad.
   */
  get mesesElegidos(): string[] {
    const valor = this.form.get('mesAnio')!.value as string;
    return valor ? [valor] : [];
  }

  /** Lo que dice el botón de guardar. */
  get textoCrear(): string {
    return 'Crear planilla';
  }

  /**
   * 1 si el mes elegido todavía no ha pasado; 0 si ya pasó.
   *
   * No lo impide: generarlo congela los sueldos de hoy, lo que sirve para
   * dejar un mes armado por adelantado, pero conviene avisarlo antes y no
   * que se descubra en la boleta. Devuelve un número y no un booleano
   * porque sigue contando sobre la lista que se manda al backend.
   */
  get mesesFuturos(): number {
    const hoy = new Date();
    const actual = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
    return this.mesesElegidos.filter((m) => m > actual).length;
  }

  /** El mes elegido en palabras: "Septiembre 2026". */
  get mesElegidoEtiqueta(): string {
    return this.mesesDelPeriodo.find((m) => m.valor === this.mesesElegidos[0])?.etiqueta ?? '';
  }

  /** Lo que va a pasar al guardar, en una frase. */
  get resumenDeMeses(): string {
    return this.mesElegidoEtiqueta ? `Se creará la planilla de ${this.mesElegidoEtiqueta}.` : '';
  }

  /** El rango del periodo, en cristiano, para ponerlo bajo el campo. */
  get rangoDelPeriodo(): string {
    if (!this.mesesDelPeriodo.length) return '';
    const primero = this.mesesDelPeriodo[0].etiqueta;
    const ultimo = this.mesesDelPeriodo[this.mesesDelPeriodo.length - 1].etiqueta;
    return primero === ultimo
      ? `Este periodo solo cubre ${primero}.`
      : `Este periodo va de ${primero} a ${ultimo} — ${this.mesesDelPeriodo.length} meses.`;
  }

  guardar(): void {
    // Con el resultado en pantalla el botón dice "Crear otra": deja el
    // formulario en blanco en vez de volver a mandar lo mismo, que solo
    // devolvería "ya existían" otra vez.
    if (this.resultadoMeses && !this.corridaEditando) {
      this.nueva();
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.toastService.error('Falta un dato', 'Ponle nombre a la planilla y elige el mes que se va a generar.');
      return;
    }

    const v = this.form.getRawValue();

    if (this.corridaEditando) {
      this.actualizar();
      return;
    }

    if (v.generar && this.alcance === 'elegidos' && !this.empleadosElegidos.length) {
      this.toastService.error('Falta elegir', 'Marca al menos un trabajador, o cambia a "todo el personal".');
      return;
    }

    const meses = [...this.mesesElegidos].sort();

    const payload: PlanillaCorridaPayload = {
      nombre: v.nombre!.trim(),
      meses,
      periodo_id: v.periodo_id || null,
      observaciones: v.observaciones || null,
      generar: !!v.generar,
    };

    // Sin lista, el backend alcanza a todo el personal activo.
    if (v.generar && this.alcance === 'elegidos') {
      payload.empleado_ids = this.empleadosElegidos;
    }

    this.guardando = true;
    this.resultadoMeses = null;

    this.corridaService.crear(payload).subscribe({
      next: (res) => {
        this.guardando = false;
        if (!res.success) return;

        this.resultadoMeses = res.data as unknown as ResultadoVariosMeses;
        this.avisarDelResultado(payload.nombre);
        this.cargar();
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('No se pudo crear', mensajeErrorApi(err, 'No se pudo crear la planilla.'));
      },
    });
  }

  /**
   * El aviso de arriba. Se separa del resumen del modal porque son dos cosas
   * distintas: el toast es lo que pasó en una línea, y el bloque de abajo el
   * detalle de mes a mes por si algo se saltó.
   */
  private avisarDelResultado(nombre: string): void {
    const r = this.resultadoMeses;
    if (!r) return;

    const { planillas, mesesOmitidos, generadas, omitidas } = r.resumen;

    if (!planillas) {
      this.toastService.error(
        'No se creó ninguna planilla',
        `Ya existían planillas llamadas "${nombre}" en los ${mesesOmitidos} mes(es) que marcaste.`
      );
      return;
    }

    const dice = [
      `${planillas} planilla(s) de "${nombre}"`,
      generadas ? `con ${generadas} trabajador(es) dentro` : 'vacías, listas para agregarles gente',
      omitidas ? `· ${omitidas} sin agregar (ya tenían planilla de ese mes o les falta el sueldo)` : '',
      mesesOmitidos ? `· ${mesesOmitidos} mes(es) ya la tenían y se saltaron` : '',
    ].filter(Boolean).join(' ');

    this.toastService.success('Planillas creadas', dice);
  }

  /** El mes de una planilla creada, en palabras, para el resumen del modal. */
  etiquetaDeMes(mes: number, anio: number): string {
    return `${nombreMes(mes)} ${anio}`;
  }

  private actualizar(): void {
    const v = this.form.getRawValue();
    this.guardando = true;

    this.corridaService
      .update(this.corridaEditando!.id, {
        nombre: v.nombre!.trim(),
        periodo_id: v.periodo_id || null,
        observaciones: v.observaciones || null,
      })
      .subscribe({
        next: () => {
          this.guardando = false;
          this.toastService.success('Planilla actualizada', 'Los cambios quedaron guardados.');
          this.cerrarModal();
          this.cargar();
        },
        error: (err) => {
          this.guardando = false;
          this.toastService.error('No se pudo guardar', mensajeErrorApi(err, 'No se pudo actualizar la planilla.'));
        },
      });
  }

  /** Cerrarla la deja como está; abrirla vuelve a permitir tocarla. */
  alternarCierre(corrida: PlanillaCorrida): void {
    const cerrando = corrida.estado !== 'cerrada';
    this.corridaService.update(corrida.id, { estado: cerrando ? 'cerrada' : 'abierta' }).subscribe({
      next: () => {
        this.toastService.success(
          cerrando ? 'Planilla cerrada' : 'Planilla abierta',
          cerrando
            ? 'Queda como está: no se le pueden mover cifras ni agregar gente.'
            : 'Ya se le pueden agregar trabajadores y ajustar conceptos.'
        );
        this.cargar();
      },
      error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cambiar el estado.')),
    });
  }

  eliminar(corrida: PlanillaCorrida): void {
    const cuantos = corrida.personas ?? 0;
    const aviso = cuantos > 0
      ? `la planilla "${corrida.nombre}". Sus ${cuantos} trabajador(es) NO se borran: pasan a "Sin agrupar"`
      : `la planilla "${corrida.nombre}"`;

    this.confirmService.confirmarEliminar(aviso, () => {
      this.corridaService.delete(corrida.id).subscribe({
        next: (res) => {
          this.toastService.success('Planilla eliminada', res.data?.message ?? 'Se eliminó la agrupación.');
          this.cargar();
        },
        error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar la planilla.')),
      });
    });
  }

  // ────────── Reporte general de la planilla ──────────

  exportando = false;

  /**
   * Baja el reporte de TODOS los trabajadores del mes, estén en la planilla
   * que estén: una fila por trabajador, con su ficha, una columna por cada
   * concepto y su neto.
   *
   * Va el mes y el año del filtro de arriba, pero NO el buscador: el de esta
   * pantalla busca nombres de planilla ("TIC", "Docentes"), y el del reporte
   * busca personas. Pasárselo daría un archivo vacío casi siempre.
   *
   * Para bajar solo una planilla concreta se entra a ella: ahí está el mismo
   * botón, ya acotado a su gente.
   */
  exportar(): void {
    this.exportando = true;

    this.planillaService
      .exportar({
        mes: this.filtroMes || undefined,
        anio: this.filtroAnio || undefined,
      })
      .subscribe({
        next: (blob) => {
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = this.nombreDelReporte();
          a.click();
          window.URL.revokeObjectURL(url);

          this.exportando = false;
          this.toastService.success(
            'Reporte descargado',
            `Todos los trabajadores de ${this.etiquetaPeriodo}, con sus conceptos.`
          );
        },
        error: (err) => {
          this.exportando = false;
          this.toastService.error('No se descargó', mensajeErrorApi(err, 'No se pudo generar el reporte.'));
        },
      });
  }

  /**
   * "Planilla general - Septiembre 2026.csv".
   *
   * Se arma acá y no se lee de la respuesta porque el backend no expone
   * Content-Disposition al navegador: sin esa cabecera en
   * Access-Control-Expose-Headers, el front no puede leerla.
   */
  private nombreDelReporte(): string {
    const mes = this.filtroMes ? nombreMes(Number(this.filtroMes)) : 'Todos los meses';
    const anio = this.filtroAnio ? ` ${this.filtroAnio}` : '';

    // Windows rechaza estos caracteres en un nombre de archivo.
    return `Planilla general - ${mes}${anio}.csv`.replace(/[\\/:*?"<>|]/g, '');
  }

  nombreMes = nombreMes;
}
