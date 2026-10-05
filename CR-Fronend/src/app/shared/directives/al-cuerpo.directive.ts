import { Directive, ElementRef, OnDestroy, OnInit, inject } from '@angular/core';

/**
 * Saca un modal de la pantalla y lo cuelga del <body>, encima de todo.
 *
 * El contenido de cada pantalla vive dentro de `.content-area`, que tiene
 * `view-transition-name` para el fundido entre pantallas (ver styles.scss).
 * Eso la vuelve su propia capa: un `position: fixed` con `z-index: 10000`
 * dentro de ella solo gana ADENTRO de la caja, y el menú lateral y la
 * cabecera —que están afuera— se le pintan encima. Así se veía la boleta en
 * Emisión: cortada por el menú a la izquierda y por la cabecera arriba, con
 * el fondo oscuro solo sobre el contenido.
 *
 *   <div class="modal-overlay" *ngIf="abierto" appAlCuerpo>…</div>
 *
 * Angular sigue manejándolo igual (eventos, bindings, el *ngIf que lo quita):
 * solo cambia dónde está en el DOM.
 */
@Directive({
  selector: '[appAlCuerpo]',
  standalone: true,
})
export class AlCuerpoDirective implements OnInit, OnDestroy {
  private readonly el = inject(ElementRef<HTMLElement>).nativeElement as HTMLElement;

  ngOnInit(): void {
    document.body.appendChild(this.el);
  }

  ngOnDestroy(): void {
    // Por si Angular lo busca en su sitio original al cerrar.
    this.el.remove();
  }
}
