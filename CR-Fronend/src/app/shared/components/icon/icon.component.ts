import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { getIconPath, resolverIconoModulo } from '../../icons/icon-map';

/**
 * Pinta un ícono del catálogo (shared/icons/icon-map).
 *
 * Es el ÚNICO sitio de la app donde se marca un SVG como confiable. Angular
 * limpia el contenido de [innerHTML] y borra los <path>/<circle>, así que un
 * ícono puesto a mano con [innerHTML] no se ve — que es justo lo que le
 * pasaba al sidebar.
 *
 * Se le puede pedir el ícono de dos maneras:
 *
 *   <app-icon icono="domain"></app-icon>              <!-- por clave -->
 *   <app-icon [nombreModulo]="m.nombre"               <!-- por nombre -->
 *             [icono]="m.icono"></app-icon>
 *
 * Con `nombreModulo` manda el nombre y el `icono` de la base de datos queda
 * como respaldo, así un módulo nuevo sale con ícono aunque nadie le haya
 * puesto uno. Ver resolverIconoModulo().
 */
@Component({
  selector: 'app-icon',
  standalone: true,
  imports: [CommonModule],
  template: `
    <svg
      xmlns="http://www.w3.org/2000/svg"
      [attr.width]="tamano"
      [attr.height]="tamano"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      [attr.stroke-width]="grosor"
      stroke-linecap="round"
      stroke-linejoin="round"
      [innerHTML]="svg"
      aria-hidden="true"
    ></svg>
  `,
  styles: [`
    :host {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      line-height: 0;
    }
  `],
})
export class IconComponent {
  private sanitizer = inject(DomSanitizer);

  /** Clave del catálogo (p. ej. "domain"). Respaldo si se pasa nombreModulo. */
  @Input() icono: string | null = '';

  /** Nombre del módulo; si viene, manda sobre `icono`. */
  @Input() nombreModulo: string | null = null;

  @Input() tamano = 20;
  @Input() grosor = 2;

  get svg(): SafeHtml {
    const clave = this.nombreModulo
      ? resolverIconoModulo(this.nombreModulo, this.icono)
      : this.icono;
    return this.sanitizer.bypassSecurityTrustHtml(getIconPath(clave));
  }
}
