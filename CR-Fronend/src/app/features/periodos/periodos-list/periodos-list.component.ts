import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import {
  PeriodoService,
  EmpleadoService,
  AreaService,
  CargoService,
  SedeService,
  ToastService,
  ConfirmService,
} from '../../../core/services';
import {
  Area,
  Cargo,
  Empleado,
  GeneracionMasivaPlanilla,
  Periodo,
  PeriodoPayload,
  Sede,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { MESES_OPCIONES, nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import {
  SelectorEmpleadosComponent,
  AlcanceGrupo,
} from '../../../shared/components/selector-empleados/selector-empleados.component';
import { forkJoin } from 'rxjs';

/**
 * Periodos (RR.HH. y Admin).
 *
 * Un periodo es el tramo de tiempo que abarca una campaña de planillas (por
 * ejemplo el año escolar). Además del CRUD, es desde acá que se lanza la
 * GENERACIÓN MASIVA: crea de una vez la planilla del mes elegido para todos
 * los empleados activos, con sus conceptos automáticos ya calculados.
 *
 * Esa generación es idempotente: a quien ya tenga planilla de ese mes se le
 * omite en vez de duplicarla, y el backend devuelve el detalle de qué pasó
 * con cada empleado.
 */
@Component({
  selector: 'app-periodos-list',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule,
    PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorEmpleadosComponent,
  ],
  templateUrl: './periodos-list.component.html',
})
export class PeriodosListComponent implements OnInit {
  private fb = inject(FormBuilder);
  private periodoService = inject(PeriodoService);
  private empleadoService = inject(EmpleadoService);
  private areaService = inject(AreaService);
  private cargoService = inject(CargoService);
  private sedeService = inject(SedeService);
  private toastService = inject(ToastService);
  private confirmService = inject(ConfirmService);

  periodos: Periodo[] = [];
  cargando = false;

  /** Filas por página; el backend corta y cuenta, acá solo se pinta. */
  readonly TAMANO_PAGINA = 10;
  pagina = 0;
  busqueda = '';
  /** Cuántos hay en total, según el backend — no el largo de la página. */
  total = 0;

  /**
   * Una sola cifra: esta tabla no maneja activos e inactivos. El listado ya
   * excluye lo dado de baja, así que un contador de inactivos diría 0 siempre.
   */
  get cifras(): CifraCabecera[] {
    return [{ icono: 'date_range', valor: this.total, etiqueta: 'Periodos', tono: 'brand' }];
  }
  guardando = false;
  modalVisible = false;
  periodoEditando: Periodo | null = null;

  // ── Generación masiva ──
  modalGenerarVisible = false;
  generando = false;
  periodoAGenerar: Periodo | null = null;
  resultado: GeneracionMasivaPlanilla | null = null;

  /** A quiénes se les arma la planilla: a todos, o a un grupo elegido. */
  alcance: AlcanceGrupo = 'todos';
  empleadosElegidos: string[] = [];

  // Catálogos para el selector de grupo
  empleados: Empleado[] = [];
  areas: Area[] = [];
  cargos: Cargo[] = [];
  sedes: Sede[] = [];

  meses = MESES_OPCIONES;
  nombreMes = nombreMes;

  columnas: ColumnaTabla<Periodo>[] = [
    { campo: 'nombre', header: 'Periodo', ancho: '35%' },
    { campo: 'fecha_inicio', header: 'Desde', tipo: 'fecha' },
    { campo: 'fecha_fin', header: 'Hasta', tipo: 'fecha' },
  ];

  /**
   * Atajo para generar desde el propio periodo. La entrada principal está en
   * la pantalla de Planillas, que es donde se busca; esto es un acceso rápido.
   */
  accionesExtra: AccionPersonalizada<Periodo>[] = [
    { id: 'generar', titulo: 'Generar planillas de este periodo', icono: 'table_chart', etiqueta: 'Generar' },
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(45)]],
    fecha_inicio: ['', [Validators.required]],
    fecha_fin: ['', [Validators.required]],
  });

  formGenerar = this.fb.group({
    mes: [new Date().getMonth() + 1, [Validators.required]],
    anio: [new Date().getFullYear(), [Validators.required, Validators.min(2000)]],
  });

  ngOnInit(): void {
    this.cargar();
    this.cargarCatalogos();
  }

  /** Lo que necesita el selector de grupo; se pide una sola vez. */
  private cargarCatalogos(): void {
    forkJoin({
      empleados: this.empleadoService.getAll(),
      areas: this.areaService.getAll(),
      cargos: this.cargoService.getAll(),
      sedes: this.sedeService.getAll(),
    }).subscribe({
      next: ({ empleados, areas, cargos, sedes }) => {
        // Solo personal activo: a un inactivo no se le arma planilla.
        if (empleados.success) this.empleados = empleados.data.filter((e) => e.estado !== 'inactivo');
        if (areas.success) this.areas = areas.data;
        if (cargos.success) this.cargos = cargos.data;
        if (sedes.success) this.sedes = sedes.data;
      },
      error: () => {
        // No es crítico: se podrá generar para todos, aunque no se pueda acotar.
        this.toastService.error('Aviso', 'No se pudo cargar la lista de empleados para acotar el grupo.');
      },
    });
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** El backend exige fecha_fin >= fecha_inicio; se avisa antes de enviar. */
  get rangoInvertido(): boolean {
    const desde = this.form.get('fecha_inicio')?.value;
    const hasta = this.form.get('fecha_fin')?.value;
    return !!desde && !!hasta && hasta < desde;
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
    this.periodoService
      .getPagina({ page: this.pagina, size: this.TAMANO_PAGINA, search: this.busqueda || undefined })
      .subscribe({
      next: (res) => {
        if (res.success) {
          this.periodos = res.data.content;
          this.total = res.data.totalElements;
        }
        this.cargando = false;
      },
      error: (err) => {
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los periodos.'));
        this.cargando = false;
      },
    });
  }

  nuevo(): void {
    this.periodoEditando = null;
    this.form.reset();
    this.modalVisible = true;
  }

  editar(periodo: Periodo): void {
    this.periodoEditando = periodo;
    this.form.patchValue({
      nombre: periodo.nombre,
      // El backend manda las fechas como "2026-03-01"; <input type="date">
      // necesita exactamente ese formato, así que se recorta por si viniera
      // con hora ("2026-03-01T00:00:00").
      fecha_inicio: (periodo.fecha_inicio ?? '').slice(0, 10),
      fecha_fin: (periodo.fecha_fin ?? '').slice(0, 10),
    });
    this.modalVisible = true;
  }

  cerrarModal(): void {
    this.modalVisible = false;
    this.periodoEditando = null;
    this.form.reset();
  }

  guardar(): void {
    if (this.form.invalid || this.rangoInvertido) {
      this.form.markAllAsTouched();
      return;
    }

    const payload = this.form.getRawValue() as PeriodoPayload;
    this.guardando = true;

    const peticion = this.periodoEditando
      ? this.periodoService.update(this.periodoEditando.id, payload)
      : this.periodoService.create(payload);

    peticion.subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.toastService.success(
            this.periodoEditando ? 'Periodo actualizado' : 'Periodo creado',
            `"${res.data.nombre}" se guardó correctamente.`
          );
          this.cerrarModal();
          this.cargar();
        }
      },
      error: (err) => {
        this.guardando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo guardar el periodo.'));
      },
    });
  }

  eliminar(periodo: Periodo): void {
    this.confirmService.confirmarEliminar(`el periodo "${periodo.nombre}"`, () => {
      this.periodoService.delete(periodo.id).subscribe({
        next: () => {
          this.toastService.success('Eliminado', `El periodo "${periodo.nombre}" fue eliminado.`);
          this.cargar();
        },
        error: (err) => {
          this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo eliminar el periodo.'));
        },
      });
    });
  }

  // ────────── Generación masiva de planillas ──────────

  abrirGenerar(periodo: Periodo): void {
    this.periodoAGenerar = periodo;
    this.resultado = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];
    this.formGenerar.reset({
      mes: new Date().getMonth() + 1,
      anio: new Date().getFullYear(),
    });
    this.modalGenerarVisible = true;
  }

  cerrarGenerar(): void {
    this.modalGenerarVisible = false;
    this.periodoAGenerar = null;
    this.resultado = null;
    this.alcance = 'todos';
    this.empleadosElegidos = [];
  }

  /** A cuántos alcanzará la generación tal como está configurada ahora. */
  get cuantosAlcanzados(): number {
    return this.alcance === 'todos' ? this.empleados.length : this.empleadosElegidos.length;
  }

  generar(): void {
    if (this.formGenerar.invalid || !this.periodoAGenerar) {
      this.formGenerar.markAllAsTouched();
      return;
    }

    if (this.alcance === 'elegidos' && !this.empleadosElegidos.length) {
      this.toastService.error('Falta elegir', 'Marca al menos un empleado, o cambia a "todo el personal".');
      return;
    }

    const { mes, anio } = this.formGenerar.getRawValue();
    this.generando = true;
    this.resultado = null;

    // Sin lista, el backend va a todo el personal activo.
    const soloEstos = this.alcance === 'elegidos' ? this.empleadosElegidos : undefined;

    this.periodoService.generarPlanillaMasiva(this.periodoAGenerar.id, Number(mes), Number(anio), soloEstos).subscribe({
      next: (res) => {
        this.generando = false;
        if (res.success) {
          this.resultado = res.data;
          const { generadas, omitidas } = res.data.resumen;
          this.toastService.resultadoMasivo({
            hechas: generadas,
            omitidas,
            exito: 'Planillas generadas',
            nada: 'No se generó ninguna planilla',
            cosas: 'planilla(s)',
            motivo: 'esos empleados ya tenían planilla de ese mes, o no tienen sueldo básico',
          });
        }
      },
      error: (err) => {
        this.generando = false;
        // El backend responde 422 cuando el mes cae fuera del rango del periodo.
        this.toastService.error('No se generó', mensajeErrorApi(err, 'No se pudieron generar las planillas.'));
      },
    });
  }
}
