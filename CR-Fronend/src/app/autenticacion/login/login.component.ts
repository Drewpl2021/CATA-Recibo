import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss'
})
export class LoginComponent {
  showPassword = false;
  email = '';
  password = '';
  isLoading = false;
  errorMsg = '';

  constructor(private router: Router, private authService: AuthService) {}

  onLogin() {
    if (!this.email || !this.password) {
      this.errorMsg = 'Por favor ingresa tu correo y contraseña.';
      return;
    }
    this.isLoading = true;
    this.errorMsg = '';

    this.authService.login(this.email, this.password).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          const user = this.authService.getUser();
          let rolName = '';
          if (typeof user?.rol === 'string') rolName = user.rol;
          else if (user?.rol && typeof user.rol === 'object') rolName = (user.rol as any).nombre || '';
          const rol = rolName.toLowerCase();
          if (rol === 'admin' || rol === 'rrhh') {
            this.router.navigate(['/inicio/dashboard']);
          } else {
            this.router.navigate(['/inicio/mis-boletas']);
          }
        }
      },
      error: (err) => {
        this.isLoading = false;
        this.errorMsg = err.status === 401
          ? 'Credenciales incorrectas. Verifica tu correo y contraseña.'
          : 'Error al conectar con el servidor. Intenta de nuevo.';
      }
    });
  }

  goToRegister() {
    this.router.navigate(['/registro']);
  }

  togglePassword() {
    this.showPassword = !this.showPassword;
  }
}

