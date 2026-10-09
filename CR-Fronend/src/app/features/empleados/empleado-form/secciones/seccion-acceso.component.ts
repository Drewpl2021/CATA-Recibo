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
 * ACCESO LE LLEGA A ESE CORREO (nunca el DNI). Por eso la pantalla lo dice — es
 * lo que RR.HH. tiene que comunicarle al trabajador.
 *
 * El rol no se elige: toda alta entra como empleado (el backend lo fuerza).
 * Al editar tampoco se toca desde acá: se cambia en la pantalla de Usuarios.
 */
@Component({
  selector: 'app-seccion-acceso',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-acceso.component.html',
})
export class SeccionAccesoComponent extends SeccionEmpleadoBase {
  @Input() roles: Rol[] = [];

  /** Si el catálogo trae el rol "empleado", que es con el que entra toda alta. */
  get hayRolEmpleado(): boolean {
    return !this.roles.length || this.roles.some((r) => r.nombre === 'empleado');
  }

  /** El DNI escrito en el paso 1 (ya no es la contraseña: el acceso va por correo). */
  get dni(): string {
    return this.form.get('dni')?.value || '';
  }
}
