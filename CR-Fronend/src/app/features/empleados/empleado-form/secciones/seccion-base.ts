import { Directive, Input } from '@angular/core';
import { FormGroup } from '@angular/forms';

/**
 * Base de las secciones del formulario de Empleado.
 *
 * Todas comparten el MISMO FormGroup del componente padre: cada sección solo
 * pinta un puñado de campos, pero el formulario y su validación son uno solo.
 * Así el guardado es una sola llamada y el wizard puede saber en qué paso
 * está el error.
 *
 * Cada sección hereda de acá para no repetir el @Input ni el helper de
 * errores en los cinco archivos.
 */
@Directive()
export abstract class SeccionEmpleadoBase {
  @Input({ required: true }) form!: FormGroup;

  /** Solo lectura: en modo "ver" los campos se muestran deshabilitados. */
  @Input() soloLectura = false;

  /** Alta (true) o ficha ya existente (false). Cambia algunos textos. */
  @Input() esNuevo = true;

  /** ¿Mostrar el error de este campo? Solo si el usuario ya lo tocó. */
  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Texto del primer error del campo, para no repetir *ngIf por regla. */
  error(campo: string): string {
    const c = this.form.get(campo);
    if (!c || !c.errors) return '';
    const e = c.errors;
    if (e['required']) return 'Este dato es obligatorio.';
    if (e['email']) return 'Escribe un correo válido.';
    if (e['pattern']) return 'El formato no es válido.';
    if (e['maxlength']) return `Máximo ${e['maxlength'].requiredLength} caracteres.`;
    if (e['minlength']) return `Mínimo ${e['minlength'].requiredLength} caracteres.`;
    if (e['min']) return `No puede ser menor que ${e['min'].min}.`;
    if (e['fechaFutura']) return 'La fecha no puede ser futura.';
    return 'Revisa este dato.';
  }
}
