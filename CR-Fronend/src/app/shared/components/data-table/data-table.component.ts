import { AfterContentInit, ChangeDetectorRef, Component, ElementRef, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, debounceTime, distinctUntilChanged } from 'rxjs';
import { AccionPersonalizada, ColumnaTabla, leerCampo } from './data-table.models';
import { fechaLegible } from '../../../core/utils';
import { IconComponent } from '../icon/icon.component';
import { PistaDirective } from '../../directives/pista.directive';

export type AccionFila = 'ver' | 'editar' | 'eliminar';

/**
 * Tabla reciclable: cualquier pantalla de lista (Áreas, Cargos, Sedes,
 * Roles, Empleados, Periodos, ...) le pasa sus columnas + datos y listo —
 * trae buscador, paginado y columna de acciones ya resueltos, en vez de
 * que cada "-list.component.html" reescriba su propia tabla HTML.
 * Sin dependencias de terceros — usa las mismas clases .data-table /
 * .status-badge que ya existían en el CSS global.
 *
 * Uso típico:
 *   <app-data-table
 *     [columnas]="columnas" [datos]="areas" [cargando]="cargando"
 *     [camposBusqueda]="['nombre', 'descripcion']" [acciones]="['editar','eliminar']"
 *     (editarFila)="editar($event)" (eliminarFila)="eliminar($event)">
 *     <button tableActions class="btn-primary" (click)="nuevo()">+ Nueva Área</button>
 *   </app-data-table>
 */
@Component({
  selector: 'app-data-table',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, PistaDirective],
  templateUrl: './data-table.component.html',
})
export class DataTableComponent<T = any> implements AfterContentInit, OnChanges, OnDestroy {
  @Input() columnas: ColumnaTabla<T>[] = [];
  @Input() datos: T[] = [];
  @Input() cargando = false;
  @Input() camposBusqueda: string[] = [];
  @Input() acciones: AccionFila[] = ['editar', 'eliminar'];
  @Input() mensajeVacio = 'No hay registros para mostrar.';
  @Input() filasPorPagina = 10;
  @Input() mostrarBuscador = true;
  /** Botones extra de la pantalla, además de ver/editar/eliminar. */
  @Input() accionesPersonalizadas: AccionPersonalizada<T>[] = [];

  /**
   * Qué guarda esta tabla, en singular y con su artículo: "el área",
   * "este empleado", "la solicitud".
   *
   * Sirve para que las pistas de los botones digan qué hacen de verdad
   * ("Editar este empleado") en vez de un "Editar" suelto que no dice sobre
   * qué. Sin esto los textos quedan genéricos, que es como estaban.
   */
  @Input() entidad = '';

  get pistaVer(): string {
    return this.entidad ? `Ver ${this.entidad}` : 'Ver el detalle';
  }

  get pistaEditar(): string {
    return this.entidad ? `Editar ${this.entidad}` : 'Editar este registro';
  }

  /**
   * Casi todo el sistema da de baja en vez de borrar, pero el diálogo de
   * confirmación dice "Eliminar": la pista usa la misma palabra para que no
   * parezcan dos acciones distintas.
   */
  get pistaEliminar(): string {
    return this.entidad ? `Eliminar ${this.entidad}` : 'Eliminar este registro';
  }

  /**
   * Con `true`, quien pagina y busca es el backend: `datos` trae SOLO las
   * filas de la página actual, y la tabla se limita a pintarlas y a avisar
   * cuando el usuario cambia de página o escribe. Sin esto la tabla
   * recibiría diez filas y creería que ese es el total.
   *
   * Con `false` (por defecto) sigue funcionando como siempre, cortando y
   * filtrando en memoria: es lo que necesitan las listas cortas que ya
   * vienen completas desde otra pantalla.
   */
  @Input() paginacionServidor = false;
  /** Cuántos registros hay en total, según el backend. */
  @Input() totalElementos = 0;
  /** Página actual en base 0, la misma numeración que usa el backend. */
  @Input() pagina = 0;

