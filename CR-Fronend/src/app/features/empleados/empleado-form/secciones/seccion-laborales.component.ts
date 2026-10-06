import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { Area, Cargo, Contrato, Sede, TipoContrato } from '../../../../core/models';
import {
  ESTADO_EMPLEADO_OPCIONES,
  ESTADO_CONTRATO_OPCIONES,
} from '../../../../shared/constants';
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
  /** El catálogo administrable de Tipos de Contrato (pantalla Configuración). */
  @Input() tiposContrato: TipoContrato[] = [];

  /** Contratos que ya tiene. Vacío en un alta: todavía no existe ninguno. */
  @Input() contratos: Contrato[] = [];
  @Input() cargandoContratos = false;

  estados = ESTADO_EMPLEADO_OPCIONES;

  /** Se avisa una vez cuando el cambio de área dejó fuera al cargo elegido. */
  cargoLimpiadoPorElArea = false;

  /**
   * Los cargos que se pueden elegir en el área marcada.
   *
   * Son los acotados a esa área MÁS los comodines (los que no están acotados
   * a ninguna: practicantes, voluntarios). Sin área elegida salen todos.
   *
   * Se filtra en memoria: el formulario ya trae el catálogo entero al
   * abrirse, así que cambiar de área no cuesta una llamada más.
   */
  get cargosDelArea(): Cargo[] {
    const areaId = this.form.get('area_id')?.value;

    if (!areaId) {
      return this.cargos;
    }

    return this.cargos.filter(
      (c) => !c.areas?.length || c.areas.some((a) => a.id === areaId)
    );
  }

  /**
   * Al cambiar de área, un cargo que ya no encaja se quita.
   *
   * Dejarlo puesto sería peor que molesto: el desplegable mostraría un hueco
   * y el backend rechazaría el alta al final del formulario, con los cinco
   * pasos ya llenos.
   */
  alCambiarArea(): void {
    const cargoId = this.form.get('cargo_id')?.value;
    this.cargoLimpiadoPorElArea = false;

    if (!cargoId) {
      return;
    }

    if (!this.cargosDelArea.some((c) => c.id === cargoId)) {
      this.form.patchValue({ cargo_id: '' });
      this.cargoLimpiadoPorElArea = true;
    }
  }

  /**
   * Lo decide el catálogo (`requiere_fecha_fin`), no un valor fijo: un
   * contrato que no pide fecha de fin (hoy, Plazo indeterminado) no acaba,
   * así que pedírsela no tiene sentido. Los demás sí la llevan y el backend
   * la exige.
   */
  get llevaFechaFin(): boolean {
    const tipoId = this.form.get('tipo_contrato_id')?.value;
    return !!this.tiposContrato.find((t) => t.id === tipoId)?.requiere_fecha_fin;
  }

  /**
   * Se está cambiando el tipo de contrato de alguien que ya trabaja acá.
   *
   * Cambiarlo no es corregir un dato suyo: es firmar otro contrato. Al
   * guardar se cierra el de ahora y empieza uno nuevo, así que hay que
   * decirlo ANTES, no después.
   */
  get cambioDeContrato(): boolean {
    const tipoId = this.form.get('tipo_contrato_id')?.value;
    return !this.esNuevo && !!this.contratoVigente && !!tipoId
      && tipoId !== this.contratoVigente.tipo_contrato_id;
  }

  /** El que está corriendo ahora, si lo hay. */
  get contratoVigente(): Contrato | undefined {
    return this.contratos.find((c) => c.estado === 'vigente');
  }

  /** Fecha en formato peruano, sin el desfase de zona horaria. */
  fecha = fechaLegible;

  /** Con qué color se pinta cada estado en la lista. */
  /** Sigue como vigente pero su fecha de fin ya pasó: nadie lo renovó. */
  vencido(c: { estado: string; fecha_fin?: string | null }): boolean {
    const d = new Date();
    const dos = (n: number) => String(n).padStart(2, '0');
    const hoy = `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
    return c.estado === 'vigente' && !!c.fecha_fin && String(c.fecha_fin).slice(0, 10) < hoy;
  }

  severidadEstado(estado: string): string {
    if (estado === 'vigente') return 'success';
    if (estado === 'renovado') return 'info';
    return 'secondary';
  }

  /**
   * "vigente" → "Vigente", "plazo_fijo" → "Plazo fijo".
   *
   * Sale del mismo catálogo que llena los desplegables, así que la lista de
   * contratos y el formulario dicen exactamente lo mismo. Antes acá se
   * pintaba el valor crudo del backend y se disimulaba con un
   * `text-transform: capitalize`, que deja "Plazo Fijo" con dos mayúsculas
   * y no arregla el guion bajo.
   */
  etiquetaEstadoContrato(valor: string): string {
    return ESTADO_CONTRATO_OPCIONES.find((o) => o.value === valor)?.label ?? valor;
  }

  /** El nombre del tipo que está elegido ahora mismo en el formulario. */
  etiquetaTipoContratoElegido(): string {
    const id = this.form.get('tipo_contrato_id')?.value;
    return this.tiposContrato.find((t) => t.id === id)?.nombre ?? '';
  }
}
