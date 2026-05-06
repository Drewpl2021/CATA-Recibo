import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-dashboard-view',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard-view.component.html',
  styleUrl: './dashboard-view.component.scss'
})
export class DashboardViewComponent {
  recentDocs = [
    { id: 1, tipo: 'Recibo de Sueldo', periodo: 'Marzo 2026', estado: 'pending', fecha: '05 Abr 2026' },
    { id: 2, tipo: 'Actualización Políticas', periodo: 'Anual 2026', estado: 'pending', fecha: '01 Abr 2026' },
    { id: 3, tipo: 'Recibo de Sueldo', periodo: 'Febrero 2026', estado: 'signed', fecha: '05 Mar 2026' },
    { id: 4, tipo: 'Formulario Vacaciones', periodo: 'Febrero 2026', estado: 'signed', fecha: '15 Feb 2026' },
  ];
}