  /** El usuario pidió otra página (base 0). */
  @Output() cambioPagina = new EventEmitter<number>();
  /** El usuario escribió en el buscador; sale ya con el retardo aplicado. */
  @Output() cambioBusqueda = new EventEmitter<string>();

  @Output() verFila = new EventEmitter<T>();
  @Output() editarFila = new EventEmitter<T>();
  @Output() eliminarFila = new EventEmitter<T>();
  /** Se dispara al pulsar uno de los botones de accionesPersonalizadas. */
  @Output() accionPersonalizada = new EventEmitter<{ accion: string; fila: T }>();

  /**
   * Si la barra de arriba tiene algo que enseñar: el buscador, o los botones
   * que mete la pantalla ("+ Nueva Área", los chips de filtro...).
   *
   * Sin esto, una tabla sin ninguna de las dos cosas —Mis Boletas, el detalle
   * de una planilla— dejaba una franja blanca vacía encima de la cabecera.
   */
  hayBarraSuperior = true;

  busqueda = '';
  paginaActual = 1;
  /**
   * Lo que hay escrito en el cuadro de "ir a la página". Se mantiene igual
   * a paginaActual salvo mientras el usuario está tecleando un número
   * distinto; solo se intenta saltar al confirmar (Enter o al salir del
   * campo), nunca en cada tecla.
   */
  paginaEscrita = 1;

  /**
   * El buscador no dispara una petición por tecla: espera a que el usuario
   * deje de escribir. Sin esto, "Mamani" son seis consultas y seis
   * respuestas que pueden llegar desordenadas.
   */
  private tecleo$ = new Subject<string>();
  private suscripcion?: Subscription;

  private host = inject(ElementRef<HTMLElement>);
  private cdr = inject(ChangeDetectorRef);

  /**
   * Los botones llegan como contenido proyectado con el atributo
   * `tableActions`, así que solo se sabe si los hay cuando ya están puestos.
   */
  ngAfterContentInit(): void {
    const hayBotones = !!(this.host.nativeElement as HTMLElement).querySelector('[tableActions]');
    this.hayBarraSuperior = (this.mostrarBuscador && this.camposBusqueda.length > 0) || hayBotones;
    this.cdr.detectChanges();
  }

  constructor() {
    this.suscripcion = this.tecleo$
      .pipe(debounceTime(350), distinctUntilChanged())
      .subscribe((termino) => this.cambioBusqueda.emit(termino));
  }

  ngOnDestroy(): void {
    this.suscripcion?.unsubscribe();
  }

  /**
   * Cuántas filas de silueta pintar mientras carga: las mismas que va a
   * traer la página, hasta un tope, para que la tabla no dé un salto de
   * alto cuando lleguen los datos de verdad.
   */
  get filasEsqueleto(): number[] {
    if (!this.cargando) return [];
    const cuantas = Math.min(this.filasPorPagina, 8);
    return Array.from({ length: cuantas }, (_, i) => i);
  }

  /**
   * Ancho de cada barra de la silueta. Se varía a propósito: barras todas
   * iguales parecen una tabla rota, no un texto que está por llegar.
   */
  anchoEsqueleto(columna: ColumnaTabla<T>): string {
    const anchos = ['85%', '60%', '72%', '45%', '90%', '55%'];
    const posicion = this.columnas.indexOf(columna);
    return anchos[posicion % anchos.length];
  }

  /** ¿Hay algo que pintar en la columna de acciones de esta fila? */
  hayAcciones(fila: T): boolean {
    return this.acciones.length > 0 || this.accionesVisibles(fila).length > 0;
  }

