import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ConfirmService, ImportacionPlameService, PaymentConceptService, ToastService } from '../../../core/services';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import {
  AvisoPlame,
  ColumnaPlame,
  DecisionesPlame,
  PaymentConcept,
  ResultadoPlame,
  SituacionPlame,
  TrabajadorPlame,
  VistaPreviaPlame,
} from '../../../core/models';
import { mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';
import { WizardComponent } from '../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../shared/components/wizard/wizard.models';

type FiltroTrabajadores = 'todos' | 'nuevos' | 'avisos' | 'diferencias';

const ETIQUETA_SITUACION: Record<SituacionPlame, string> = {
  existe: 'En el sistema',
  nuevo_activo: 'Nuevo · activo',
  nuevo_cesado: 'Nuevo · cesado',
  igual: 'Igual al sistema',
  con_diferencias: 'Con diferencias',
  sin_planilla: 'Sin planilla en el sistema',
  sin_ficha: 'No está en el sistema',
};

const SEVERIDAD_SITUACION: Record<SituacionPlame, string> = {
  existe: 'success',
  nuevo_activo: 'info',
  nuevo_cesado: 'secondary',
  igual: 'success',
  con_diferencias: 'warning',
  sin_planilla: 'warning',
  sin_ficha: 'warning',
};

/**
 * Importar el PLAME de RR.HH.: la planilla de un mes tal como se pagó.
 *
 * Para los meses que no pasaron por el sistema (enero a julio de 2026). Tres
 * pasos:
 *
 *   1. Subir el PLAME     el mes, el año y el .xls de RR.HH.
 *   2. Revisar            cada fila tiene que cuadrar con su neto; los
 *                         extras (curso, corbatas) van a «Otros Conceptos»
 *                         con su detalle; quien no está en el sistema entra
 *                         activo o cesado según su contrato.
 *   3. Listo              planillas cerradas y marcadas «PLAME».
 *
 * Si el mes ya tiene planillas del sistema (agosto en adelante), el paso 2
 * solo compara: no se puede importar encima.
 */
@Component({
  selector: 'app-importar-plame',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    PageHeaderComponent, WizardComponent, IconComponent,
    DataTableComponent, CeldaTablaDirective, SelectorArchivoComponent,
  ],
  templateUrl: './importar-plame.component.html',
})
export class ImportarPlameComponent implements OnInit {
  private progreso = inject(ProgresoService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private importacion = inject(ImportacionPlameService);
  private conceptos = inject(PaymentConceptService);
  private toast = inject(ToastService);
  private confirm = inject(ConfirmService);

  readonly meses = Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: nombreMes(i + 1) }));
  mes = new Date().getMonth() + 1;
  anio = new Date().getFullYear();

  readonly pasos: PasoWizard[] = [
    { id: 'archivo', titulo: 'Subir el PLAME', icono: 'description', campos: ['revisado'] },
    { id: 'revision', titulo: 'Revisar el mes', icono: 'check_circle', campos: ['aplicado'] },
    { id: 'listo', titulo: 'Listo', icono: 'receipt' },
  ];
  paso = 0;

  /** Le dice al wizard qué pasos están terminados. */
  avance = this.fb.group({
    revisado: [false, Validators.requiredTrue],
    aplicado: [false, Validators.requiredTrue],
  });

  // ── Paso 1 ─────────────────────────────────────────────
  archivos: File[] = [];
  revisando = false;

  // ── Paso 2 ─────────────────────────────────────────────
  vista: VistaPreviaPlame | null = null;
  decisiones: DecisionesPlame = { columnas: {}, personas: {} };
  /** Se tocó una decisión y la revisión de abajo ya no es la de ahora. */
  desactualizada = false;
  aplicando = false;

  catalogo: PaymentConcept[] = [];
  columnasEditables: ColumnaPlame[] = [];
  columnasDeSiempre: ColumnaPlame[] = [];
  verTodasLasColumnas = false;

  filtro: FiltroTrabajadores = 'todos';
  chips: { valor: FiltroTrabajadores; texto: string; cuenta: number }[] = [];
  trabajadoresVisibles: TrabajadorPlame[] = [];

  readonly columnasDeEditables: ColumnaTabla<ColumnaPlame>[] = [
    { campo: 'titulo', header: 'Columna del PLAME', ancho: '26%' },
    { campo: 'ejemplo', header: 'Ejemplo', ancho: '12%', formatear: (v) => v || '—' },
    { campo: 'motivo', header: 'Lo que entendió el sistema', ancho: '32%' },
    { campo: 'concepto_id', header: 'A dónde va' },
  ];

  readonly columnasDeSiempreTabla: ColumnaTabla<ColumnaPlame>[] = [
    { campo: 'titulo', header: 'Columna del PLAME', ancho: '34%' },
    { campo: 'estado', header: 'Qué es', ancho: '18%' },
    { campo: 'motivo', header: 'Detalle' },
  ];

  readonly columnasDeTrabajadores: ColumnaTabla<TrabajadorPlame>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '26%' },
    { campo: 'situacion', header: 'Situación', ancho: '16%' },
    { campo: 'lineas', header: 'Lo que trae el PLAME' },
    { campo: 'neto', header: 'Neto del mes', ancho: '14%' },
  ];

  // ── Paso 3 ─────────────────────────────────────────────
  resultado: ResultadoPlame | null = null;

  ngOnInit(): void {
    const parametros = this.route.snapshot.queryParamMap;
    const mes = Number(parametros.get('mes'));
    const anio = Number(parametros.get('anio'));
    if (mes >= 1 && mes <= 12) this.mes = mes;
    if (anio >= 2000 && anio <= 2100) this.anio = anio;

    this.conceptos.listar({ size: 200 }).subscribe({
      next: (res) => {
        if (res.success) this.catalogo = res.data.content.filter((c) => !c.calculo_especial);
      },
    });
  }

  get periodo(): string {
    return `${nombreMes(this.mes)} ${this.anio}`;
  }

  // ═══ Paso 1 ════════════════════════════════════════════════════

  alElegirArchivo(archivos: File[]): void {
    this.archivos = archivos;
    this.decisiones = { columnas: {}, personas: {} };
    this.reiniciar();
    if (archivos[0]) this.revisar();
  }

  alCambiarPeriodo(): void {
    this.reiniciar();
  }

  revisar(): void {
    const archivo = this.archivos[0];
    if (!archivo || this.revisando) return;

    this.revisando = true;
    this.progreso
      .seguir('Revisando el PLAME', this.importacion.previsualizar(archivo, this.mes, this.anio, this.decisiones))
      .subscribe({
        next: (res) => {
          this.revisando = false;
          if (!res.success) return;
          this.cargarVista(res.data);
          this.avance.patchValue({ revisado: true });
          this.paso = 1;
        },
        error: (err) => {
          this.revisando = false;
          this.toast.error('No se pudo revisar', mensajeErrorApi(err, 'Revisa el archivo e intenta de nuevo.'));
        },
      });
  }

  // ═══ Paso 2 ════════════════════════════════════════════════════

  private cargarVista(vista: VistaPreviaPlame): void {
    this.vista = vista;
    this.desactualizada = false;
    this.columnasEditables = vista.columnas.filter((c) => c.editable);
    this.columnasDeSiempre = vista.columnas.filter((c) => !c.editable);
    this.armarChips();
    this.filtrar();
  }

  private armarChips(): void {
    const t = this.vista?.trabajadores ?? [];
    const comparar = this.vista?.resumen.modo === 'comparar';
    this.chips = [
      { valor: 'todos' as FiltroTrabajadores, texto: 'Todos', cuenta: t.length },
      { valor: 'nuevos' as FiltroTrabajadores, texto: 'No estaban en el sistema', cuenta: t.filter((x) => this.esNuevo(x)).length },
      { valor: 'avisos' as FiltroTrabajadores, texto: 'Distinto de su ficha', cuenta: t.filter((x) => x.avisos.length > 0).length },
      ...(comparar
        ? [{ valor: 'diferencias' as FiltroTrabajadores, texto: 'Distinto del sistema', cuenta: t.filter((x) => x.situacion === 'con_diferencias' || x.situacion === 'sin_planilla').length }]
        : []),
    ];
    if (!this.chips.some((c) => c.valor === this.filtro)) this.filtro = 'todos';
  }

  elegirFiltro(filtro: FiltroTrabajadores): void {
    this.filtro = filtro;
    this.filtrar();
  }

  private filtrar(): void {
    const t = this.vista?.trabajadores ?? [];
    this.trabajadoresVisibles = t.filter((x) => {
      switch (this.filtro) {
        case 'nuevos': return this.esNuevo(x);
        case 'avisos': return x.avisos.length > 0;
        case 'diferencias': return x.situacion === 'con_diferencias' || x.situacion === 'sin_planilla';
        default: return true;
      }
    });
  }

  esNuevo(t: TrabajadorPlame): boolean {
    return t.situacion === 'nuevo_activo' || t.situacion === 'nuevo_cesado' || t.situacion === 'sin_ficha';
  }

  /** Lo que hoy se hace con una columna editable, para el desplegable. */
  decisionDe(c: ColumnaPlame): string {
    if (this.decisiones.columnas[c.indice] !== undefined) return this.decisiones.columnas[c.indice];
    if (c.rol === 'otros') return c.clave ?? 'ignorar';
    if (c.rol === 'concepto') return c.concepto_id ?? 'ignorar';
    return 'ignorar';
  }

  cambiarColumna(c: ColumnaPlame, valor: string): void {
    this.decisiones.columnas[c.indice] = valor;
    this.desactualizada = true;
  }

  cambiarPersona(t: TrabajadorPlame, estado: 'activo' | 'cesado'): void {
    this.decisiones.personas[t.dni] = estado;
    this.desactualizada = true;
  }

  get sePuedeAplicar(): boolean {
    const v = this.vista;
    return !!v && v.resumen.modo !== 'comparar' && v.resumen.errores === 0 && v.resumen.trabajadores > 0
      && !this.desactualizada && !this.aplicando;
  }

  etiquetaSituacion(s: SituacionPlame): string {
    return ETIQUETA_SITUACION[s] ?? s;
  }

  severidadSituacion(s: SituacionPlame): string {
    return SEVERIDAD_SITUACION[s] ?? 'secondary';
  }

  etiquetaEstadoColumna(c: ColumnaPlame): string {
    switch (c.estado) {
      case 'reconocida': return c.concepto ?? 'Concepto';
      case 'dato': return 'Dato del trabajador';
      case 'total': return 'Total para comprobar';
      case 'informativa': return 'De referencia';
      case 'falta': return 'Falta en el catálogo';
      default: return 'No se importa';
    }
  }

  /** Dónde está el problema, en palabras de quien mira el Excel. */
  ubicacion(aviso: AvisoPlame): string {
    const partes = [aviso.fila ? `Fila ${aviso.fila}` : '', aviso.dni ? `DNI ${aviso.dni}` : '', aviso.columna ?? ''].filter((p) => p);
    return partes.length ? partes.join(', ') : 'Todo el archivo';
  }

  aplicar(): void {
    const vista = this.vista;
    const archivo = this.archivos[0];
    if (!vista || !archivo || !this.sePuedeAplicar) return;

    const r = vista.resumen;
    const nuevos = r.nuevos_activos + r.nuevos_cesados;
    this.confirm
      .confirmar({
        titulo: r.modo === 'reemplazar' ? `Reemplazar la planilla de ${r.periodo}` : `Importar la planilla de ${r.periodo}`,
        mensaje: `Se guardarán ${r.trabajadores} planilla(s) de ${r.periodo}, cerradas, tal como vienen en el PLAME.`
          + (nuevos ? ` Además se registrarán ${nuevos} trabajador(es) que no estaban en el sistema.` : '')
          + (r.modo === 'reemplazar' ? ' La importación anterior de este mes se reemplaza.' : ''),
        aceptarTexto: 'Sí, importar',
        variante: 'default',
      })
      .then((aceptado) => {
        if (!aceptado) return;
        this.aplicando = true;
        this.progreso
          .seguir('Importando el PLAME', this.importacion.aplicar(archivo, this.mes, this.anio, this.decisiones))
          .subscribe({
            next: (res) => {
              this.aplicando = false;
              if (!res.success) return;
              this.resultado = res.data;
              this.avance.patchValue({ aplicado: true });
              this.paso = 2;
              this.toast.success('PLAME importado', res.data.mensaje);
            },
            error: (err) => {
              this.aplicando = false;
              if (err?.status === 422 && err?.error?.data?.resumen) this.cargarVista(err.error.data as VistaPreviaPlame);
              this.toast.error('No se importó nada', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
            },
          });
      });
  }

  // ═══ Navegación ════════════════════════════════════════════════

  otroArchivo(): void {
    this.archivos = [];
    this.decisiones = { columnas: {}, personas: {} };
    this.reiniciar();
    this.paso = 0;
  }

  verPlanillas(): void {
    this.router.navigate(['/inicio/planillas'], { queryParams: { mes: this.mes, anio: this.anio } });
  }

  private reiniciar(): void {
    this.vista = null;
    this.resultado = null;
    this.desactualizada = false;
    this.columnasEditables = [];
    this.columnasDeSiempre = [];
    this.trabajadoresVisibles = [];
    this.chips = [];
    this.avance.patchValue({ revisado: false, aplicado: false });
  }
}
