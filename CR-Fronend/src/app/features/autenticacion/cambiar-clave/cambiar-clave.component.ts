import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService, ToastService } from '../../../core/services';

/**
 * El cambio obligatorio del primer ingreso.
 *
 * Cuando RR.HH. da de alta a un empleado, su contraseña es su DNI — que está
 * en su ficha, en el listado y en su boleta, o sea que lo sabe medio colegio.
 * Hasta que ponga una suya, el backend responde 423 a todo lo demás, así que
 * esta pantalla no tiene salida más que cambiarla o cerrar sesión.
 *
 * Va sin el menú lateral a propósito: con la cuenta trabada no hay ninguna
 * otra pantalla a la que ir.
 */
@Component({
  selector: 'app-cambiar-clave',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './cambiar-clave.component.html',
  styleUrl: '../acceso.scss',
})
export class CambiarClaveComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private toast = inject(ToastService);
  private router = inject(Router);

  guardando = false;
  errorMsg = '';
  verPassword = false;

  /** Para saludar por su nombre a quien acaba de entrar. */
  nombre = this.authService.getUser()?.name ?? '';

  form = this.fb.group(
    {
      password_actual: ['', [Validators.required]],
      password_nuevo: ['', [Validators.required, Validators.minLength(8)]],
      password_nuevo_confirmation: ['', [Validators.required]],
    },
    { validators: (grupo: AbstractControl) => coincidenLasClaves(grupo) }
  );

  get actualVacia(): boolean {
    const c = this.form.get('password_actual');
    return !!c && c.touched && c.invalid;
  }

  get nuevaCorta(): boolean {
    const c = this.form.get('password_nuevo');
    return !!c && c.touched && c.hasError('minlength');
  }

  get noCoinciden(): boolean {
    const c = this.form.get('password_nuevo_confirmation');
    return !!c && c.touched && this.form.hasError('noCoinciden');
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.errorMsg = '';
    const crudo = this.form.getRawValue();

    this.authService
      .cambiarPassword({
        password_actual: crudo.password_actual!,
        password_nuevo: crudo.password_nuevo!,
        password_nuevo_confirmation: crudo.password_nuevo_confirmation!,
      })
      .subscribe({
        next: () => {
          this.guardando = false;
          this.toast.success('Contraseña cambiada', 'Ya puedes usar el sistema.');
          this.router.navigateByUrl(this.authService.rutaInicioSegunRol());
        },
        error: (err) => {
          this.guardando = false;
          this.errorMsg =
            err?.error?.errors?.password_actual?.[0] ||
            err?.error?.errors?.password_nuevo?.[0] ||
            err?.error?.message ||
            'No se pudo cambiar la contraseña. Intenta de nuevo.';
        },
      });
  }

  /** La única otra salida: irse. */
  salir(): void {
    this.authService.logout();
  }

  togglePassword(): void {
    this.verPassword = !this.verPassword;
  }
}

/** Las dos casillas de la nueva tienen que decir lo mismo. */
function coincidenLasClaves(grupo: AbstractControl): { noCoinciden: true } | null {
  const clave = grupo.get('password_nuevo')?.value;
  const repetida = grupo.get('password_nuevo_confirmation')?.value;
  return clave && repetida && clave !== repetida ? { noCoinciden: true } : null;
}
