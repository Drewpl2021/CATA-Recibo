import { Component, EventEmitter, Input, Output } from '@angular/core';
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
export class FormModalComponent {
  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  @Input() titulo = '';
  @Input() ancho = '480px';
  @Input() guardando = false;
  @Input() textoGuardar = 'Guardar';
  @Input() ocultarBotonesPorDefecto = false;

  @Output() guardar = new EventEmitter<void>();
  @Output() cancelar = new EventEmitter<void>();

  cerrar(): void {
    if (this.guardando) return;
    this.visible = false;
    this.visibleChange.emit(false);
    this.cancelar.emit();
  }
}
