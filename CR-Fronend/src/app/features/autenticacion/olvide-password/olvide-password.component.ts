import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../../core/services';

/**
 * "Olvidé mi contraseña".
 *
 * Pide el correo y manda un enlace de un solo uso que vence en una hora.
 *
 * El mensaje de éxito es el mismo exista o no ese correo, y es a propósito:
 * el backend responde igual en los dos casos para que nadie pueda ir probando
 * direcciones hasta averiguar quién trabaja en el colegio.
 */
@Component({
  selector: 'app-olvide-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './olvide-password.component.html',
  styleUrl: '../acceso.scss',
})
export class OlvidePasswordComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  enviando = false;
  enviado = false;
  errorMsg = '';

  form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
  });

  get emailInvalido(): boolean {
    const c = this.form.get('email');
    return !!c && c.invalid && c.touched;
  }

  enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.enviando = true;
    this.errorMsg = '';

    this.authService.olvidePassword(this.form.getRawValue().email!).subscribe({
      next: () => {
        this.enviando = false;
        this.enviado = true;
      },
      error: (err) => {
        this.enviando = false;
        // 429: pidió otro enlace demasiado pronto. Es el único caso en que el
        // backend sí dice algo concreto.
        this.errorMsg =
          err?.error?.data?.message ||
          err?.error?.message ||
          'No se pudo conectar con el servidor. Intenta de nuevo.';
      },
    });
  }

  volverAlLogin(): void {
    this.router.navigate(['/login']);
  }
}
