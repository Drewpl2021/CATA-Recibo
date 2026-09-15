import { Directive, Input, TemplateRef, inject } from '@angular/core';

/**
 * Una celda a medida dentro de <app-data-table>.
 *
 * La tabla sabe pintar texto, fechas, montos, badges e hitos. Cuando una
 * columna necesita otra cosa —un desplegable para elegir, una lista de
 * cambios—, la pantalla le pasa su propia plantilla con el mismo nombre de
 * campo, en vez de construirse una tabla HTML aparte:
 *
 *   <app-data-table [columnas]="columnas" [datos]="filas">
 *     <ng-template appCelda="accion" let-fila>
 *       <select class="form-control" [(ngModel)]="fila.accion">…</select>
 *     </ng-template>
 *   </app-data-table>
 *
 * Dentro de la plantilla, `let-fila` es la fila y `let-indice="indice"` su
 * posición en la página.
 */
@Directive({
  selector: 'ng-template[appCelda]',
  standalone: true,
})
export class CeldaTablaDirective {
  /** El `campo` de la columna a la que reemplaza. */
  @Input({ alias: 'appCelda', required: true }) campo = '';

  readonly plantilla = inject<TemplateRef<unknown>>(TemplateRef);
}
