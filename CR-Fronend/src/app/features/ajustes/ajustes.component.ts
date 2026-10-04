import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { AjustesService, AjustesSistema, ToastService } from '../../core/services';
import { mensajeErrorApi } from '../../core/utils';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';

/**
 * Ajustes del sistema: lo que el Administrador enciende y apaga sin tocar
 * código. Cada cambio se guarda al momento y queda en la Auditoría.
 */
@Component({
  selector: 'app-ajustes',
  standalone: true,
  imports: [CommonModule, PageHeaderComponent],
  templateUrl: './ajustes.component.html',
})
export class AjustesComponent implements OnInit {
  private ajustesService = inject(AjustesService);
  private toastService = inject(ToastService);

  ajustes: AjustesSistema | null = null;
  cargando = true;
  guardando = false;

  /** El año en curso: "anteriores" son los de antes de este. */
  readonly anioActual = new Date().getFullYear();

  ngOnInit(): void {
    this.ajustesService.obtener().subscribe({
      next: (res) => {
        if (res.success) this.ajustes = res.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los ajustes.'));
      },
    });
  }

  cambiarAniosAnteriores(activo: boolean): void {
    if (!this.ajustes) return;

    const antes = this.ajustes.permitir_anios_anteriores;
    // Se ve al instante; si el servidor lo rechaza, se devuelve como estaba.
    this.ajustes = { ...this.ajustes, permitir_anios_anteriores: activo };
    this.guardando = true;

    this.ajustesService.guardar({ permitir_anios_anteriores: activo }).subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.ajustes = res.data;
          this.toastService.success(
            'Ajuste guardado',
            activo
              ? 'RR.HH. ya puede armar planillas y boletas de años anteriores.'
              : `Desde ahora solo se arman planillas y boletas de ${this.anioActual} en adelante.`
          );
        }
      },
      error: (err) => {
        this.guardando = false;
        this.ajustes = { ...this.ajustes!, permitir_anios_anteriores: antes };
        this.toastService.error('No se guardó', mensajeErrorApi(err, 'No se pudo guardar el ajuste.'));
      },
    });
  }
}
