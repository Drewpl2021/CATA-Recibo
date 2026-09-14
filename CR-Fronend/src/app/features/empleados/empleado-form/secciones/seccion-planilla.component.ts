import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import {
  SISTEMA_PENSIONES_OPCIONES,
  AFP_ENTIDAD_OPCIONES,
  FORMA_PAGO_OPCIONES,
  BANCOS_PERU,
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
  bancos = BANCOS_PERU;

  /**
   * Se encendió al pasar a honorarios y haber tenido que dejar la pensión en
   * "ninguna". Se avisa en pantalla: cambiar un dato que el usuario no tocó
   * sin decírselo es la clase de cosa que aparece después en un reclamo.
   */
  pensionLimpiadaPorHonorarios = false;

  get esAfp(): boolean {
    return this.form.get('sistema_pensiones')?.value === 'AFP';
  }

  /** Sin sistema de pensiones: no se le descuenta nada por ese lado. */
  get sinPension(): boolean {
    return !this.form.get('sistema_pensiones')?.value;
  }

  get esHonorarios(): boolean {
    return this.form.get('forma_pago')?.value === 'honorarios';
  }

  /**
   * Banco, cuenta y CCI.
   *
   * Salen tanto con depósito como con honorarios: el recibo por honorarios
   * dice con qué documento se paga, no por dónde llega la plata, y en la
   * práctica también se abona por transferencia.
   */
  get pideDatosBancarios(): boolean {
    const forma = this.form.get('forma_pago')?.value;
    return forma === 'banco' || forma === 'honorarios';
  }

  /**
   * Quien emite recibo por honorarios no está en planilla: es cuarta
   * categoría y no aporta a ninguna pensión. Si quedara en ONP se le
   * descontaría el 13% que no le corresponde, así que se deja sin sistema y
   * se le quitan la AFP y el CUSPP, igual que hace el backend.
   */
  alCambiarFormaPago(): void {
    this.pensionLimpiadaPorHonorarios = false;

    if (!this.esHonorarios) return;

    const pension = this.form.get('sistema_pensiones');
    if (!pension?.value) return;

    pension.setValue('');
    this.form.get('afp')?.setValue('');
    this.form.get('cuspp')?.setValue('');
    this.pensionLimpiadaPorHonorarios = true;
  }
}
