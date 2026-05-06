import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-registro',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './registro.component.html',
  styleUrl: './registro.component.scss'
})
export class RegistroComponent {
  form = {
    nombre: '',
    apellido: '',
    dni: '',
    cargo: '',
    departamento: '',
    telefono: '',
    email: ''
  };

  ocrSimulated = false;

  constructor(private router: Router) {}

  simulateOcr() {
    this.ocrSimulated = true;
    this.form.nombre = 'Carlos';
    this.form.apellido = 'Mamani Quispe';
    this.form.dni = '40123456';
  }

  onSubmit() {
    this.router.navigate(['/inicio']);
  }

  goToLogin() {
    this.router.navigate(['/login']);
  }
}
