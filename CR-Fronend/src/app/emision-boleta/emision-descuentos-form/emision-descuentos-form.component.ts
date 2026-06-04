import { Component, OnInit } from '@angular/core';
import { CommonModule, Location } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

@Component({
  selector: 'app-emision-descuentos-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './emision-descuentos-form.component.html',
  styleUrl: './emision-descuentos-form.component.scss'
})
export class EmisionDescuentosFormComponent implements OnInit {
  empleadoId: string = '';
  nombreEmpleado: string = 'Empleado Desconocido';

  // Descuentos
  descPredeterminado: boolean = false;
  descAfp: boolean = false;
  descSeguro: boolean = false;

  // Bonificaciones
  montoBonificacion: number | null = null;
  conceptoBonificacion: string = '';

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private location: Location
  ) {}

  ngOnInit(): void {
    this.empleadoId = this.route.snapshot.paramMap.get('id') || '';
    
    // Recuperar el nombre pasado por state (navegación)
    const state = this.location.getState() as any;
    if (state && state.nombre) {
      this.nombreEmpleado = state.nombre;
    } else {
      this.nombreEmpleado = `Empleado (ID: ${this.empleadoId})`;
    }
  }

  guardar(): void {
    const data = {
      empleadoId: this.empleadoId,
      descuentos: {
        predeterminado10: this.descPredeterminado,
        afp: this.descAfp,
        seguro: this.descSeguro
      },
      bonificacion: {
        monto: this.montoBonificacion,
        concepto: this.conceptoBonificacion
      }
    };
    
    console.log('Guardando datos de emisión:', data);
    alert(`Se guardaron los descuentos y bonificaciones para ${this.nombreEmpleado} exitosamente.`);
    this.volver();
  }

  volver(): void {
    this.router.navigate(['/inicio/emision-boleta']);
  }
}
