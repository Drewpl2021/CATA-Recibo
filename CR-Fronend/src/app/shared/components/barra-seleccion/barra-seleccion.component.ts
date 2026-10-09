import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IconComponent } from '../icon/icon.component';
import { PistaDirective } from '../../directives/pista.directive';

/**
 * La barra de los marcados: aparece al marcar filas con la casilla de una
 * tabla y flota pegada abajo mientras se baja por la lista.
 *
 * Cada pantalla pone sus botones adentro (Activar, Exportar, Emitir…); la
 * barra pone lo que es igual en todas: cuántos hay, a quiénes, «Marcar los
 * N de todas las páginas» y la equis para soltarlos. La usan Empleados,
 * Planillas y Emisión de boletas.
 *
 *   <app-barra-seleccion [cantidad]="marcados.length" singular="trabajador marcado"
 *     plural="trabajadores marcados" [resumen]="..." [total]="total"
 *     (marcarTodos)="marcarTodos()" (soltar)="soltarMarcados()">
 *     <button class="barra-seleccion__boton" ...>Exportar</button>
 *   </app-barra-seleccion>
 */
@Component({
  selector: 'app-barra-seleccion',
  standalone: true,
  imports: [CommonModule, IconComponent, PistaDirective],
  template: `
    <div class="barra-seleccion" *ngIf="cantidad" role="region" [attr.aria-label]="'Acciones con los ' + plural">
      <span class="barra-seleccion__cuenta">{{ cantidad }}</span>
      <div class="barra-seleccion__texto">
        <strong>{{ cantidad === 1 ? singular : plural }}</strong>
        <span>{{ resumen }}</span>
        <!-- Lo marcado se suma página por página; esto marca de una vez a
             todos los que da el buscador y los filtros de ahora. -->
        <button *ngIf="total !== null && cantidad < total" type="button" class="barra-seleccion__todos"
          (click)="marcarTodos.emit()" [disabled]="ocupado || marcandoTodos"
          [appPista]="'Marca también a los de las demás páginas: los ' + total + ' que salen con el buscador y los filtros de ahora.'">
          {{ marcandoTodos ? 'Marcando…' : 'Marcar los ' + total + ' de todas las páginas' }}
        </button>
      </div>
      <div class="barra-seleccion__acciones">
        <ng-content></ng-content>
        <button type="button" class="barra-seleccion__cerrar" (click)="soltar.emit()" [disabled]="ocupado"
          appPista="Desmarca a todos. No cambia nada de ellos." aria-label="Quitar la selección">
          <app-icon icono="close" [tamano]="18"></app-icon>
        </button>
      </div>
    </div>
  `,
})
export class BarraSeleccionComponent {
  @Input() cantidad = 0;
  @Input() singular = 'marcado';
  @Input() plural = 'marcados';
  /** A quiénes, en corto: «Ana, Luis, Rosa y 4 más». */
  @Input() resumen = '';
  /** Cuántos da la lista con el buscador y los filtros; null si no se sabe. */
  @Input() total: number | null = null;
  /** Mientras se aplica una acción: los botones se apagan. */
  @Input() ocupado = false;
  @Input() marcandoTodos = false;

  @Output() marcarTodos = new EventEmitter<void>();
  @Output() soltar = new EventEmitter<void>();
}

/** «Ana, Luis, Rosa y 4 más.»: para el resumen de la barra. */
export function resumirMarcados(nombres: string[]): string {
  return nombres.length > 3 ? `${nombres.slice(0, 3).join(', ')} y ${nombres.length - 3} más.` : nombres.join(', ') + '.';
}
