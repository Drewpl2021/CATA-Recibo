import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { Rol } from '../../../../core/models';
import { SeccionEmpleadoBase } from './seccion-base';

/**
 * Paso 5: la cuenta con la que entrará al sistema.
 *
 * Al dar de alta un empleado, el backend le crea el usuario en la misma
 * operación: el correo que se ponga acá será con el que entre, y su
 * CONTRASEÑA INICIAL ES SU DNI. Por eso la pantalla lo dice en grande — es
 * lo que RR.HH. tiene que comunicarle al trabajador.
 *
 * Al editar, el rol ya no se toca desde acá (el backend lo ignora): se
 * cambia en la pantalla de Usuarios.
 */
@Component({
  selector: 'app-seccion-acceso',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-acceso.component.html',
})
export class SeccionAccesoComponent extends SeccionEmpleadoBase {
  @Input() roles: Rol[] = [];

  /** El DNI escrito en el paso 1, que será su contraseña inicial. */
  get dni(): string {
    return this.form.get('dni')?.value || '';
  }
}
