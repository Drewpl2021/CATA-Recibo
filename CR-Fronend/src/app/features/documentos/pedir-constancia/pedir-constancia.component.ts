import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MisDocumentosService, ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';

type TipoConstancia = 'constancia_trabajo' | 'constancia_haberes';

/**
 * «Pedir constancia»: el trabajador elige la de trabajo o la de haberes (de
 * sus últimos 3, 6 o 12 meses pagados) y, si quiere, a quién va dirigida.
 * Se arma al momento; RR.HH. la firma y le llega el aviso.
 */
@Component({
  selector: 'app-pedir-constancia',
  standalone: true,
  imports: [FormsModule, FormModalComponent],
  templateUrl: './pedir-constancia.component.html',
  styleUrl: './pedir-constancia.component.scss',
})
export class PedirConstanciaComponent {
  private readonly servicio = inject(MisDocumentosService);
  private readonly toast = inject(ToastService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  /** Se pidió: la pantalla recarga su lista. */
  @Output() pedida = new EventEmitter<void>();

  tipo: TipoConstancia = 'constancia_trabajo';
  meses = 3;
  dirigidoA = '';
  enviando = false;

  readonly opcionesMeses = [3, 6, 12];

  cerrar(): void {
    if (this.enviando) return;
    this.visible = false;
    this.visibleChange.emit(false);
    this.tipo = 'constancia_trabajo';
    this.meses = 3;
    this.dirigidoA = '';
  }

  pedir(): void {
    if (this.enviando) return;
    this.enviando = true;
    const meses = this.tipo === 'constancia_haberes' ? this.meses : null;
    this.servicio.pedirConstancia(this.tipo, meses, this.dirigidoA.trim() || null).subscribe({
      next: (res) => {
        this.enviando = false;
        this.toast.success(res.data.nueva ? 'Constancia pedida' : 'Ya la habías pedido', res.data.message);
        this.pedida.emit();
        this.cerrar();
      },
      error: (err) => {
        this.enviando = false;
        this.toast.error('No se pudo pedir', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }
}
