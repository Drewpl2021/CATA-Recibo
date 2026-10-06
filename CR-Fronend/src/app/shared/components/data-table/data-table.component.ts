import { AfterContentInit, ChangeDetectorRef, Component, ContentChildren, ElementRef, EventEmitter, Input, OnChanges, OnDestroy, Output, QueryList, SimpleChanges, TemplateRef, inject } from '@angular/core';
import { CeldaTablaDirective } from './celda-tabla.directive';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, debounceTime, distinctUntilChanged } from 'rxjs';
import { AccionPersonalizada, ColumnaTabla, leerCampo } from './data-table.models';
import { fechaHoraLegible, fechaLegible, formatoDiaHora } from '../../../core/utils';
import { IconComponent } from '../icon/icon.component';
import { PistaDirective } from '../../directives/pista.directive';

export type AccionFila = 'ver' | 'editar' | 'eliminar';

/**
 * Cómo se llama, para quien busca, cada campo por el que se puede buscar.
 *
 * Con esto el buscador dice "Buscar por nombre, DNI o cargo…" en vez de un
 * "Buscar..." suelto que obliga a probar a ver qué encuentra. Nombre y
 * apellido caen en la misma palabra a propósito: quien busca escribe el
 * nombre de la persona, no distingue entre las dos columnas.
 */
const NOMBRE_DEL_CAMPO: Record<string, string> = {
  nombre: 'nombre',
  apellido: 'nombre',
  name: 'nombre',
  dni: 'DNI',
  'empleado.nombre': 'trabajador',
  'empleado.apellido': 'trabajador',
  'empleado.dni': 'DNI',
  'cargo.nombre': 'cargo',
  'area.nombre': 'área',
  'usuario.email': 'correo',
  'paymentConcept.nombre': 'concepto',
  usuario_nombre: 'usuario',
  email: 'correo',
  descripcion: 'descripción',
  direccion: 'dirección',
  telefono: 'teléfono',
  observaciones: 'observaciones',
  motivo: 'motivo',
  ruta: 'ruta',
  titulo: 'título',
  mensaje: 'mensaje',
  tipo: 'tipo',
  tipo_contrato: 'tipo de contrato',
};

