import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IconComponent } from '../icon/icon.component';

/**
 * Cabecera reciclable para TODAS las pantallas del layout: ícono + título +
 * subtítulo, y un espacio a la derecha para los botones de la pantalla.
 *
 * El ícono se pide por el mismo nombre que usa el sidebar (modulos.icono del
 * backend), así "Áreas" muestra el mismo ícono arriba y en el menú.
 *
 *   <app-page-header icono="domain" titulo="Áreas"
 *                    subtitulo="Áreas académicas del colegio">
 *     <button headerActions class="btn-primary" (click)="nueva()">Nueva Área</button>
 *   </app-page-header>
 */
/** Una cifra de la cabecera: un número con su ícono y qué cuenta. */
export interface CifraCabecera {
  /** Clave del catálogo de íconos (shared/icons/icon-map). */
  icono: string;
  valor: number;
  /** Qué se está contando: "Total", "Activas", "De baja". */
  etiqueta: string;
  /** Colorea el ícono; por defecto el azul de marca. */
  tono?: 'brand' | 'success' | 'muted';
}

@Component({
  selector: 'app-page-header',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './page-header.component.html',
})
export class PageHeaderComponent {
  @Input({ required: true }) titulo = '';
  @Input() subtitulo = '';

  /** Clave del catálogo. Si no se pasa, se deduce del título. */
  @Input() icono = '';

  /**
   * Las cifras de la pantalla: cuántos hay en total, cuántos activos,
   * cuántos dados de baja. Van acá y no en tarjetas aparte porque esas
   * tarjetas repetían el ícono y el título que ya están en esta cabecera.
   *
   * Vacío las oculta, que es lo que necesitan las pantallas sin listado.
   */
  @Input() cifras: CifraCabecera[] = [];

  /** Mientras carga muestra un guion en vez de un cero que no es cierto. */
  @Input() cargandoCifras = false;
}
