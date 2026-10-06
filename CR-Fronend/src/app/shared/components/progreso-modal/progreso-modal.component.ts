import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { IconComponent } from '../icon/icon.component';

/**
 * El modal de avance de los procesos largos. Uno solo, montado en
 * AppComponent como el de confirmación: cualquier pantalla lo abre con
 * ProgresoService.seguir().
 *
 * No se puede cerrar: el proceso sigue en el servidor aunque se cierre, y
 * un modal cerrado a mitad hacía creer que se había cancelado.
 */
@Component({
  selector: 'app-progreso-modal',
  standalone: true,
  imports: [CommonModule, IconComponent],
  templateUrl: './progreso-modal.component.html',
})
export class ProgresoModalComponent {
  readonly estado$ = inject(ProgresoService).estado$;

  /** "menos de 5 segundos", "40 segundos", "2 min 10 s". */
  tiempo(segundos: number): string {
    const s = Math.round(segundos);
    if (s < 5) return 'menos de 5 segundos';
    if (s < 60) return `${s} segundos`;
    const m = Math.floor(s / 60);
    const r = s % 60;
    return r ? `${m} min ${r} s` : `${m} min`;
  }
}
