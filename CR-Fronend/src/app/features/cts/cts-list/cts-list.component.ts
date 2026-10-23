import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ConfirmService, ToastService } from '../../../core/services';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { CtsService, EstadoCts, FilaCts, HistorialCts, ListaCts, MesHistorialCts, PeriodoCts } from '../../../core/services/planilla/cts.service';
import { fechaDeDia, guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { PanelLateralComponent } from '../../../shared/components/panel-lateral/panel-lateral.component';
import { BarraSeleccionComponent, resumirMarcados } from '../../../shared/components/barra-seleccion/barra-seleccion.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { FiltrosComponent } from '../../../shared/components/filtros/filtros.component';
import { OrdenComponent, OpcionOrden } from '../../../shared/components/orden/orden.component';
import { CampoFiltro, ValoresFiltro } from '../../../shared/components/filtros/filtros.models';
import { PistaDirective } from '../../../shared/directives/pista.directive';

/** Cómo se dice y se pinta cada estado de la CTS de alguien. */
const ESTADOS: Record<EstadoCts, { texto: string; tono: 'success' | 'info' | 'warning' | 'secondary' }> = {
  por_calcular: { texto: 'Por calcular', tono: 'warning' },
  calculada: { texto: 'Por depositar', tono: 'info' },
  depositada: { texto: 'Depositada', tono: 'success' },
  sin_derecho: { texto: 'Sin CTS', tono: 'secondary' },
};

/**
 * Un paso del recorrido de cada depósito: calcular, depositar, entregar la
 * hoja. «hecho» en verde, «actual» resaltado (lo que toca ahora), «espera»
 * apagado.
 */
export interface PasoCts {
  numero: number;
  titulo: string;
  explicacion: string;
  estado: string;
  icono: string;
  fase: 'hecho' | 'actual' | 'espera';
  /** 0 a 100, para la barrita; null si el paso no se mide así. */
  avance: number | null;
}

/** Un mes en el mapa del panel: su barra ya medida contra el mes que más ganó. */
interface BarraMes {
  mes: MesHistorialCts;
  etiqueta: string;
  /** Ancho de la barra, 0 a 100. */
  ancho: number;
  /** Lo que dice el globo al pasar el mouse. */
  pista: string;
}

/** Una fila del cuadro grande: un concepto con su monto en cada mes. */
interface FilaHistorial {
  nombre: string;
  montos: (number | null)[];
  /** En qué meses este monto entra en la CTS (se resalta la celda). */
  computable: boolean[];
  total?: boolean;
}

interface GrupoHistorial {
  titulo: string;
  filas: FilaHistorial[];
}

/** Lo que entra en la remuneración computable: del mes de cierre, y la gratificación de su mes. */
const COMPUTABLES_DEL_CIERRE = ['Remuneración básica', 'Asignación Familiar', 'Bonificación por Cargo'];
const GRATIFICACION = 'Gratificaciones Fiestas Patrias - Ley 29351 y 30334';

/** Un chip de situación sobre la tabla, con su cuenta. */
interface ChipSituacion {
  valor: EstadoCts | '';
  texto: string;
  cuenta: number;
}

/**
 * CTS: la Compensación por Tiempo de Servicios de cada semestre.
 *
 * Se mira un depósito (mayo o noviembre de un año). Primero la lista es una
 * vista previa; «Calcular» la guarda. Después se marca a quienes ya se les
 * depositó y se registra el depósito: su hoja de liquidación queda en el
 * expediente de cada uno.
 *
 * Las filas, las cifras y las columnas son propiedades y no getters: se
 * rehacen solo al cargar o al filtrar (si no, la tabla vuelve a la página 1
 * y redibuja los íconos en cada ciclo).
 */
@Component({
  selector: 'app-cts-list',
  standalone: true,
  imports: [
    PistaDirective, CommonModule, FormsModule, IconComponent, PageHeaderComponent, DataTableComponent, CeldaTablaDirective,
    FiltrosComponent, OrdenComponent, FormModalComponent, PanelLateralComponent, BarraSeleccionComponent,
  ],
  templateUrl: './cts-list.component.html',
})
export class CtsListComponent implements OnInit {
  private servicio = inject(CtsService);
  private toast = inject(ToastService);
  private confirmar = inject(ConfirmService);
  private progreso = inject(ProgresoService);
  private router = inject(Router);
  private ruta = inject(ActivatedRoute);

  readonly anioActual = new Date().getFullYear();
  readonly anios = [this.anioActual + 1, this.anioActual, this.anioActual - 1, this.anioActual - 2];
  readonly periodos: { valor: PeriodoCts; titulo: string; detalle: string }[] = [
    { valor: 'mayo', titulo: 'Mayo', detalle: 'noviembre – abril' },
    { valor: 'noviembre', titulo: 'Noviembre', detalle: 'mayo – octubre' },
  ];
  /** null hasta la primera carga: el servidor elige el próximo depósito. */
  anio: number | null = null;
  periodo: PeriodoCts | null = null;

  datos: ListaCts | null = null;
  cargando = false;
  cifras: CifraCabecera[] = [];
  pasos: PasoCts[] = [];
  chips: ChipSituacion[] = [];
  /** Días que faltan para el límite del depósito; negativo si ya pasó. */
  diasParaLimite = 0;
  /** «mayo – octubre 2026» o «noviembre 2025 – abril 2026», para los títulos. */
  rangoSemestre = '';
  /** Lo que falta depositar del semestre. */
  porDepositar = 0;
  filasVisibles: FilaCts[] = [];
  readonly fecha = fechaDeDia;

  /** La situación va en chips sobre la tabla (se ve de un golpe); el resto, en el embudo. */
  situacion: EstadoCts | '' = '';
  filtros: ValoresFiltro = {};
  camposFiltro: CampoFiltro[] = [
    { clave: 'sede_id', etiqueta: 'Sede', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'area_id', etiqueta: 'Área', tipo: 'opciones', vacio: 'Todas', opciones: [] },
    { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'opciones', vacio: 'Todos', opciones: [] },
  ];

  @ViewChild(DataTableComponent) private tabla?: DataTableComponent<FilaCts>;

  readonly columnas: ColumnaTabla<FilaCts>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '28%' },
    { campo: 'meses', header: 'Tiempo', ancho: '15%' },
    { campo: 'remuneracion_computable', header: 'Remuneración computable', ancho: '19%' },
    { campo: 'monto', header: 'CTS', ancho: '15%' },
    {
      campo: 'estado_cts', header: 'Situación', tipo: 'badge', ancho: '13%',
      formatear: (_v, f) => ESTADOS[f.estado_cts].texto,
      badgeSeveridad: (_v, f) => ESTADOS[f.estado_cts].tono,
    },
  ];

  readonly acciones: AccionPersonalizada<FilaCts>[] = [
    { id: 'ver', titulo: 'Ver el cálculo', icono: 'eye' },
    { id: 'hoja', titulo: 'Descargar la hoja de liquidación', icono: 'download', visible: (f) => !!f.liquidacion_id },
  ];

  ngOnInit(): void {
    // Al volver a la pantalla, sigue en el depósito que se miraba.
    const params = this.ruta.snapshot.queryParamMap;
    const anio = Number(params.get('anio'));
    const periodo = params.get('periodo');
    if (this.anios.includes(anio)) this.anio = anio;
    if (periodo === 'mayo' || periodo === 'noviembre') this.periodo = periodo;
    this.cargar();
  }

  elegirPeriodo(periodo: PeriodoCts): void {
    if (this.periodo === periodo || this.cargando) return;
    this.periodo = periodo;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.soltarMarcados();
    this.servicio.lista(this.anio, this.periodo).subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.datos = res.data;
        this.anio = res.data.anio;
        this.periodo = res.data.periodo;
        this.router.navigate([], { queryParams: { anio: this.anio, periodo: this.periodo }, queryParamsHandling: 'merge', replaceUrl: true });
        this.armarResumen();
        this.ponerOpcionesDeFiltro();
        this.aplicarFiltro();
        this.refrescarDetalle();
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo calcular la CTS.'));
      },
    });
  }

  /**
   * Las cifras de la cabecera, los tres pasos y los chips. Todo sale del
   * resumen que manda el servidor y se arma una vez por carga.
   */
  private armarResumen(): void {
    const d = this.datos!;
    const r = d.resumen;
    const filas = d.filas;
    const guardadas = r.calculadas + r.depositadas;
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const [a, m, dia] = d.limite.split('-').map(Number);
    this.diasParaLimite = Math.round((new Date(a, m - 1, dia).getTime() - hoy.getTime()) / 86_400_000);
    this.porDepositar = Math.max(0, Math.round((r.total - r.depositado) * 100) / 100);
    this.rangoSemestre = d.periodo === 'mayo'
      ? `noviembre ${d.anio - 1} – abril ${d.anio}`
      : `mayo – octubre ${d.anio}`;

    this.cifras = [
      { icono: 'people', valor: r.con_derecho, etiqueta: 'Con CTS' },
      { icono: 'warning', valor: r.por_calcular + r.calculadas, etiqueta: 'Sin depositar', tono: 'warning' },
      { icono: 'check_circle', valor: r.depositadas, etiqueta: 'Depositadas', tono: 'success' },
    ];

    const calculado = r.con_derecho > 0 && r.por_calcular === 0 && r.desactualizadas === 0;
    const depositado = r.con_derecho > 0 && r.depositadas === r.con_derecho;
    const fase = (hecho: boolean, anteriorHecho: boolean): PasoCts['fase'] => (hecho ? 'hecho' : anteriorHecho ? 'actual' : 'espera');
    const soles = (v: number) => 'S/ ' + v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    this.pasos = [
      {
        numero: 1, icono: 'refresh', titulo: 'Calcular',
        explicacion: 'El sistema saca la CTS de cada uno con su sueldo, su asignación familiar y la gratificación del semestre.',
        estado: r.con_derecho === 0 ? 'Nadie tiene CTS en este semestre.'
          : r.desactualizadas ? `${r.desactualizadas} cambiaron después del cálculo: vuelve a calcular.`
          : r.por_calcular ? `${r.por_calcular} de ${r.con_derecho} por calcular. Hasta entonces es una vista previa.`
          : `${guardadas} calculadas · ${soles(r.total)}`,
        fase: fase(calculado, true),
        avance: r.con_derecho ? Math.round(((r.con_derecho - r.por_calcular) / r.con_derecho) * 100) : null,
      },
      {
        numero: 2, icono: 'bank', titulo: 'Depositar en el banco',
        explicacion: 'Haz la transferencia a la cuenta CTS de cada uno y regístrala aquí con su fecha.',
        estado: depositado ? `Todo depositado · ${soles(r.depositado)}`
          : `${r.depositadas} de ${r.con_derecho} depositadas · faltan ${soles(this.porDepositar)}`,
        fase: fase(depositado, calculado),
        avance: r.con_derecho ? Math.round((r.depositadas / r.con_derecho) * 100) : null,
      },
      {
        numero: 3, icono: 'receipt_long', titulo: 'Entregar la hoja',
        explicacion: 'Al registrar el depósito, su hoja de liquidación llega a su expediente y la firma en Mis Documentos.',
        estado: r.depositadas ? `${r.depositadas} hoja(s) entregada(s)` : 'Todavía ninguna',
        fase: fase(depositado, depositado),
        avance: null,
      },
    ];

    const cuenta = (e: EstadoCts) => filas.filter((f) => f.estado_cts === e).length;
    this.chips = [
      { valor: '', texto: 'Todos', cuenta: filas.length },
      ...(Object.keys(ESTADOS) as EstadoCts[])
        .map((e) => ({ valor: e, texto: ESTADOS[e].texto, cuenta: cuenta(e) }))
        .filter((c) => c.cuenta > 0 || c.valor === this.situacion),
    ];
  }

  elegirSituacion(valor: EstadoCts | ''): void {
    this.situacion = valor;
    this.aplicarFiltro();
  }

  /** Las sedes, áreas y cargos que de verdad hay en la lista. */
  private ponerOpcionesDeFiltro(): void {
    const filas = this.datos?.filas ?? [];

    const poner = (clave: string, id: (f: FilaCts) => string | null, nombre: (f: FilaCts) => string | null) => {
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

  /**
   * El botón «Ordenar». Esta lista llega entera del servidor y se filtra
   * acá, así que se ordena acá también, sobre todas las filas que pasan el
   * filtro (no solo las de la página que se ve).
   */
  orden = '';
  readonly opcionesDeOrden: OpcionOrden[] = [
    { valor: 'apellido', etiqueta: 'Apellidos de la A a la Z' },
    { valor: 'apellido_desc', etiqueta: 'Apellidos de la Z a la A' },
    { valor: 'nombre', etiqueta: 'Nombres de la A a la Z' },
    { valor: 'nombre_desc', etiqueta: 'Nombres de la Z a la A' },
    { valor: 'monto_desc', etiqueta: 'CTS de mayor a menor' },
    { valor: 'monto', etiqueta: 'CTS de menor a mayor' },
    { valor: 'ingreso', etiqueta: 'Más antiguos en el colegio' },
  ];

  private comparar(a: FilaCts, b: FilaCts): number {
    const texto = (x: string, y: string) => (x ?? '').localeCompare(y ?? '', 'es', { sensitivity: 'base' });
    switch (this.orden) {
      case 'apellido_desc': return texto(b.apellido, a.apellido) || texto(b.nombres, a.nombres);
      case 'nombre': return texto(a.nombres, b.nombres) || texto(a.apellido, b.apellido);
      case 'nombre_desc': return texto(b.nombres, a.nombres) || texto(b.apellido, a.apellido);
      case 'monto_desc': return b.monto - a.monto;
      case 'monto': return a.monto - b.monto;
      case 'ingreso': return texto(a.fecha_ingreso, b.fecha_ingreso);
      default: return texto(a.apellido, b.apellido) || texto(a.nombres, b.nombres);
    }
  }

  aplicarFiltro(): void {
    const v = this.filtros;
    this.filasVisibles = (this.datos?.filas ?? []).filter((f) =>
      (!this.situacion || f.estado_cts === this.situacion)
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

  tiempo(f: FilaCts): string {
    const partes = [];
    if (f.meses) partes.push(`${f.meses} mes${f.meses === 1 ? '' : 'es'}`);
    if (f.dias) partes.push(`${f.dias} día${f.dias === 1 ? '' : 's'}`);
    return partes.join(' y ') || '—';
  }

  /** «octubre de 2026»: el mes del que sale la remuneración (el cierre o el del cese). */
  mesDeCierre(f: FilaCts): string {
    if (!f.hasta) return '';
    const [anio, mes] = f.hasta.split('-').map(Number);
    return `${nombreMes(mes).toLowerCase()} de ${anio}`;
  }

  /** La gratificación que entra en este depósito: la de diciembre para mayo, la de julio para noviembre. */
  get mesGratificacion(): string {
    if (!this.datos) return '';
    return this.datos.periodo === 'mayo' ? `diciembre ${this.datos.anio - 1}` : `julio ${this.datos.anio}`;
  }

  estado(f: FilaCts) {
    return ESTADOS[f.estado_cts];
  }

  alAccionar(e: { accion: string; fila: FilaCts }): void {
    if (e.accion === 'ver') this.abrirDetalle(e.fila);
    if (e.accion === 'hoja') this.bajarHoja(e.fila);
  }

  // ── Calcular ──
  calculando = false;

  calcular(): void {
    if (!this.datos) return;
    const { anio, periodo } = this.datos;
    this.confirmar.confirmar({
      titulo: `Calcular la CTS de ${periodo} ${anio}`,
      mensaje: 'Se guarda la CTS de todo el personal con derecho. Las ya depositadas no se tocan; las que estaban por depositar se vuelven a calcular con los datos de hoy.',
      aceptarTexto: 'Sí, calcular',
      variante: 'default',
    }).then((aceptado) => {
      if (!aceptado) return;
      this.calculando = true;
      this.progreso.seguir('Calculando la CTS', this.servicio.calcular(anio, periodo)).subscribe({
        next: (res) => {
          this.calculando = false;
          if (!res.success) return;
          const { calculadas, depositadas, total } = res.data;
          this.toast.success('CTS calculada',
            `${calculadas} trabajador(es) · S/ ${total.toFixed(2)} en total` + (depositadas ? ` · ${depositadas} ya depositada(s), sin tocar` : '') + '.');
          this.cargar();
        },
        error: (err) => {
          this.calculando = false;
          this.toast.error('No se calculó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
        },
      });
    });
  }

  // ── Los marcados y el depósito ──
  marcados: FilaCts[] = [];

  get resumenMarcados(): string {
    return resumirMarcados(this.marcados.map((f) => f.nombre));
  }

  bajandoZip = false;

  /**
   * Las hojas de CTS en un .zip: de los marcados o, sin marcados, de todo
   * el periodo. Lo baja el navegador (enlace firmado, como las boletas).
   */
  bajarHojasEnZip(filas?: { id: string }[]): void {
    if (this.bajandoZip || !this.anio || !this.periodo) return;
    this.bajandoZip = true;
    this.servicio.bajarHojasEnZip(this.anio, this.periodo, filas?.map((f) => f.id)).subscribe({
      next: (cantidad) => {
        this.bajandoZip = false;
        this.toast.success('Descarga iniciada', `${cantidad} hoja(s) de CTS en el .zip.`);
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

  modalDeposito = false;
  depositando = false;
  /** Las que se van a depositar: solo las calculadas y no depositadas. */
  aDepositar: FilaCts[] = [];
  deposito = { fecha: '', entidad: '', cuenta: '' };

  abrirDeposito(filas: FilaCts[]): void {
    this.aDepositar = filas.filter((f) => f.estado_cts === 'calculada' && f.liquidacion_id);
    if (!this.aDepositar.length) {
      this.toast.error('Nada que depositar', 'Primero calcula la CTS; las ya depositadas no se registran de nuevo.');
      return;
    }
    const una = this.aDepositar.length === 1 ? this.aDepositar[0] : null;
    this.deposito = {
      fecha: this.hoy(),
      entidad: una?.entidad_ficha ?? '',
      cuenta: una?.cuenta_ficha ?? '',
    };
    this.modalDeposito = true;
  }

  /** Hoy en la fecha local (toISOString daría mañana pasadas las 7 p. m. en Perú). */
  private hoy(): string {
    const d = new Date();
    const dos = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
  }

  /** El botón del paso 2: todos los que tienen su CTS calculada y aún sin depositar. */
  depositarPendientes(): void {
    this.abrirDeposito(this.datos?.filas ?? []);
  }

  get totalADepositar(): number {
    return this.aDepositar.reduce((s, f) => s + f.monto, 0);
  }

  registrarDeposito(): void {
    if (!this.deposito.fecha) {
      this.toast.error('Falta la fecha', 'Indica el día en que se hizo el depósito.');
      return;
    }
    const una = this.aDepositar.length === 1;
    this.depositando = true;
    this.servicio.depositar({
      ids: this.aDepositar.map((f) => f.liquidacion_id!),
      fecha_deposito: this.deposito.fecha,
      entidad_financiera: una ? this.deposito.entidad || null : null,
      numero_cuenta: una ? this.deposito.cuenta || null : null,
    }).subscribe({
      next: (res) => {
        this.depositando = false;
        if (!res.success) return;
        this.modalDeposito = false;
        this.toast.success('Depósito registrado', `${res.data.depositadas} hoja(s) de liquidación quedaron en el expediente.`);
        this.cargar();
      },
      error: (err) => {
        this.depositando = false;
        this.toast.error('No se registró', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }

  anularDeposito(f: FilaCts): void {
    if (!f.liquidacion_id) return;
    this.confirmar.confirmar({
      titulo: 'Anular el depósito',
      mensaje: `La CTS de ${f.nombre} vuelve a quedar por depositar y su hoja de liquidación sale del expediente. Úsalo solo si el depósito se registró por error.`,
      aceptarTexto: 'Sí, anular',
      variante: 'danger',
    }).then((aceptado) => {
      if (!aceptado) return;
      this.servicio.anularDeposito(f.liquidacion_id!).subscribe({
        next: () => {
          this.toast.success('Depósito anulado', `La CTS de ${f.nombre} quedó por depositar.`);
          this.cargar();
        },
        error: (err) => this.toast.error('No se anuló', mensajeErrorApi(err, 'Inténtalo de nuevo.')),
      });
    });
  }

  bajarHoja(f: FilaCts): void {
    if (!f.liquidacion_id || !this.datos) return;
    this.servicio.hoja(f.liquidacion_id).subscribe({
      next: (blob) => guardarArchivo(blob, `CTS ${this.datos!.periodo} ${this.datos!.anio} - ${f.nombre}.pdf`),
      error: () => this.toast.error('No se descargó', 'No se pudo armar la hoja de liquidación.'),
    });
  }

  // ── El detalle, en el panel de la derecha ──
  detalle: FilaCts | null = null;
  panelAbierto = false;

  abrirDetalle(f: FilaCts): void {
    this.detalle = f;
    this.panelAbierto = true;
    this.cargarHistorial(f);
  }

  // ── El historial del semestre: el mapa en el panel y el cuadro grande ──
  historial: HistorialCts | null = null;
  cargandoHistorial = false;
  barras: BarraMes[] = [];
  mesesCuadro: string[] = [];
  gruposHistorial: GrupoHistorial[] = [];
  totalesHistorial: FilaHistorial[] = [];
  modalHistorial = false;

  private cargarHistorial(f: FilaCts): void {
    if (!this.datos) return;
    const pedido = f.id;
    this.historial = null;
    this.barras = [];
    this.cargandoHistorial = true;
    this.servicio.historial(f.id, this.datos.anio, this.datos.periodo).subscribe({
      next: (res) => {
        // Si mientras tanto se abrió a otra persona, esta respuesta ya no sirve.
        if (this.detalle?.id !== pedido) return;
        this.cargandoHistorial = false;
        if (!res.success) return;
        this.historial = res.data;
        this.armarHistorial();
      },
      error: () => {
        this.cargandoHistorial = false;
      },
    });
  }

  /** Las barras del panel y las filas del cuadro, una vez por carga (no getters: ver la regla de las tablas). */
  private armarHistorial(): void {
    const meses = this.historial?.meses ?? [];
    const tope = Math.max(...meses.map((m) => m.ingresos), 1);
    const soles = (v: number) => 'S/ ' + v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    this.barras = meses.map((m) => ({
      mes: m,
      etiqueta: nombreMes(m.mes).slice(0, 3),
      ancho: Math.round((m.ingresos / tope) * 100),
      pista: m.situacion === 'planilla'
        ? `${nombreMes(m.mes)} ${m.anio}: ganó ${soles(m.ingresos)}, le descontaron ${soles(m.descuentos)} y recibió ${soles(m.neto)}.`
        : m.situacion === 'sin_planilla'
          ? `${nombreMes(m.mes)} ${m.anio}: trabajó, pero ese mes no tiene planilla en el sistema.`
          : `${nombreMes(m.mes)} ${m.anio}: todavía no trabajaba en el colegio.`,
    }));

    this.mesesCuadro = meses.map((m) => `${nombreMes(m.mes).slice(0, 3)} ${m.anio}`);

    // Los conceptos en el orden de la boleta: ingresos, descuentos, adelantos y aportes del colegio.
    const fila = (nombre: string, monto: (m: MesHistorialCts) => number | null, computable: (m: MesHistorialCts) => boolean, total = false): FilaHistorial => ({
      nombre, total,
      montos: meses.map((m) => (m.situacion === 'planilla' ? monto(m) : null)),
      computable: meses.map((m) => m.situacion === 'planilla' && computable(m)),
    });
    const delTipo = (tipos: string[]) => {
      const nombres = new Set<string>();
      meses.forEach((m) => m.conceptos.filter((c) => tipos.includes(c.tipo)).forEach((c) => nombres.add(c.nombre)));
      return [...nombres].map((nombre) => fila(
        nombre,
        (m) => m.conceptos.filter((c) => c.nombre === nombre).reduce((s, c) => s + c.monto, 0) || null,
        (m) => (m.es_cierre && COMPUTABLES_DEL_CIERRE.includes(nombre)) || (m.es_gratificacion && nombre === GRATIFICACION),
      ));
    };

    this.gruposHistorial = [
      { titulo: 'Ingresos', filas: [fila('Remuneración básica', (m) => m.sueldo_base, (m) => m.es_cierre), ...delTipo(['bonificacion'])] },
      { titulo: 'Descuentos', filas: delTipo(['descuento', 'adelanto']) },
      { titulo: 'Aportes del colegio', filas: delTipo(['aportacion']) },
    ].filter((g) => g.filas.length);

    this.totalesHistorial = [
      fila('Total ingresos', (m) => m.ingresos, () => false, true),
      fila('Total descuentos', (m) => m.descuentos, () => false, true),
      fila('Neto recibido', (m) => m.neto, () => false, true),
    ];
  }

  /** Tras recargar, el panel abierto muestra la fila nueva de la misma persona. */
  private refrescarDetalle(): void {
    if (this.detalle) this.detalle = this.datos?.filas.find((f) => f.id === this.detalle!.id) ?? null;
    if (!this.detalle) this.panelAbierto = false;
  }
}
