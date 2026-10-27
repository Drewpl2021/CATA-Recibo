import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription } from 'rxjs';

import { ConfirmService, ToastService } from '../../../core/services';
import {
  EstadoGratificacion, FilaGratificacion, GratificacionService, HistorialGratificacion, ListaGratificacion,
  MesGratificacion, PeriodoGratificacion,
} from '../../../core/services/planilla/gratificacion.service';
import { fechaDeDia, guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { PanelLateralComponent } from '../../../shared/components/panel-lateral/panel-lateral.component';
import { BarraSeleccionComponent, resumirMarcados } from '../../../shared/components/barra-seleccion/barra-seleccion.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { OrdenComponent, OpcionOrden } from '../../../shared/components/orden/orden.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { PistaDirective } from '../../../shared/directives/pista.directive';
import { PasoCts } from '../../cts/cts-list/cts-list.component';

/** Cómo se dice y se pinta cada estado de la gratificación de alguien. */
const ESTADOS: Record<EstadoGratificacion, { texto: string; tono: 'success' | 'info' | 'warning' | 'danger' | 'secondary' }> = {
  diferencia: { texto: 'Con diferencia', tono: 'danger' },
  por_pagar: { texto: 'Por pagar', tono: 'warning' },
  pagada: { texto: 'Pagada', tono: 'success' },
  no_corresponde: { texto: 'No corresponde', tono: 'secondary' },
};

interface ChipSituacion {
  valor: EstadoGratificacion | 'trunca' | '';
  texto: string;
  cuenta: number;
}

/** Un mes en el mapa del panel, con su barra ya medida. */
interface BarraMes {
  mes: MesGratificacion;
  etiqueta: string;
  ancho: number;
  pista: string;
}

/**
 * Gratificaciones: la de Fiestas Patrias (julio) y la de Navidad (diciembre).
 *
 * Se PAGAN en la planilla de ese mes y salen en la boleta: esta pantalla no
 * paga, revisa. Por cada trabajador dice cuánto le corresponde por ley y lo
 * compara con lo que tiene su planilla; las diferencias salen en rojo. A
 * quien ya la tiene pagada se le deja su hoja en el expediente.
 *
 * Misma forma que CTS: el periodo arriba, los tres pasos, chips, la tabla con
 * Ordenar y Filtros, y el detalle en el panel de la derecha. Las filas y las
 * cifras son propiedades, no getters (ver la regla de las tablas).
 */
@Component({
  selector: 'app-gratificaciones-list',
  standalone: true,
  imports: [
    PistaDirective, CommonModule, FormsModule, IconComponent, PageHeaderComponent, DataTableComponent, CeldaTablaDirective,
    FiltrosComponent, OrdenComponent, PanelLateralComponent, BarraSeleccionComponent,
  ],
  templateUrl: './gratificaciones-list.component.html',
})
export class GratificacionesListComponent implements OnInit {
  private servicio = inject(GratificacionService);
  private toast = inject(ToastService);
  private confirmar = inject(ConfirmService);
  private router = inject(Router);
  private ruta = inject(ActivatedRoute);
  private location = inject(Location);

  /*
   * Al cambiar de periodo o de año, la pantalla no parpadea. Antes, en el
   * mismo instante del clic, se desactivaban las pestañas, el año y los
   * botones (se ponían pálidos), se atenuaban la tabla y las cifras, y la
   * dirección se cambiaba con router.navigate —que el sistema toma como
   * «abriendo otra pantalla»—; al llegar los datos, todo volvía de golpe.
   * Ahora nada se apaga si la respuesta llega rápido (400 ms); si tarda, se
   * atenúan solo la tabla y las cifras. Un clic nuevo cancela el anterior.
   */
  refrescando = false;
  private esperaRefresco?: ReturnType<typeof setTimeout>;
  private pedido?: Subscription;

  private empezarCarga(): void {
    this.cargando = true;
    clearTimeout(this.esperaRefresco);
    this.esperaRefresco = setTimeout(() => (this.refrescando = this.cargando), 400);
    this.pedido?.unsubscribe();
  }

  private terminarCarga(): void {
    this.cargando = false;
    clearTimeout(this.esperaRefresco);
    this.refrescando = false;
  }

  /** El periodo en la dirección (para volver o compartir), sin navegar. */
  private ponerEnLaDireccion(): void {
    const url = this.router.createUrlTree([], {
      relativeTo: this.ruta, queryParams: { anio: this.anio, periodo: this.periodo }, queryParamsHandling: 'merge',
    });
    this.location.replaceState(url.toString());
  }

  readonly anioActual = new Date().getFullYear();
  readonly anios = [this.anioActual, this.anioActual - 1, this.anioActual - 2];
  readonly periodos: { valor: PeriodoGratificacion; titulo: string; detalle: string }[] = [
    { valor: 'julio', titulo: 'Julio', detalle: 'Fiestas Patrias · enero – junio' },
    { valor: 'diciembre', titulo: 'Diciembre', detalle: 'Navidad · julio – diciembre' },
  ];
  anio: number | null = null;
  periodo: PeriodoGratificacion | null = null;

  datos: ListaGratificacion | null = null;
  cargando = false;
  cifras: CifraCabecera[] = [];
  pasos: PasoCts[] = [];
  chips: ChipSituacion[] = [];
  diasParaLimite = 0;
  rangoSemestre = '';
  filasVisibles: FilaGratificacion[] = [];
  readonly fecha = fechaDeDia;

  situacion: ChipSituacion['valor'] = '';
  filtros: ValoresFiltro = {};
  camposFiltro: CampoFiltro[] = [
    { clave: 'sede_id', etiqueta: 'Sede', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'area_id', etiqueta: 'Área', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
  ];

  @ViewChild(DataTableComponent) private tabla?: DataTableComponent<FilaGratificacion>;

  readonly columnas: ColumnaTabla<FilaGratificacion>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '26%' },
    { campo: 'meses', header: 'Meses', ancho: '12%' },
    { campo: 'gratificacion', header: 'Le corresponde', ancho: '20%' },
    { campo: 'pagado_total', header: 'En su planilla', ancho: '18%' },
    {
      campo: 'estado_grati', header: 'Situación', tipo: 'badge', ancho: '12%',
      formatear: (_v, f) => ESTADOS[f.estado_grati].texto,
      badgeSeveridad: (_v, f) => ESTADOS[f.estado_grati].tono,
    },
  ];

  readonly acciones: AccionPersonalizada<FilaGratificacion>[] = [
    { id: 'ver', titulo: 'Ver el cálculo', icono: 'eye' },
    { id: 'hoja', titulo: 'Descargar la hoja de gratificación', icono: 'download', visible: (f) => f.aplica },
  ];

  orden = '';
  readonly opcionesDeOrden: OpcionOrden[] = [
    { valor: 'apellido', etiqueta: 'Apellidos de la A a la Z' },
    { valor: 'apellido_desc', etiqueta: 'Apellidos de la Z a la A' },
    { valor: 'nombre', etiqueta: 'Nombres de la A a la Z' },
    { valor: 'nombre_desc', etiqueta: 'Nombres de la Z a la A' },
    { valor: 'total_desc', etiqueta: 'Gratificación de mayor a menor' },
    { valor: 'diferencia', etiqueta: 'Mayor diferencia primero' },
  ];

  ngOnInit(): void {
    const params = this.ruta.snapshot.queryParamMap;
    const anio = Number(params.get('anio'));
    const periodo = params.get('periodo');
    if (this.anios.includes(anio)) this.anio = anio;
    if (periodo === 'julio' || periodo === 'diciembre') this.periodo = periodo;
    this.cargar();
  }

  elegirPeriodo(periodo: PeriodoGratificacion): void {
    if (this.periodo === periodo) return;
    this.periodo = periodo;
    this.cargar();
  }

  /**
   * Cada periodo que ya se vio queda en memoria: volver a él es instantáneo
   * (se muestra al momento y se pone al día por detrás, en silencio). Al
   * abrir la pantalla se deja listo también el otro periodo, para que el
   * primer clic tampoco espere. Lo que cambia datos (entregar, calcular,
   * depositar) llama a recargar(), que borra la memoria.
   */
  private memoria = new Map<string, ListaGratificacion>();

  private clave(anio: number | null, periodo: PeriodoGratificacion | null): string | null {
    return anio && periodo ? `${anio}-${periodo}` : null;
  }

  cargar(): void {
    this.soltarMarcados();
    const clave = this.clave(this.anio, this.periodo);
    const guardado = clave ? this.memoria.get(clave) : undefined;
    if (guardado) {
      // Al momento, sin atenuar nada; luego se pone al día sin que se note.
      this.pedido?.unsubscribe();
      this.mostrar(guardado);
      this.pedido = this.servicio.lista(this.anio, this.periodo).subscribe({
        next: (res) => {
          if (!res.success) return;
          this.memoria.set(this.clave(res.data.anio, res.data.periodo)!, res.data);
          const sigueAqui = res.data.anio === this.anio && res.data.periodo === this.periodo;
          if (sigueAqui && JSON.stringify(res.data) !== JSON.stringify(this.datos)) this.mostrar(res.data);
        },
        error: () => {},
      });
      return;
    }

    this.empezarCarga();
    this.pedido = this.servicio.lista(this.anio, this.periodo).subscribe({
      next: (res) => {
        this.terminarCarga();
        if (!res.success) return;
        this.memoria.set(this.clave(res.data.anio, res.data.periodo)!, res.data);
        this.mostrar(res.data);
        this.precargarElOtro();
      },
      error: (err) => {
        this.terminarCarga();
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo revisar la gratificación.'));
      },
    });
  }

  /** Después de entregar, calcular o depositar: los datos de memoria ya no sirven. */
  recargar(): void {
    this.memoria.clear();
    this.cargar();
  }

  /** Deja listo el otro periodo del año, sin mostrarlo. */
  private precargarElOtro(): void {
    const otro: PeriodoGratificacion = this.periodo === 'julio' ? 'diciembre' : 'julio';
    if (!this.anio || this.memoria.has(this.clave(this.anio, otro)!)) return;
    const anio = this.anio;
    this.servicio.lista(anio, otro).subscribe({
      next: (res) => { if (res.success) this.memoria.set(this.clave(res.data.anio, res.data.periodo)!, res.data); },
      error: () => {},
    });
  }

  private mostrar(datos: ListaGratificacion): void {
    this.datos = datos;
    this.anio = datos.anio;
    this.periodo = datos.periodo;
    this.ponerEnLaDireccion();
    this.armarResumen();
    this.ponerOpcionesDeFiltro();
    this.aplicarFiltro();
    if (this.detalle) {
      this.detalle = this.datos.filas.find((f) => f.id === this.detalle!.id) ?? null;
      if (!this.detalle) this.panelAbierto = false;
    }
  }

  /** Las cifras, los pasos y los chips: una vez por carga. */
  private armarResumen(): void {
    const d = this.datos!;
    const r = d.resumen;
    const filas = d.filas;
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const [a, m, dia] = d.limite.split('-').map(Number);
    this.diasParaLimite = Math.round((new Date(a, m - 1, dia).getTime() - hoy.getTime()) / 86_400_000);
    this.rangoSemestre = d.periodo === 'julio' ? `enero – junio ${d.anio}` : `julio – diciembre ${d.anio}`;
    const soles = (v: number) => 'S/ ' + v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const mesPago = d.periodo === 'julio' ? 'julio' : 'diciembre';

    this.cifras = [
      { icono: 'people', valor: r.con_derecho, etiqueta: 'Con gratificación' },
      { icono: 'warning', valor: r.diferencias, etiqueta: 'Con diferencia', tono: 'warning' },
      { icono: 'check_circle', valor: r.pagadas, etiqueta: 'Pagadas', tono: 'success' },
    ];

    const pagado = r.con_derecho > 0 && r.por_pagar === 0;
    const sinDiferencias = pagado && r.diferencias === 0;
    const fase = (hecho: boolean, anteriorHecho: boolean): PasoCts['fase'] => (hecho ? 'hecho' : anteriorHecho ? 'actual' : 'espera');

    this.pasos = [
      {
        numero: 1, icono: 'table_chart', titulo: `Generar la planilla de ${mesPago}`,
        explicacion: `La gratificación se paga en la planilla de ${mesPago} (la trunca, en la del mes del cese) y sale en la boleta.`,
        estado: r.con_derecho === 0 ? 'Nadie tiene gratificación en este semestre.'
          : r.por_pagar ? `${r.por_pagar} de ${r.con_derecho} todavía sin planilla · ${soles(r.total)} en total`
          : `En planilla · ${soles(r.pagado)}`,
        fase: fase(pagado, true),
        avance: r.con_derecho ? Math.round(((r.con_derecho - r.por_pagar) / r.con_derecho) * 100) : null,
      },
      {
        numero: 2, icono: 'check_circle', titulo: 'Revisar las diferencias',
        explicacion: 'Cada planilla se compara con lo que dice la ley: meses completos, bonificación por cargo y el 9 %.',
        estado: !pagado ? 'Cuando esté la planilla.' : r.diferencias ? `${r.diferencias} con diferencia: revísalas` : 'Todo coincide con la ley',
        fase: fase(sinDiferencias, pagado),
        avance: null,
      },
      {
        numero: 3, icono: 'receipt_long', titulo: 'Entregar la hoja',
        explicacion: 'La hoja de gratificación llega a su expediente y la firma en Mis Documentos.',
        estado: r.entregadas ? `${r.entregadas} de ${r.pagadas} entregada(s)` : 'Todavía ninguna',
        fase: fase(r.pagadas > 0 && r.entregadas >= r.pagadas, sinDiferencias),
        avance: r.pagadas ? Math.round((r.entregadas / r.pagadas) * 100) : null,
      },
    ];

    const cuenta = (e: EstadoGratificacion) => filas.filter((f) => f.estado_grati === e).length;
    this.chips = [
      { valor: '' as const, texto: 'Todos', cuenta: filas.length },
      ...(Object.keys(ESTADOS) as EstadoGratificacion[]).map((e) => ({ valor: e, texto: ESTADOS[e].texto, cuenta: cuenta(e) })),
      { valor: 'trunca' as const, texto: 'Truncas (cesaron)', cuenta: r.truncas },
    ].filter((c) => c.cuenta > 0 || c.valor === '' || c.valor === this.situacion);
  }

  elegirSituacion(valor: ChipSituacion['valor']): void {
    this.situacion = valor;
    this.aplicarFiltro();
  }

  private ponerOpcionesDeFiltro(): void {
    const filas = this.datos?.filas ?? [];
    const poner = (clave: string, id: (f: FilaGratificacion) => string | null, nombre: (f: FilaGratificacion) => string | null) => {
      const unicos = new Map<string, string>();
      filas.forEach((f) => { const v = id(f); if (v) unicos.set(v, nombre(f) ?? v); });
      this.camposFiltro.find((c) => c.clave === clave)!.opciones = [...unicos]
        .map(([valor, etiqueta]) => ({ valor, etiqueta }))
        .sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
    };
    poner('sede_id', (f) => f.sede_id, (f) => f.sede);
    poner('area_id', (f) => f.area_id, (f) => f.area);
    poner('cargo_id', (f) => f.cargo_id, (f) => f.cargo);
    this.camposFiltro = [...this.camposFiltro];
  }

  private comparar(a: FilaGratificacion, b: FilaGratificacion): number {
    const texto = (x: string, y: string) => (x ?? '').localeCompare(y ?? '', 'es', { sensitivity: 'base' });
    switch (this.orden) {
      case 'apellido_desc': return texto(b.apellido, a.apellido) || texto(b.nombres, a.nombres);
      case 'nombre': return texto(a.nombres, b.nombres) || texto(a.apellido, b.apellido);
      case 'nombre_desc': return texto(b.nombres, a.nombres) || texto(b.apellido, a.apellido);
      case 'total_desc': return b.total - a.total;
      case 'diferencia': return Math.abs(b.diferencia) - Math.abs(a.diferencia);
      default: return texto(a.apellido, b.apellido) || texto(a.nombres, b.nombres);
    }
  }

  aplicarFiltro(): void {
    const v = this.filtros;
    this.filasVisibles = (this.datos?.filas ?? []).filter((f) =>
      (!this.situacion || (this.situacion === 'trunca' ? f.trunca : f.estado_grati === this.situacion))
      && (!v['sede_id'] || f.sede_id === v['sede_id'])
      && (!v['area_id'] || f.area_id === v['area_id'])
      && (!v['cargo_id'] || f.cargo_id === v['cargo_id']))
      .sort((a, b) => this.comparar(a, b));
  }

  get mensajeVacio(): string {
    return this.situacion || Object.values(this.filtros).some((v) => !!v)
      ? 'Nadie cumple estos filtros. Quita alguno con su «×» o con «Limpiar todo».'
      : 'No hay trabajadores en este semestre.';
  }

  estado(f: FilaGratificacion) {
    return ESTADOS[f.estado_grati];
  }

  /** «julio de 2026», o el mes del cese si es trunca. */
  mesDePago(f: FilaGratificacion): string {
    return `${nombreMes(f.mes_planilla).toLowerCase()} de ${f.anio_planilla}`;
  }

  verPlanillas(): void {
    if (!this.datos) return;
    this.router.navigate(['/inicio/planillas'], { queryParams: { mes: this.datos.periodo === 'julio' ? 7 : 12, anio: this.datos.anio } });
  }

  alAccionar(e: { accion: string; fila: FilaGratificacion }): void {
    if (e.accion === 'ver') this.abrirDetalle(e.fila);
    if (e.accion === 'hoja') this.bajarHoja(e.fila);
  }

  bajarHoja(f: FilaGratificacion): void {
    if (!this.datos) return;
    this.servicio.hoja(f.id, this.datos.anio, this.datos.periodo).subscribe({
      next: (blob) => guardarArchivo(blob, `Gratificación ${this.datos!.periodo} ${this.datos!.anio} - ${f.nombre}.pdf`),
      error: () => this.toast.error('No se descargó', 'No se pudo armar la hoja de gratificación.'),
    });
  }

  // ── Marcados y entrega de la hoja ──
  marcados: FilaGratificacion[] = [];
  entregando = false;

  get resumenMarcados(): string {
    return resumirMarcados(this.marcados.map((f) => f.nombre));
  }

  bajandoZip = false;

  /**
   * Las hojas de gratificación en un .zip: de los marcados o, sin marcados, de todo
   * el periodo. Lo baja el navegador (enlace firmado, como las boletas).
   */
  bajarHojasEnZip(filas?: { id: string }[]): void {
    if (this.bajandoZip || !this.anio || !this.periodo) return;
    this.bajandoZip = true;
    this.servicio.bajarHojasEnZip(this.anio, this.periodo, filas?.map((f) => f.id)).subscribe({
      next: (cantidad) => {
        this.bajandoZip = false;
        this.toast.success('Descarga iniciada', `${cantidad} hoja(s) de gratificación en el .zip.`);
      },
      error: (err) => {
        this.bajandoZip = false;
        this.toast.error('No se descargó', mensajeErrorApi(err, 'No se pudieron juntar las hojas.'));
      },
    });
  }

  soltarMarcados(): void {
    this.marcados = [];
    this.tabla?.limpiarSeleccion();
  }

  /** El botón del paso 3: todos los que la tienen pagada y sin hoja. */
  entregarPendientes(): void {
    this.entregar((this.datos?.filas ?? []).filter((f) => f.estado_grati === 'pagada' && !f.documento_id));
  }

  entregar(filas: FilaGratificacion[]): void {
    const datos = this.datos;
    const pagadas = filas.filter((f) => f.estado_grati === 'pagada');
    if (!datos || !pagadas.length) {
      this.toast.error('Nada que entregar', 'La hoja se entrega solo a quien ya tiene la gratificación pagada en su planilla, y sin diferencias.');
      return;
    }
    this.confirmar.confirmar({
      titulo: `Entregar la hoja de la gratificación de ${datos.periodo} ${datos.anio}`,
      mensaje: `La hoja de ${pagadas.length} trabajador(es) quedará en su expediente; la verán en Mis Documentos.`
        + (filas.length > pagadas.length ? ` ${filas.length - pagadas.length} se saltan: su gratificación no está pagada o tiene diferencia.` : ''),
      aceptarTexto: 'Sí, entregar',
      variante: 'default',
    }).then((aceptado) => {
      if (!aceptado) return;
      this.entregando = true;
      this.servicio.entregar(datos.anio, datos.periodo, pagadas.map((f) => f.id)).subscribe({
        next: (res) => {
          this.entregando = false;
          if (!res.success) return;
          this.toast.success('Hojas entregadas', res.data.message);
          this.recargar();
        },
        error: (err) => {
          this.entregando = false;
          this.toast.error('No se entregó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
        },
      });
    });
  }

  // ── El detalle, en el panel de la derecha ──
  detalle: FilaGratificacion | null = null;
  panelAbierto = false;
  historial: HistorialGratificacion | null = null;
  cargandoHistorial = false;
  barras: BarraMes[] = [];

  abrirDetalle(f: FilaGratificacion): void {
    this.detalle = f;
    this.panelAbierto = true;
    if (!this.datos) return;
    const pedido = f.id;
    this.historial = null;
    this.barras = [];
    this.cargandoHistorial = true;
    this.servicio.historial(f.id, this.datos.anio, this.datos.periodo).subscribe({
      next: (res) => {
        if (this.detalle?.id !== pedido) return;
        this.cargandoHistorial = false;
        if (!res.success) return;
        this.historial = res.data;
        const tope = Math.max(...res.data.meses.map((m) => m.ingresos), 1);
        const soles = (v: number) => 'S/ ' + v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        this.barras = res.data.meses.map((m) => ({
          mes: m,
          etiqueta: nombreMes(m.mes).slice(0, 3),
          ancho: Math.round((m.ingresos / tope) * 100),
          pista: m.situacion === 'planilla'
            ? `${nombreMes(m.mes)} ${m.anio}: ganó ${soles(m.ingresos)} y recibió ${soles(m.neto)}.`
            : m.situacion === 'sin_planilla'
              ? `${nombreMes(m.mes)} ${m.anio}: trabajó, pero ese mes no tiene planilla en el sistema.`
              : `${nombreMes(m.mes)} ${m.anio}: no trabajaba en el colegio.`,
        }));
      },
      error: () => (this.cargandoHistorial = false),
    });
  }
}
