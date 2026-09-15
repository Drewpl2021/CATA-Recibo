import { AbstractControl, ValidationErrors } from '@angular/forms';

/**
 * La contraseña tiene que llevar al menos una letra y un número.
 *
 * Es el espejo de Password::defaults() del backend (AppServiceProvider):
 * si solo lo exigiera el backend, la persona se enteraría al darle a
 * guardar. Del largo mínimo se encarga Validators.minLength(8), y de que
 * no esté vacía, Validators.required.
 */
export function claveConLetrasYNumeros(control: AbstractControl): ValidationErrors | null {
  const valor = String(control.value ?? '');
  if (!valor) return null;

  return tieneLetrasYNumeros(valor) ? null : { claveDebil: true };
}

/** La misma regla, para formularios sin FormGroup (el modal del menú del usuario). */
export function tieneLetrasYNumeros(valor: string): boolean {
  return /\p{L}/u.test(valor) && /\d/.test(valor);
}
