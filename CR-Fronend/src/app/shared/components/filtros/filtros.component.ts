import { Component, EventEmitter, HostListener, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { CampoFiltro, ValoresFiltro } from './filtros.models';

/**
 * El icono de filtros de una tabla, con su panel.
 *
 * Las tablas solo tenían un buscador por texto: para "los de Jerusalén con
 * plazo fijo" o "a quién le falta la boleta de este mes" había que bajar la
 * lista a Excel y filtrar allá. Esto pone esas preguntas en la pantalla.
 *
 * Dos decisiones:
 *
 * 1. Se aplica al instante, sin botón de "Buscar". Cada cambio avisa a la
 *    pantalla y ella recarga: se ve el efecto de lo que se acaba de elegir
 *    sin un paso de más.
 * 2. Lo que está puesto sale como chip AL LADO del icono, no escondido
 *    dentro del panel. Un embudo con un puntito no dice por qué la tabla
 *    tiene ocho filas en vez de ciento cincuenta; "Sede: Jerusalén" sí, y se
 *    quita de un clic.
 *
 * Uso:
 *   <app-filtros tableActions [campos]="camposDeFiltro" [(valores)]="filtros"
 *       (cambio)="pagina = 0; cargar()" (primeraApertura)="cargarCatalogos()">
 *   </app-filtros>
 */
@Component({
  selector: 'app-filtros',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './filtros.component.html',
})
export class FiltrosComponent {
  /** Qué se puede filtrar en esta tabla. */
  @Input() campos: CampoFiltro[] = [];

  /** Lo que está puesto. La pantalla es la dueña del valor. */
  @Input() valores: ValoresFiltro = {};
  @Output() valoresChange = new EventEmitter<ValoresFiltro>();

  /** Cambió un filtro: la pantalla vuelve a la página 1 y recarga. */
  @Output() cambio = new EventEmitter<ValoresFiltro>();

  /**
   * Se abrió el panel por primera vez.
   *
   * Los desplegables de área, cargo y sede necesitan sus catálogos, y
   * pedirlos al entrar a la pantalla sería cargar tres listas que quizá
   * nadie va a mirar. Se piden cuando se abre el panel.
   */
  @Output() primeraApertura = new EventEmitter<void>();

  abierto = false;
  private yaSeAbrio = false;

  /**
   * El panel cuelga del botón, y se decide de qué lado y con qué alto.
   *
   * Lo primero que probé fue `position: fixed` con coordenadas de pantalla,
   * para que el contenedor de la tabla no lo recortara. No sirve: alguna
   * caja de arriba lleva un `transform`, y eso convierte al elemento fijo en
   * hijo de ESA caja —medido: el panel salía 280 px más abajo de donde le
   * tocaba—. Así que va pegado al botón, y el recorte se destapa con el
   * `:has()` de styles.scss.
   */
  haciaArriba = false;

  /** Lo más alto que puede ser sin salirse de la pantalla. */
  altoMaximo = 0;

  private boton?: HTMLElement;

  /** Menos que esto debajo del botón y el panel se abre hacia arriba. */
  private readonly ALTO_MINIMO = 300;

  /** Cuántos chips se ven antes de resumir el resto en "+N". */
  private readonly CHIPS_A_LA_VISTA = 2;

  alternar(evento: MouseEvent): void {
    // El clic no sube: arriba está el cierre por "clic fuera", que lo
    // cerraría en el mismo golpe y el panel no abriría nunca.
    evento.stopPropagation();
    this.abierto = !this.abierto;
    this.boton = evento.currentTarget as HTMLElement;
    this.medir();

    if (this.abierto && !this.yaSeAbrio) {
      this.yaSeAbrio = true;
      this.primeraApertura.emit();
    }
  }

  @HostListener('document:click')
  cerrar(): void {
    this.abierto = false;
  }

  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    this.abierto = false;
  }

  /** Si la pantalla se mueve, el panel se mueve con su botón. */
  @HostListener('window:scroll')
  @HostListener('window:resize')
  alMoverseLaPantalla(): void {
    if (this.abierto) this.medir();
  }

  /**
   * Dónde cabe el panel.
   *
   * Debajo del botón si hay sitio; si no, hacia arriba. Y con un alto
   * máximo, porque con nueve filtros el panel medía más que la pantalla de
   * un portátil y los últimos quedaban fuera de la vista.
   */
  private medir(): void {
    if (!this.boton) return;

    const caja = this.boton.getBoundingClientRect();
    const debajo = window.innerHeight - caja.bottom - 16;
    const encima = caja.top - 16;

    this.haciaArriba = debajo < this.ALTO_MINIMO && encima > debajo;
    this.altoMaximo = Math.max(Math.round(this.haciaArriba ? encima : debajo), 180);
  }

  // ────────── Lo que está puesto ──────────

  get cuantosActivos(): number {
    return this.clavesActivas.length;
  }

  private get clavesActivas(): string[] {
    return Object.keys(this.valores).filter((k) => this.valores[k] !== '' && this.valores[k] != null);
  }

  /** Un chip por filtro puesto: "Sede: Jerusalén", "Sin sueldo". */
  get chips(): { clave: string; texto: string }[] {
    const lista: { clave: string; texto: string }[] = [];

    for (const campo of this.campos) {
      const valor = this.valores[campo.clave];
      const hasta = campo.claveHasta ? this.valores[campo.claveHasta] : '';

      if (campo.tipo === 'fecha') {
        if (valor) lista.push({ clave: campo.clave, texto: `${campo.etiqueta} desde ${this.enDia(valor)}` });
        if (hasta) lista.push({ clave: campo.claveHasta!, texto: `${campo.etiqueta} hasta ${this.enDia(hasta)}` });
        continue;
      }

      if (!valor) continue;

      if (campo.tipo === 'si-no') {
        lista.push({ clave: campo.clave, texto: campo.etiqueta });
        continue;
      }

      const opcion = campo.opciones?.find((o) => o.valor === valor);
      lista.push({ clave: campo.clave, texto: `${campo.etiqueta}: ${opcion?.etiqueta ?? valor}` });
    }

    return lista;
  }

  get chipsVisibles(): { clave: string; texto: string }[] {
    return this.chips.slice(0, this.CHIPS_A_LA_VISTA);
  }

  get chipsDeMas(): number {
    return Math.max(0, this.chips.length - this.CHIPS_A_LA_VISTA);
  }

  /** "2026-09-21" → "21/09/2026", que es como se lee acá. */
  private enDia(iso: string): string {
    const [anio, mes, dia] = iso.split('-');
    return dia ? `${dia}/${mes}/${anio}` : iso;
  }

  // ────────── Cambios ──────────

  valorDe(clave?: string): string {
    return (clave && this.valores[clave]) || '';
  }

  estaMarcado(clave: string): boolean {
    return this.valores[clave] === '1';
  }

  poner(clave: string, valor: string): void {
    const nuevos = { ...this.valores };

    if (valor === '' || valor == null) {
      delete nuevos[clave];
    } else {
      nuevos[clave] = valor;
    }

    this.avisar(nuevos);
  }

  alternarMarca(clave: string, marcado: boolean): void {
    this.poner(clave, marcado ? '1' : '');
  }

  quitar(clave: string): void {
    this.poner(clave, '');
  }

  limpiar(): void {
    this.avisar({});
  }

  private avisar(nuevos: ValoresFiltro): void {
    this.valores = nuevos;
    this.valoresChange.emit(nuevos);
    this.cambio.emit(nuevos);
  }
}
