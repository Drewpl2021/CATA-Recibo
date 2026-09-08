import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/services';

/**
 * Donde aterriza el enlace del correo: poner la contraseña nueva.
 *
 * El token y el correo vienen en la URL. Si falta alguno, el enlace está roto
 * o alguien entró a mano; se dice y se manda a pedir uno nuevo, en vez de
 * enseñar un formulario que iba a fallar igual al enviarlo.
 */
@Component({
  selector: 'app-restablecer-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './restablecer-password.component.html',
  styleUrl: '../acceso.scss',
})
export class RestablecerPasswordComponent implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private ruta = inject(ActivatedRoute);

  token = '';
  email = '';
  enlaceValido = false;

  guardando = false;
  listo = false;
  errorMsg = '';
  verPassword = false;

  form = this.fb.group(
    {
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: (grupo: AbstractControl) => coincidenLasClaves(grupo) }
  );

  ngOnInit(): void {
    this.token = this.ruta.snapshot.queryParamMap.get('token') ?? '';
    this.email = this.ruta.snapshot.queryParamMap.get('email') ?? '';
    this.enlaceValido = !!this.token && !!this.email;
  }

  get passwordCorta(): boolean {
    const c = this.form.get('password');
    return !!c && c.touched && c.hasError('minlength');
  }

  get noCoinciden(): boolean {
    const c = this.form.get('password_confirmation');
    return !!c && c.touched && this.form.hasError('noCoinciden');
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.errorMsg = '';
    const { password, password_confirmation } = this.form.getRawValue();

    this.authService
      .restablecerPassword({
        token: this.token,
        email: this.email,
        password: password!,
        password_confirmation: password_confirmation!,
      })
      .subscribe({
        next: () => {
          this.guardando = false;
          this.listo = true;
        },
        error: (err) => {
          this.guardando = false;
          this.errorMsg =
            err?.error?.errors?.token?.[0] ||
            err?.error?.errors?.password?.[0] ||
            err?.error?.message ||
            'No se pudo cambiar la contraseña. Pide un enlace nuevo.';
        },
      });
  }

  irAlLogin(): void {
    this.router.navigate(['/login']);
  }

  pedirOtroEnlace(): void {
    this.router.navigate(['/olvide-password']);
  }

  togglePassword(): void {
    this.verPassword = !this.verPassword;
  }
}

/** Las dos casillas tienen que decir lo mismo. */
function coincidenLasClaves(grupo: AbstractControl): { noCoinciden: true } | null {
  const clave = grupo.get('password')?.value;
  const repetida = grupo.get('password_confirmation')?.value;
  return clave && repetida && clave !== repetida ? { noCoinciden: true } : null;
}
