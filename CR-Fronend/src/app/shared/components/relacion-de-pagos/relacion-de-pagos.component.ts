import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { ToastService } from '../../../core/services';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../form-modal/form-modal.component';

/**
 * «Relación de pagos»: a quién, a qué cuenta y cuánto, por banco o caja.
 * La usan Planillas (los sueldos del mes) y CTS (el depósito del semestre).
 * La pantalla le pasa cómo pedir el archivo; aquí solo se elige el formato:
 * PDF para firmar o Excel para Tesorería.
 */
@Component({
  selector: 'app-relacion-de-pagos',
  standalone: true,
  imports: [FormsModule, FormModalComponent],
  templateUrl: './relacion-de-pagos.component.html',
  styleUrl: './relacion-de-pagos.component.scss',
})
export class RelacionDePagosComponent {
  private readonly toast = inject(ToastService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  /** «Relación de abonos de remuneraciones», «Relación de depósitos de CTS». */
  @Input() titulo = 'Relación de pagos';
  /** «Octubre 2026», «Noviembre 2026»: va en el nombre del archivo. */
  @Input() periodo = '';
  @Input({ required: true }) descargar!: (formato: 'pdf' | 'xlsx') => Observable<Blob>;

  formato: 'pdf' | 'xlsx' = 'pdf';
  bajando = false;

  cerrar(): void {
    if (this.bajando) return;
    this.visible = false;
    this.visibleChange.emit(false);
  }

  bajar(): void {
    if (this.bajando) return;
    this.bajando = true;
    const formato = this.formato;
    this.descargar(formato).subscribe({
      next: (blob) => {
        this.bajando = false;
        guardarArchivo(blob, `${this.titulo}${this.periodo ? ' - ' + this.periodo : ''}.${formato}`);
        this.toast.success('Relación descargada', formato === 'pdf' ? 'Lista para imprimir y firmar.' : 'Lista para Tesorería.');
        this.cerrar();
      },
      error: async (err) => {
        this.bajando = false;
        // El error llega como blob (se pidió un archivo): se lee para mostrar el motivo.
        let detalle = err;
        if (err?.error instanceof Blob) {
          try { detalle = { error: JSON.parse(await err.error.text()) }; } catch { /* sin detalle */ }
        }
        this.toast.error('No se descargó', mensajeErrorApi(detalle, 'No se pudo armar la relación.'));
      },
    });
  }
}
