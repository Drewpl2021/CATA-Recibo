import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormGroup } from '@angular/forms';
import { IconComponent } from '../icon/icon.component';
import { PasoWizard } from './wizard.models';

/**
 * Barra de pasos para formularios largos.
 *
 * Solo se encarga de la navegación y de avisar de errores; el contenido de
 * cada paso lo pone la pantalla que lo usa:
 *
 *   <app-wizard [pasos]="pasos" [(actual)]="paso" [form]="form"></app-wizard>
 *   <seccion-a *ngIf="pasos[paso].id === 'personales'" [form]="form"></seccion-a>
 *
 * Si se le pasa el `form` y cada paso declara sus `campos`, marca en rojo el
 * paso que tenga errores y no deja avanzar hasta corregirlos — así el usuario
 * se entera en el paso 1 y no al final, tras llenarlo todo.
 */
@Component({
  selector: 'app-wizard',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './wizard.component.html',
})
export class WizardComponent {
  @Input({ required: true }) pasos: PasoWizard[] = [];
  @Input() actual = 0;
  @Output() actualChange = new EventEmitter<number>();

  /** Opcional: con él, el wizard puede validar antes de avanzar. */
  @Input() form?: FormGroup;

  get esUltimo(): boolean {
    return this.actual >= this.pasos.length - 1;
  }

  get esPrimero(): boolean {
    return this.actual <= 0;
  }

  get progreso(): number {
    if (this.pasos.length < 2) return 100;
    return (this.actual / (this.pasos.length - 1)) * 100;
  }

  /** ¿Este paso tiene algún campo inválido que el usuario ya tocó? */
  tieneErrores(indice: number): boolean {
    const paso = this.pasos[indice];
    if (!this.form || !paso?.campos?.length) return false;
    return paso.campos.some((campo) => {
      const c = this.form!.get(campo);
      return !!c && c.invalid && c.touched;
    });
  }

  completado(indice: number): boolean {
    return indice < this.actual && !this.tieneErrores(indice);
  }

  /** Se puede saltar a un paso ya visitado; hacia adelante hay que validar. */
  irA(indice: number): void {
    if (indice === this.actual) return;
    if (indice < this.actual) {
      this.actual = indice;
      this.actualChange.emit(indice);
      return;
    }
    for (let i = this.actual; i < indice; i++) {
      if (!this.validarPaso(i)) return;
    }
    this.actual = indice;
    this.actualChange.emit(indice);
  }

  siguiente(): void {
    if (this.esUltimo || !this.validarPaso(this.actual)) return;
    this.actual++;
    this.actualChange.emit(this.actual);
  }

  anterior(): void {
    if (this.esPrimero) return;
    this.actual--;
    this.actualChange.emit(this.actual);
  }

  /**
   * Marca como tocados los campos del paso para que se vean sus errores, y
   * responde si se puede seguir. Sin `form` no bloquea nada.
   */
  validarPaso(indice: number): boolean {
    const paso = this.pasos[indice];
    if (!this.form || !paso?.campos?.length) return true;

    let valido = true;
    paso.campos.forEach((campo) => {
      const c = this.form!.get(campo);
      if (!c) return;
      c.markAsTouched();
      if (c.invalid) valido = false;
    });
    return valido;
  }
}
