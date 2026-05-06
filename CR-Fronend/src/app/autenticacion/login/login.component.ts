import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

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

  constructor(private router: Router) {}

  onLogin() {
    this.router.navigate(['/inicio']);
  }

  goToRegister() {
    this.router.navigate(['/registro']);
  }

  togglePassword() {
    this.showPassword = !this.showPassword;
  }
}
