import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { SeccionEmpleadoBase } from './seccion-base';

/** Paso 1: quién es la persona. */
@Component({
  selector: 'app-seccion-personales',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './seccion-personales.component.html',
})
export class SeccionPersonalesComponent extends SeccionEmpleadoBase {}
