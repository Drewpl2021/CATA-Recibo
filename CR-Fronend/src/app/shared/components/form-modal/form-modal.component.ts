import { Component, EventEmitter, HostListener, Input, OnChanges, OnDestroy, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

/**
 * Cascarón reciclable para CUALQUIER formulario de crear/editar en modal.
 * No sabe nada del formulario que hospeda — solo pone el título, el botón
 * de cerrar, el pie con Cancelar/Guardar (con loading), y un <ng-content>
 * para el cuerpo del formulario. Sin dependencias de terceros — usa las
 * mismas clases .modal-overlay/.modal-container que ya usaba el layout.
 *
 * Uso típico:
 *   <app-form-modal [(visible)]="mostrarModal" [titulo]="'Nueva Área'"
 *       [guardando]="guardando" (guardar)="onGuardar()" (cancelar)="onCancelar()">
 *     <form [formGroup]="form"> ... </form>
 *   </app-form-modal>
 */
@Component({
  selector: 'app-form-modal',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './form-modal.component.html',
})
export class FormModalComponent implements OnChanges, OnDestroy {
  /**
   * Cuántos modales hay abiertos a la vez.
   *
   * El visor de un documento se abre ENCIMA del expediente, así que al
   * cerrar el de arriba el fondo no debe soltarse todavía. Por eso se
   * cuentan en vez de poner y quitar la clase a lo bruto.
   */
  private static abiertos = 0;

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  @Input() titulo = '';
  @Input() ancho = '480px';
  @Input() guardando = false;
  @Input() textoGuardar = 'Guardar';
  @Input() ocultarBotonesPorDefecto = false;
  /** Cabecera azul con letra blanca, como Mi perfil y Avisos. */
  @Input() cabeceraMarca = false;

  @Output() guardar = new EventEmitter<void>();
  @Output() cancelar = new EventEmitter<void>();

  cerrar(): void {
    if (this.guardando) return;
    this.visible = false;
    this.soltarElFondo();
    this.visibleChange.emit(false);
    this.cancelar.emit();
  }

  /**
   * Escape cierra, como en cualquier ventana.
   *
   * Faltaba: la única salida eran la equis o el clic fuera, y quien escribe
   * con el teclado tenía que ir a buscar el ratón.
   */
  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    if (this.visible) this.cerrar();
  }

  /**
   * Dónde EMPEZÓ el clic.
   *
   * Cerrar en cuanto se suelta el ratón sobre el fondo tenía una trampa: al
   * seleccionar un texto de dentro y soltar fuera, el modal se cerraba y se
   * perdía lo escrito. Ahora solo cierra si el clic empezó y terminó fuera.
   */
  private empezoFuera = false;

  alPresionarEnElFondo(evento: MouseEvent): void {
    this.empezoFuera = evento.target === evento.currentTarget;
  }

  alSoltarEnElFondo(evento: MouseEvent): void {
    if (this.empezoFuera && evento.target === evento.currentTarget) {
      this.cerrar();
    }
    this.empezoFuera = false;
  }

  ngOnChanges(): void {
    // El fondo no se mueve mientras hay un modal encima: con la rueda se
    // iba la pantalla de debajo y al cerrar aparecía en otro sitio.
    if (this.visible && !this.fondoTomado) {
      this.fondoTomado = true;
      FormModalComponent.abiertos++;
      document.body.classList.add('con-modal');
    } else if (!this.visible) {
      this.soltarElFondo();
    }
  }

  ngOnDestroy(): void {
    this.soltarElFondo();
  }

  private fondoTomado = false;

  private soltarElFondo(): void {
    if (!this.fondoTomado) return;

    this.fondoTomado = false;
    FormModalComponent.abiertos = Math.max(0, FormModalComponent.abiertos - 1);

    if (FormModalComponent.abiertos === 0) {
      document.body.classList.remove('con-modal');
    }
  }
}
