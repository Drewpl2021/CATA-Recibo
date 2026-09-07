import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { AuthService } from '../../../core/services';
import { ToastService } from '../../../core/services';

function passwordsIguales(control: AbstractControl): ValidationErrors | null {
  const password = control.get('password')?.value;
  const confirmacion = control.get('password_confirmation')?.value;
  return password && confirmacion && password !== confirmacion ? { passwordsDistintas: true } : null;
}

/**
 * Autoregistro real: llama a POST /register (AuthController@register en el
 * backend), que crea la cuenta Y la ficha de empleado dentro de una misma
 * transacción, con rol "empleado". El formulario pide exactamente lo que esa
 * ficha necesita para existir — nombres, apellidos, DNI, correo institucional
 * y contraseña — y nada que el backend fuera a ignorar en silencio. Cargo,
 * área, sede y sueldo los completa RR.HH. después.
 */
@Component({
  selector: 'app-registro',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './registro.component.html',
  styleUrl: '../acceso.scss'
})
export class RegistroComponent {
  private fb = inject(FormBuilder);

  enviando = false;
  errorMsg = '';
  showPassword = false;

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(100)]],
    apellido: ['', [Validators.required, Validators.maxLength(100)]],
    // El DNI se pide de verdad: es su documento y, además, con él se crea su
    // ficha de empleado. Antes el backend se lo inventaba.
    dni: ['', [Validators.required, Validators.pattern(/^[0-9]{8}$/)]],
    email: ['', [Validators.required, Validators.email, Validators.pattern(/@cata\.edu\.pe$/i)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  }, { validators: passwordsIguales });

  constructor(private router: Router, private authService: AuthService, private toastService: ToastService) {}

  get invalido() {
    return (campo: string) => {
      const c = this.form.get(campo);
      return !!c && c.invalid && c.touched;
    };
  }

  get passwordsNoCoinciden(): boolean {
    return this.form.hasError('passwordsDistintas') && !!this.form.get('password_confirmation')?.touched;
  }

  togglePassword(): void {
    this.showPassword = !this.showPassword;
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.enviando = true;
    this.errorMsg = '';
    const payload = this.form.getRawValue() as {
      nombre: string; apellido: string; dni: string;
      email: string; password: string; password_confirmation: string;
    };

    this.authService.register(payload).subscribe({
      next: (res) => {
        this.enviando = false;
        if (res.success) {
          this.toastService.success('Cuenta creada', `Bienvenido, ${res.data.user.name}.`);
          this.router.navigate([this.authService.rutaInicioSegunRol()]);
        }
      },
      error: (err) => {
        this.enviando = false;
        // 429: el servidor corta los registros seguidos desde una misma IP.
        if (err?.status === 429) {
          this.errorMsg = 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.';
          return;
        }

        const errores = err?.error?.errors;
        if (errores) {
          this.errorMsg = (Object.values(errores).flat() as string[]).join(' ');
        } else {
          this.errorMsg = err?.error?.message || 'No se pudo crear la cuenta. Intenta de nuevo.';
        }
      }
    });
  }

  goToLogin(): void {
    this.router.navigate(['/login']);
  }
}
