import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { Area, Cargo, Contrato, Sede } from '../../../../core/models';
import { TIPO_CONTRATO_OPCIONES, ESTADO_EMPLEADO_OPCIONES } from '../../../../shared/constants';
import { fechaLegible } from '../../../../core/utils';
import { SeccionEmpleadoBase } from './seccion-base';

/** Paso 2: su puesto dentro del colegio. */
@Component({
  selector: 'app-seccion-laborales',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-laborales.component.html',
})
export class SeccionLaboralesComponent extends SeccionEmpleadoBase {
  /** Catálogos que carga el componente padre una sola vez. */
  @Input() areas: Area[] = [];
  @Input() cargos: Cargo[] = [];
  @Input() sedes: Sede[] = [];

  /** Contratos que ya tiene. Vacío en un alta: todavía no existe ninguno. */
  @Input() contratos: Contrato[] = [];
  @Input() cargandoContratos = false;

  tiposContrato = TIPO_CONTRATO_OPCIONES;
  estados = ESTADO_EMPLEADO_OPCIONES;

  /**
   * Un contrato indeterminado no acaba, así que pedirle fecha de término no
   * tiene sentido. Los demás (plazo fijo, suplencia, prácticas) sí la llevan
   * y el backend la exige.
   */
  get llevaFechaFin(): boolean {
    const tipo = this.form.get('tipo_contrato')?.value;
    return this.esNuevo && !!tipo && tipo !== 'indeterminado';
  }

  /** El que está corriendo ahora, si lo hay. */
  get contratoVigente(): Contrato | undefined {
    return this.contratos.find((c) => c.estado === 'vigente');
  }

  /** Fecha en formato peruano, sin el desfase de zona horaria. */
  fecha = fechaLegible;

  /** Con qué color se pinta cada estado en la lista. */
  severidadEstado(estado: string): string {
    if (estado === 'vigente') return 'success';
    if (estado === 'renovado') return 'info';
    return 'secondary';
  }
}
