import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { NIVEL_ESTUDIOS_OPCIONES } from '../../../../shared/constants';
import { Documento } from '../../../../core/models';
import { SelectorArchivoComponent } from '../../../../shared/components/selector-archivo/selector-archivo.component';
import { SeccionEmpleadoBase } from './seccion-base';

/** Paso 4: estudios, hoja de vida y a quién avisar. Todo opcional. */
@Component({
  selector: 'app-seccion-complementarios',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, SelectorArchivoComponent],
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

  /** Lo elegido en el selector compartido (uno solo). */
  cv: File[] = [];

  get archivo(): File | null {
    return this.cv[0] ?? null;
  }

  alCambiarCv(archivos: File[]): void {
    this.cv = archivos;
    this.cvElegido.emit(this.archivo);
  }
}
