import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { NIVEL_ESTUDIOS_OPCIONES } from '../../../../shared/constants';
import { Documento } from '../../../../core/models';
import { SeccionEmpleadoBase } from './seccion-base';

/** Paso 4: estudios, hoja de vida y a quién avisar. Todo opcional. */
@Component({
  selector: 'app-seccion-complementarios',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-complementarios.component.html',
})
export class SeccionComplementariosComponent extends SeccionEmpleadoBase {
  niveles = NIVEL_ESTUDIOS_OPCIONES;

  /** La hoja de vida que ya tiene en su expediente, si la hay. */
  @Input() hojaDeVida: Documento | null = null;

  /**
   * El archivo no se sube al elegirlo: se avisa al formulario y este lo sube
   * DESPUÉS de guardar. En un alta todavía no existe el empleado al que
   * colgárselo, así que antes de guardar no hay a dónde mandarlo.
   */
  @Output() cvElegido = new EventEmitter<File | null>();

  /** Bajarse la que ya está guardada. */
  @Output() descargarCv = new EventEmitter<void>();

  /** Lo elegido, para poder enseñar su nombre antes de guardar. */
  archivo: File | null = null;

  alElegirArchivo(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    this.archivo = input.files?.[0] ?? null;
    this.cvElegido.emit(this.archivo);
  }

  /** Se limpia también el <input file>: si no, vuelve a ofrecer el mismo. */
  quitarArchivo(campo: HTMLInputElement): void {
    this.archivo = null;
    campo.value = '';
    this.cvElegido.emit(null);
  }

  get pesoLegible(): string {
    if (!this.archivo) return '';

    const kb = this.archivo.size / 1024;
    return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`;
  }
}
