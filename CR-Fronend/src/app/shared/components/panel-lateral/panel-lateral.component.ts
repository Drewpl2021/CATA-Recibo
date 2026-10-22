import { Component, EventEmitter, HostListener, Input, OnChanges, OnDestroy, Output } from '@angular/core';

import { IconComponent } from '../icon/icon.component';
import { AlCuerpoDirective } from '../../directives/al-cuerpo.directive';
import { soltarFondo, tomarFondo } from '../../utils/fondo-fijo';

/**
 * Un panel que entra desde la derecha, para ver el detalle de algo sin
 * salir de la lista: el contrato con lo que se le adjuntó, el rastro de una
 * boleta mes a mes.
 *
 * Es el primo de app-form-modal: mismo cierre con Escape o con un clic en el
 * fondo, el mismo fondo quieto, y se cuelga del <body> para quedar encima
 * del menú. La diferencia es que no tapa la pantalla: la lista de atrás se
 * sigue viendo, y uno no pierde dónde estaba.
 *
 *   <app-panel-lateral [(visible)]="abierto" titulo="Contrato" subtitulo="Del 1 de marzo…">
 *     …el detalle…
 *     <div panelPie>…botones…</div>
 *   </app-panel-lateral>
 */
@Component({
  selector: 'app-panel-lateral',
  standalone: true,
  imports: [IconComponent, AlCuerpoDirective],
  templateUrl: './panel-lateral.component.html',
})
export class PanelLateralComponent implements OnChanges, OnDestroy {
  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  @Input() titulo = '';
  @Input() subtitulo = '';
  /** Ícono de la cabecera, del mismo mapa que app-icon. */
  @Input() icono = '';
  @Input() ancho = '480px';

  @Output() cerrado = new EventEmitter<void>();

  private fondoTomado = false;
  private empezoFuera = false;

  cerrar(): void {
    this.visible = false;
    this.soltar();
    this.visibleChange.emit(false);
    this.cerrado.emit();
  }

  /**
   * Escape cierra el panel, pero solo si no hay un modal abierto encima (el
   * visor de un documento, Subir): Escape es para la capa de arriba, y el
   * modal ya se cierra solo.
   */
  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    if (!this.visible) return;
    if (document.querySelector('body > .modal-overlay')) return;
    this.cerrar();
  }

  alPresionarEnElFondo(evento: MouseEvent): void {
    this.empezoFuera = evento.target === evento.currentTarget;
  }

  alSoltarEnElFondo(evento: MouseEvent): void {
    if (this.empezoFuera && evento.target === evento.currentTarget) this.cerrar();
    this.empezoFuera = false;
  }

  ngOnChanges(): void {
    if (this.visible && !this.fondoTomado) {
      this.fondoTomado = true;
      tomarFondo();
    } else if (!this.visible) {
      this.soltar();
    }
  }

  ngOnDestroy(): void {
    this.soltar();
  }

  private soltar(): void {
    if (!this.fondoTomado) return;
    this.fondoTomado = false;
    soltarFondo();
  }
}
