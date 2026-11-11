import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ConfirmService, ToastService } from '../../../core/services';
import { ConstanciaPorFirmar, DocumentoService } from '../../../core/services/planilla/documento.service';
import { ResumenDeFirma } from '../../../core/services/planilla/boleta.service';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { FirmarAquiComponent, TandaDeFirma } from '../../../shared/components/firmar-aqui/firmar-aqui.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';

/**
 * «Constancias por firmar»: las constancias de trabajo y de haberes que pidió
 * el personal desde Mis Documentos. RR.HH. las revisa, las firma de una vez
 * con «Firmar aquí» (como las boletas) o anula la que no corresponde. Al
 * firmarlas, a cada uno le llega el aviso.
 */
@Component({
  selector: 'app-constancias-por-firmar',
  standalone: true,
  imports: [DatePipe, RouterLink, FormModalComponent, FirmarAquiComponent, IconComponent, PistaDirective],
  templateUrl: './constancias-por-firmar.component.html',
})
export class ConstanciasPorFirmarComponent implements OnChanges {
  private readonly servicio = inject(DocumentoService);
  private readonly toast = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  /** Cambió cuántas quedan: la pantalla actualiza su número. */
  @Output() cambio = new EventEmitter<number>();

  filas: ConstanciaPorFirmar[] = [];
  firma: ResumenDeFirma | null = null;
  cargando = false;
  modalFirmar = false;
  puedeFirmar = false;
  pistaFirmar = '';

  readonly firmarTanda: TandaDeFirma = (clave, desde) => this.servicio.firmarConstancias(clave, desde);

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['visible'] && this.visible) this.cargar();
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.constanciasPorFirmar().subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.filas = res.data.filas;
        this.firma = res.data.firma;
        this.cambio.emit(this.filas.length);
        const c = res.data.firma.mi_certificado;
        this.puedeFirmar = !!c && !c.vencido && c.por_firmar > 0;
        this.pistaFirmar = !c ? 'Primero pon tu certificado en Ajustes → Boletas.'
          : c.vencido ? 'Tu certificado venció: renuévalo y ponlo de nuevo en Ajustes → Boletas.'
          : !c.por_firmar ? 'No hay constancias esperando tu firma.'
          : `Firma de una vez las ${c.por_firmar} constancia(s) que esperan tu firma. Te pide la clave de tu certificado.`;
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudieron leer las constancias.'));
      },
    });
  }

  ver(f: ConstanciaPorFirmar): void {
    this.servicio.ver(f.id).subscribe({
      next: (blob) => guardarArchivo(blob, `${f.tipo_texto} - ${f.nombre}.pdf`),
      error: (err) => this.toast.error('No se abrió', mensajeErrorApi(err, 'No se pudo abrir la constancia.')),
    });
  }

  anular(f: ConstanciaPorFirmar): void {
    this.confirm
      .confirmar({
        titulo: 'Anular la constancia',
        mensaje: `Se borra la ${f.tipo_texto.toLowerCase()} que pidió ${f.nombre}. Si la necesita, la vuelve a pedir desde Mis Documentos.`,
        aceptarTexto: 'Sí, anularla',
        variante: 'danger',
      })
      .then((ok) => {
        if (!ok) return;
        this.servicio.anularConstancia(f.id).subscribe({
          next: () => {
            this.toast.success('Constancia anulada', `La de ${f.nombre}.`);
            this.cargar();
          },
          error: (err) => this.toast.error('No se anuló', mensajeErrorApi(err, 'Inténtalo de nuevo.')),
        });
      });
  }
}
