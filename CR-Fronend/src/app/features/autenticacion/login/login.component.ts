import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/services';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './login.component.html',
  styleUrl: '../acceso.scss'
})
export class LoginComponent {
  private fb = inject(FormBuilder);

  showPassword = false;
  isLoading = false;
  errorMsg = '';

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  constructor(private router: Router, private authService: AuthService) {}

  onLogin(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.errorMsg = '';
    const { email, password } = this.form.getRawValue();

    this.authService.login(email!, password!).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          // Si la sesión se le cayó a media faena, se le devuelve a donde
          // estaba en vez de soltarlo en el inicio.
          const retorno = this.authService.consumirRetorno();
          // Si la cuenta sigue con la contraseña que le dieron, no hay
          // retorno que valga: primero la cambia.
          this.router.navigateByUrl(
            this.authService.debeCambiarPassword() ? '/cambiar-clave' : (retorno ?? this.authService.rutaInicioSegunRol())
          );
        }
      },
      error: (err) => {
        this.isLoading = false;
        // 429: demasiados intentos seguidos desde esta IP. El servidor corta
        // para que nadie pruebe contraseñas a lo bruto.
        if (err?.status === 429) {
          this.errorMsg = 'Demasiados intentos fallidos. Espera un minuto y vuelve a intentarlo.';
          return;
        }

        // El backend responde 422 (ValidationException) con
        // errors.email = ["Credenciales incorrectas."] cuando el correo o
        // la contraseña no coinciden — o 403 si la cuenta está desactivada.
        this.errorMsg =
          err?.error?.errors?.email?.[0] ||
          err?.error?.message ||
          'No se pudo conectar con el servidor. Intenta de nuevo.';
      }
    });
  }

  goToRegister(): void {
    this.router.navigate(['/registro']);
  }

  irAOlvide(): void {
    this.router.navigate(['/olvide-password']);
  }

  togglePassword(): void {
    this.showPassword = !this.showPassword;
  }

  get emailInvalido(): boolean {
    const c = this.form.get('email');
    return !!c && c.invalid && c.touched;
  }

  get passwordInvalido(): boolean {
    const c = this.form.get('password');
    return !!c && c.invalid && c.touched;
  }
}
