import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import {
  SISTEMA_PENSIONES_OPCIONES,
  AFP_ENTIDAD_OPCIONES,
  FORMA_PAGO_OPCIONES,
} from '../../../../shared/constants';
import { SeccionEmpleadoBase } from './seccion-base';

/**
 * Paso 3: lo que necesita la planilla para calcular su boleta.
 *
 * La AFP y el CUSPP solo se piden cuando el sistema de pensiones es AFP —
 * el backend los exige justo en ese caso (`required_if:sistema_pensiones,AFP`),
 * y quien está en ONP no tiene ninguno de los dos.
 */
@Component({
  selector: 'app-seccion-planilla',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-planilla.component.html',
})
export class SeccionPlanillaComponent extends SeccionEmpleadoBase {
  sistemas = SISTEMA_PENSIONES_OPCIONES;
  afps = AFP_ENTIDAD_OPCIONES;
  formasPago = FORMA_PAGO_OPCIONES;

  get esAfp(): boolean {
    return this.form.get('sistema_pensiones')?.value === 'AFP';
  }

  get pagaPorBanco(): boolean {
    return this.form.get('forma_pago')?.value === 'banco';
  }
}