  accionesVisibles(fila: T): AccionPersonalizada<T>[] {
    return this.accionesPersonalizadas.filter((a) => !a.visible || a.visible(fila));
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['pagina'] && this.paginacionServidor) {
      // La página la manda la pantalla; acá solo se refleja.
      this.establecerPaginaActual(this.pagina + 1);
      return;
    }
    if (changes['datos'] && !this.paginacionServidor) {
      this.establecerPaginaActual(1);
    }
  }

  /** Cambia de página Y mantiene el cuadro de "ir a la página" al día. */
  private establecerPaginaActual(pagina: number): void {
    this.paginaActual = pagina;
    this.paginaEscrita = pagina;
  }

  get filaFiltradas(): T[] {
    if (!this.busqueda.trim() || !this.camposBusqueda.length) return this.datos;
    const termino = this.busqueda.trim().toLowerCase();
    return this.datos.filter((fila) =>
      this.camposBusqueda.some((campo) => String(leerCampo(fila, campo) ?? '').toLowerCase().includes(termino))
    );
  }

  /** Cuántos registros hay en total, los cuente el backend o esta tabla. */
  get totalRegistros(): number {
    return this.paginacionServidor ? this.totalElementos : this.filaFiltradas.length;
  }

  get totalPaginas(): number {
    return Math.max(1, Math.ceil(this.totalRegistros / this.filasPorPagina));
  }

  /** Las filas a pintar. Cuando pagina el servidor ya vienen cortadas. */
  get filaPagina(): T[] {
    if (this.paginacionServidor) return this.datos;
    const inicio = (this.paginaActual - 1) * this.filasPorPagina;
    return this.filaFiltradas.slice(inicio, inicio + this.filasPorPagina);
  }

  get mostrandoDesde(): number {
    return this.totalRegistros === 0 ? 0 : (this.paginaActual - 1) * this.filasPorPagina + 1;
  }

  get mostrandoHasta(): number {
    return Math.min(this.paginaActual * this.filasPorPagina, this.totalRegistros);
  }

  onBuscar(): void {
    if (this.paginacionServidor) {
      this.tecleo$.next(this.busqueda.trim());
      return;
    }
    this.establecerPaginaActual(1);
  }

  irAPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.totalPaginas) return;
    if (this.paginacionServidor) {
      // No se toca paginaActual: la pantalla recarga y vuelve por @Input.
      this.cambioPagina.emit(pagina - 1);
      return;
    }
    this.establecerPaginaActual(pagina);
  }

  /**
   * El usuario escribió un número en el cuadro de "ir a la página" y lo
   * confirmó (Enter o quitó el foco). Si no es un número válido dentro de
   * rango, se descarta y el cuadro vuelve a mostrar la página en la que
   * está — así nunca se llega a pedir una página inexistente.
   */
  irAPaginaEscrita(valor: number | string): void {
    const numero = Math.trunc(Number(valor));
    if (!Number.isFinite(numero) || numero < 1 || numero > this.totalPaginas || numero === this.paginaActual) {
      this.paginaEscrita = this.paginaActual;
      return;
    }
    this.irAPagina(numero);
  }

  valorCelda(fila: T, columna: ColumnaTabla<T>): any {
    const crudo = leerCampo(fila, columna.campo);
    if (columna.formatear) return columna.formatear(crudo, fila);
    if (columna.tipo === 'fecha' && crudo) return fechaLegible(crudo);
    if (columna.tipo === 'fecha-hora' && crudo) return new Date(crudo).toLocaleString('es-PE');
    if (columna.tipo === 'moneda' && crudo != null) return `S/ ${Number(crudo).toFixed(2)}`;
    if (columna.tipo === 'boolean') return crudo ? 'Sí' : 'No';
    return crudo;
  }

  claseBadge(fila: T, columna: ColumnaTabla<T>): string {
    const crudo = leerCampo(fila, columna.campo);
    const severidad = columna.badgeSeveridad ? columna.badgeSeveridad(crudo, fila) : 'secondary';
    return `status-badge status-badge-${severidad}`;
  }
}