/** "cargo.nombre" → "cargo"; "fecha_ingreso" → "fecha ingreso". */
function nombreDelCampo(campo: string): string {
  if (NOMBRE_DEL_CAMPO[campo]) return NOMBRE_DEL_CAMPO[campo];
  const ultimo = campo.split('.').pop() ?? campo;
  return NOMBRE_DEL_CAMPO[ultimo] ?? ultimo.replace(/_/g, ' ');
}

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
  /**
   * Qué dice el buscador. Vacío lo arma solo con `camposBusqueda`; se pone a
   * mano cuando el nombre del campo no basta ("tipo" en Mis Documentos).
   */
  @Input() placeholderBusqueda = '';
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

  /** "Buscar por nombre, DNI o cargo…": por dónde busca, con sus palabras. */
  get textoBuscador(): string {
    if (this.placeholderBusqueda) return this.placeholderBusqueda;

    const nombres = [...new Set(this.camposBusqueda.map(nombreDelCampo))];
    if (!nombres.length) return 'Buscar…';

    const ultimo = nombres.pop()!;
    if (!nombres.length) return `Buscar por ${ultimo}…`;

    // "nombre u observaciones", no "nombre o observaciones".
    const o = /^h?o/i.test(ultimo) ? 'u' : 'o';
    return `Buscar por ${nombres.join(', ')} ${o} ${ultimo}…`;
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
  /**
   * Numerar las filas: 1, 2, 3...
   *
   * Prendido por defecto en TODA la app: antes cada pantalla lo pedía por
   * su cuenta y la mitad se quedaba sin número, así que una lista se veía
   * distinta de la de al lado sin ningún motivo. Es la única forma de decir
   * "mira la 87" o de saber por dónde vas sin ir contando con el dedo —y en
   * una lista corta no estorba, así que no hace falta decidir caso por caso.
   *
   * La cuenta NO se reinicia en cada página: en la página 2 de diez en diez
   * la primera fila es la 11, no la 1. Se puede apagar con
   * [numerarFilas]="false" en la pantalla que de verdad no lo necesite
   * (una tabla ya numerada por otra columna, por ejemplo).
   */
  @Input() numerarFilas = true;

  /**
   * Marcar filas con casillas, para actuar sobre unas cuantas.
   *
   * Lo pide RR.HH. cuando quiere hacerle algo a un grupo que no es un área
   * ni un cargo —"las madres", "los que entraron este año"—: eso no se puede
   * describir con un filtro, hay que señalarlos a mano.
   *
   * Lo marcado se recuerda al pasar de página y al buscar: RR.HH. busca a
   * "gatica", lo marca, busca a "mamani", lo marca, y actúa sobre los dos.
   * Antes se soltaba todo al cambiar de página y no había forma de juntar
   * gente de páginas distintas. La casilla de la cabecera sigue marcando
   * solo la página que se ve: nadie marca a quien no vio.
   */
  @Input() seleccionable = false;

  /** Se emite cada vez que cambia lo marcado, con las filas enteras. */
  @Output() cambioSeleccion = new EventEmitter<T[]>();

  /** Cuántos registros hay en total, según el backend. */
  @Input() totalElementos = 0;
  /** Página actual en base 0, la misma numeración que usa el backend. */
  @Input() pagina = 0;

  /** El usuario pidió otra página (base 0). */
  @Output() cambioPagina = new EventEmitter<number>();
  /** El usuario escribió en el buscador; sale ya con el retardo aplicado. */
  @Output() cambioBusqueda = new EventEmitter<string>();

  /**
   * La búsqueda que la pantalla tenía puesta al volver a ella (ver
   * EstadoListadoService). Se pone UNA vez, antes de que se escriba nada:
   * después manda lo que la persona va tecleando.
   */
  @Input() set busquedaInicial(valor: string | null | undefined) {
    if (!this.yaSeEscribio && valor) this.busqueda = valor;
  }
  private yaSeEscribio = false;

  @Output() verFila = new EventEmitter<T>();
  @Output() editarFila = new EventEmitter<T>();
  @Output() eliminarFila = new EventEmitter<T>();
  /** Se dispara al pulsar uno de los botones de accionesPersonalizadas. */
  @Output() accionPersonalizada = new EventEmitter<{ accion: string; fila: T }>();

  /**
   * Las celdas a medida que trae la pantalla (ver CeldaTablaDirective).
   *
   * Existen para que ninguna pantalla vuelva a construirse su propia tabla
   * HTML solo porque una columna necesitaba un desplegable: la importación
   * desde Excel lo hizo, y quedó con otro aspecto y otro código que mantener.
   */
  @ContentChildren(CeldaTablaDirective) private celdasAMedida?: QueryList<CeldaTablaDirective>;
  private plantillas = new Map<string, TemplateRef<unknown>>();

  /** La plantilla a medida de esta columna, si la pantalla puso una. */
  plantillaDe(columna: ColumnaTabla<T>): TemplateRef<unknown> | null {
    return this.plantillas.get(columna.campo) ?? null;
  }

  private registrarCeldas(): void {
    this.plantillas = new Map((this.celdasAMedida?.toArray() ?? []).map((c) => [c.campo, c.plantilla]));
  }

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
    this.registrarCeldas();
    this.celdasAMedida?.changes.subscribe(() => this.registrarCeldas());

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
    clearTimeout(this.temporizadorVacio);
  }

  /**
   * Cuántas filas de silueta pintar mientras carga: las mismas que va a
   * traer la página, hasta un tope, para que la tabla no dé un salto de
   * alto cuando lleguen los datos de verdad.
   */
  get filasEsqueleto(): number[] {
    // Solo en la carga de verdad: la primera vez, o cuando el filtro dejó
    // la tabla en cero. Si ya había filas —recargar tras aplicar un
    // concepto, cambiar de página, lo que sea— esas filas se quedan donde
    // estaban (las pinta filaPagina) y el esqueleto no se dibuja ENCIMA:
    // eso era el parpadeo raro, un instante con las barras grises apiladas
    // sobre una tabla que ya se veía bien. Que se note que algo cambió lo
    // hace .data-table--refrescando, sin mover ni una fila de su sitio.
    if (!this.cargando || this.filaPagina.length > 0) return [];

    // Tantas como va a traer la página, no menos: con ocho fijas la tabla
    // crecía dos filas al llegar los datos y la pantalla daba un salto.
    // Medido: el salto de maquetación pasaba de 0 a 0,02 en Empleados.
    const cuantas = Math.min(this.filasPorPagina, 12);
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

  /**
   * Lo marcado ahora mismo, por el id de cada fila, con la fila entera: la
   * de otra página ya no está en `datos`, y la pantalla igual necesita su
   * nombre para decir a quién se le va a aplicar.
   */
  private marcadas = new Map<unknown, T>();

  private idDe(fila: T): unknown {
    return (fila as Record<string, unknown>)['id'];
  }

  estaMarcada(fila: T): boolean {
    return this.marcadas.has(this.idDe(fila));
  }

  alternarFila(fila: T): void {
    const id = this.idDe(fila);
    if (this.marcadas.has(id)) {
      this.marcadas.delete(id);
    } else {
      this.marcadas.set(id, fila);
    }
    this.avisarSeleccion();
  }

  /** ¿Están marcadas TODAS las de esta página? */
  get todasMarcadas(): boolean {
    const pagina = this.filaPagina;
    return pagina.length > 0 && pagina.every((f) => this.marcadas.has(this.idDe(f)));
  }

  alternarTodas(): void {
    const pagina = this.filaPagina;
    if (this.todasMarcadas) {
      pagina.forEach((f) => this.marcadas.delete(this.idDe(f)));
    } else {
      pagina.forEach((f) => this.marcadas.set(this.idDe(f), f));
    }
    this.avisarSeleccion();
  }

  /**
   * Marca esas filas desde la pantalla. La usa Empleados al llegar desde el
   * aviso de "contrato vencido": la gente ya viene marcada para actuar.
   */
  marcarFilas(filas: T[]): void {
    filas.forEach((f) => this.marcadas.set(this.idDe(f), f));
    this.avisarSeleccion();
  }

  /** Deja la selección en blanco. La pantalla la llama tras actuar. */
  limpiarSeleccion(): void {
    this.marcadas.clear();
    this.avisarSeleccion();
  }

  private avisarSeleccion(): void {
    this.cambioSeleccion.emit([...this.marcadas.values()]);
  }

  /**
   * El número que le toca a esta fila, contando desde el principio de la
   * lista y no desde el principio de la página.
   */
  numeroDeFila(indice: number): number {
    return (this.paginaActual - 1) * this.filasPorPagina + indice + 1;
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
      // Lo marcado se queda al cambiar de página o al buscar (ver
      // `seleccionable`): la pantalla enseña a quiénes y se suelta con la X.
    } else if (changes['datos'] && !this.paginacionServidor) {
      this.establecerPaginaActual(1);
    }
    this.actualizarFilasEnPantalla();
  }

  /**
   * Lo que la plantilla pinta de verdad. Casi siempre es igual a
   * `filaPagina`; la excepción es el filtro que deja la tabla en cero.
   */
  filasEnPantalla: T[] = [];
  /** Mientras esto es true, filasEnPantalla todavía tiene las filas VIEJAS, apagándose. */
  saliendoAVacio = false;
  private temporizadorVacio?: ReturnType<typeof setTimeout>;

  /**
   * De filas a "no hay nada" la tabla se vaciaba de un salto: `filaPagina`
   * pasaba de diez elementos a cero en el mismo instante en que `cargando`
   * se apagaba, así que *ngFor las quitaba todas de encima sin transición
   * ninguna —el "parece forzado" que se reportó—. De un filtro con filas a
   * otro con filas no pasa esto: la cantidad cambia, pero siempre queda algo
   * pintado, y ESO si se ve suave.
   *
   * Por eso el arreglo es solo para el caso de "quedó en cero": las filas
   * viejas se quedan un instante más (apagándose con
   * .data-table--vaciando), y recién cuando termina esa salida se cambia de
   * verdad a filasEnPantalla = [] y aparece el mensaje vacío, que entra con
   * su propio fundido (.data-table tbody tr:not(.fila-esqueleto)).
   */
  private actualizarFilasEnPantalla(): void {
    const nuevas = this.filaPagina;

    if (nuevas.length === 0 && this.filasEnPantalla.length > 0 && !this.cargando) {
      this.saliendoAVacio = true;
      clearTimeout(this.temporizadorVacio);
      this.temporizadorVacio = setTimeout(() => {
        this.filasEnPantalla = [];
        this.saliendoAVacio = false;
      }, 160);
      return;
    }

    if (nuevas.length > 0 || !this.cargando) {
      clearTimeout(this.temporizadorVacio);
      this.saliendoAVacio = false;
      this.filasEnPantalla = nuevas;
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
    this.yaSeEscribio = true;
    if (this.paginacionServidor) {
      this.tecleo$.next(this.busqueda.trim());
      return;
    }
    this.establecerPaginaActual(1);
  }

  /** El botón «×»: limpia y devuelve el foco al campo, para seguir usándolo. */
  limpiarBusqueda(campo: HTMLInputElement): void {
    this.busqueda = '';
    this.onBuscar();
    campo.focus();
  }

  /**
   * Escape en el buscador, en dos pasos: si hay algo escrito, lo primero es
   * borrarlo sin quitar el foco (como el botón «×», por si se sigue
   * escribiendo); si ya estaba vacío, un segundo Escape suelta el foco y
   * ahí sí se "sale" del buscador del todo.
   */
  alPulsarEscapeEnBuscador(campo: HTMLInputElement): void {
    if (this.busqueda) {
      this.limpiarBusqueda(campo);
    } else {
      campo.blur();
    }
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
    // Antes con toLocaleString('es-PE'): el formato lo ponía el navegador de
    // cada quien. Ahora sale "dd/mm/aaaa hh:mm" en todas las máquinas.
    if (columna.tipo === 'fecha-hora' && crudo) return fechaHoraLegible(crudo);
    // 'hito' devuelve "dd/mm/aaaa hh:mm" y la plantilla lo parte en dos
    // líneas; sin fecha devuelve cadena vacía, que es lo que hace que se
    // pinte la raya en vez de la marca.
    if (columna.tipo === 'hito') {
      if (!crudo) return '';
      const f = new Date(crudo);
      return isNaN(f.getTime()) ? '' : formatoDiaHora(f);
    }
    // Con separador de miles: "S/ 9,114.40". Sin él, un sueldo de cinco
    // cifras y uno de cuatro se parecen demasiado en una columna, y el panel
    // de arriba —que sí lo lleva— parecía decir otra cosa que la tabla.
    if (columna.tipo === 'moneda' && crudo != null) {
      return `S/ ${Number(crudo).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    }
    if (columna.tipo === 'boolean') return crudo ? 'Sí' : 'No';
    return crudo;
  }

  claseBadge(fila: T, columna: ColumnaTabla<T>): string {
    const crudo = leerCampo(fila, columna.campo);
    const severidad = columna.badgeSeveridad ? columna.badgeSeveridad(crudo, fila) : 'secondary';
    return `status-badge status-badge-${severidad}`;
  }
}
