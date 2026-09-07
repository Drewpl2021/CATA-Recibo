import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { NIVEL_ESTUDIOS_OPCIONES } from '../../../../shared/constants';
import { SeccionEmpleadoBase } from './seccion-base';

/** Paso 4: estudios y a quién avisar. Todo opcional. */
@Component({
  selector: 'app-seccion-complementarios',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-complementarios.component.html',
})
export class SeccionComplementariosComponent extends SeccionEmpleadoBase {
  niveles = NIVEL_ESTUDIOS_OPCIONES;
}
