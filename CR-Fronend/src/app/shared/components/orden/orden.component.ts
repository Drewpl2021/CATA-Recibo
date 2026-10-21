import { Component, EventEmitter, HostListener, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

import { IconComponent } from '../icon/icon.component';

/** Una forma de ordenar la tabla. `valor` es la clave que entiende el backend (?orden=). */
export interface OpcionOrden {
  valor: string;
  etiqueta: string;
}

/**
 * El botón «Ordenar» de una tabla, al lado del de Filtros.
 *
 * Se ve y se comporta como Filtros —mismo botón, mismo panel flotante, se
 * cierra con un clic fuera o con Esc— para que se lea como su hermano. La
 * primera opción es el orden de siempre: elegirla es «sin ordenar».
 *
 * El orden lo aplica el backend (ListadoPaginado::aplicarOrden): la tabla
 * viene por páginas, y ordenar acá dejaría en orden solo la que se ve.
 *
 * Uso:
 *   <app-orden tableActions [opciones]="opcionesDeOrden" [(valor)]="orden"
 *       (cambio)="alFiltrar()"></app-orden>
 */
@Component({
  selector: 'app-orden',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './orden.component.html',
})
export class OrdenComponent {
  @Input() opciones: OpcionOrden[] = [];

  /** La clave elegida; '' = el orden de siempre (la primera opción). */
  @Input() valor = '';
  @Output() valorChange = new EventEmitter<string>();

  /** Cambió el orden: la pantalla vuelve a la página 1 y recarga. */
  @Output() cambio = new EventEmitter<string>();

  abierto = false;

  /** Mismo cálculo que Filtros: hacia arriba si abajo no cabe. */
  haciaArriba = false;
  altoMaximo = 0;
  /**
   * Cuánto se corre el panel a la derecha para no salirse por la izquierda.
   * Cuelga del borde derecho del botón, y en el celular «Ordenar» queda en
   * medio de la fila: sus 280 px se salían de la pantalla.
   */
  corrimiento = 0;
  private boton?: HTMLElement;
  private readonly ALTO_MINIMO = 260;

  /** Ordenado por algo distinto de lo de siempre: el botón se pinta como activo. */
  get activo(): boolean {
    return !!this.valor && this.valor !== this.opciones[0]?.valor;
  }

  get etiquetaActual(): string {
    return this.opciones.find((o) => o.valor === this.valor)?.etiqueta ?? this.opciones[0]?.etiqueta ?? '';
  }

  alternar(evento: MouseEvent): void {
    evento.stopPropagation();
    this.abierto = !this.abierto;
    this.boton = evento.currentTarget as HTMLElement;
    this.medir();
  }

  elegir(opcion: OpcionOrden): void {
    // La primera es «la de siempre»: no hace falta mandarla al backend.
    const valor = opcion.valor === this.opciones[0]?.valor ? '' : opcion.valor;
    this.abierto = false;
    if (valor === this.valor) return;
    this.valor = valor;
    this.valorChange.emit(valor);
    this.cambio.emit(valor);
  }

  esLaElegida(opcion: OpcionOrden): boolean {
    return (this.valor || this.opciones[0]?.valor) === opcion.valor;
  }

  @HostListener('document:click')
  cerrar(): void {
    this.abierto = false;
  }

  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    this.abierto = false;
  }

  @HostListener('window:scroll')
  @HostListener('window:resize')
  alMoverseLaPantalla(): void {
    if (this.abierto) this.medir();
  }

  private medir(): void {
    if (!this.boton) return;
    const caja = this.boton.getBoundingClientRect();
    const debajo = window.innerHeight - caja.bottom - 16;
    const encima = caja.top - 16;
    this.haciaArriba = debajo < this.ALTO_MINIMO && encima > debajo;
    this.altoMaximo = Math.max(Math.round(this.haciaArriba ? encima : debajo), 180);

    const ancho = Math.min(280, window.innerWidth - 32);
    this.corrimiento = Math.max(0, Math.round(16 - (caja.right - ancho)));
  }
}
