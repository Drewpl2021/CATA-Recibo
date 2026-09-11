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

  const tieneLetra = /\p{L}/u.test(valor);
  const tieneNumero = /\d/.test(valor);

  return tieneLetra && tieneNumero ? null : { claveDebil: true };
}
