import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EMPTY, Observable, expand } from 'rxjs';
import { ToastService } from '../../../core/services';
import { CertificadoDeFirma, ResultadoFirmarAqui } from '../../../core/services/planilla/boleta.service';
import { ApiResponse, mensajeErrorApi } from '../../../core/utils';
import { PistaDirective } from '../../directives/pista.directive';
import { FormModalComponent } from '../form-modal/form-modal.component';
import { IconComponent } from '../icon/icon.component';

/** Una tanda de «Firmar aquí»: la clave y desde dónde sigue. */
export type TandaDeFirma = (clave: string, desde: string | null) => Observable<ApiResponse<ResultadoFirmarAqui>>;

/**
 * «Firmar aquí»: la firma digital del colegio con el certificado de quien
 * mira, para cualquier documento que emite el sistema (boletas, hojas de
 * CTS, hojas de gratificación). Confirma cuántos y como quién, pide la
 * clave del certificado (no se guarda) y firma por tandas hasta que no
 * quede ninguno, con su barra de avance y «Detener».
 *
 * La pantalla solo le pasa cómo pedir una tanda (`tanda`) y qué está
 * firmando; al terminar avisa con `terminado` para que recargue.
 */
@Component({
  selector: 'app-firmar-aqui',
  standalone: true,
  imports: [CommonModule, FormsModule, FormModalComponent, IconComponent, PistaDirective],
  templateUrl: './firmar-aqui.component.html',
})
export class FirmarAquiComponent {
  private readonly toast = inject(ToastService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  /** El certificado de quien mira, con cuántos esperan su firma. */
  @Input() certificado: (CertificadoDeFirma & { por_firmar: number }) | null = null;
  /** Cuántas firmas del colegio pide cada documento (1 o 2). */
  @Input() requeridas = 1;
  /** «boleta», «hoja de CTS»: femenino, como todo lo que firma el colegio. */
  @Input() singular = 'boleta';
  @Input() plural = 'boletas';
  /** false para lo masculino («certificado»): firmado, cada uno, los que… */
  @Input() femenino = true;
  /** «octubre 2026», «noviembre 2026». */
  @Input() periodo = '';
  @Input({ required: true }) tanda!: TandaDeFirma;
  /** Terminó una pasada (completa, detenida o con errores): hay que recargar. */
  @Output() terminado = new EventEmitter<void>();

  clave = '';
  firmando = false;
  /** Cómo va la pasada: cuántos lleva de cuántos. */
  avance: { hechas: number; total: number } | null = null;
  /** Se pidió detener: termina la tanda en curso y no pide más. */
  private detener = false;
  /** Solo si alguno falló o se detuvo: el detalle se queda en el modal. */
  resultado: (Pick<ResultadoFirmarAqui, 'firmadas' | 'entregadas' | 'a_medias' | 'errores'> & { quedan: number; detenida: boolean }) | null = null;

  get porcentaje(): number {
    const a = this.avance;
    return a && a.total ? Math.min(100, Math.round((a.hechas / a.total) * 100)) : 0;
  }

  nombre(cantidad: number): string {
    return cantidad === 1 ? this.singular : this.plural;
  }

  /** Al cerrar se olvida la clave. Mientras firma no se cierra: primero «Detener». */
  cerrar(): void {
    if (this.firmando) return;
    this.visible = false;
    this.visibleChange.emit(false);
    this.clave = '';
    this.resultado = null;
  }

  /**
   * Firma por tandas: pide una, y con su «siguiente» la que sigue, hasta que
   * no quede ninguna (o se pida detener). Con 94 o con mil, ningún pedido es
   * largo y la barra avanza de verdad.
   */
  firmar(): void {
    if (!this.clave || this.firmando) return;
    const clave = this.clave;
    const suma = { firmadas: 0, entregadas: 0, a_medias: 0, errores: [] as ResultadoFirmarAqui['errores'] };
    let quedan = 0;
    this.firmando = true;
    this.detener = false;
    this.resultado = null;
    this.avance = { hechas: 0, total: 0 };

    const terminar = (falla?: unknown) => {
      this.firmando = false;
      this.clave = '';
      this.avance = null;
      this.terminado.emit();

      // Falló antes de firmar nada (la clave, por ejemplo): solo el aviso.
      if (falla && !suma.firmadas && !suma.errores.length) {
        this.toast.error('No se firmó', mensajeErrorApi(falla, `No se pudieron firmar las ${this.plural}.`));
        return;
      }
      const detenida = this.detener || !!falla;
      if (suma.errores.length || detenida) {
        this.resultado = { ...suma, quedan, detenida };
        if (falla) this.toast.error('Se cortó la firma', mensajeErrorApi(falla, 'Se perdió la conexión con el servidor.'));
        return;
      }
      this.cerrar();
      this.toast.success(
        `${suma.firmadas} ${this.nombre(suma.firmadas)} ${this.femenino ? 'firmada' : 'firmado'}${suma.firmadas === 1 ? '' : 's'}`,
        suma.a_medias
          ? `De ${this.periodo}: ${suma.entregadas} ya le llegaron al trabajador y ${suma.a_medias} esperan la otra firma.`
          : `De ${this.periodo}: ya le llegaron a cada trabajador.`,
      );
    };

    this.tanda(clave, null)
      .pipe(expand((res) => (res.data.siguiente && !this.detener ? this.tanda(clave, res.data.siguiente) : EMPTY)))
      .subscribe({
        next: (res) => {
          const d = res.data;
          if (this.avance && !this.avance.total) this.avance.total = d.total;
          if (this.avance) this.avance.hechas += d.procesadas;
          suma.firmadas += d.firmadas;
          suma.entregadas += d.entregadas;
          suma.a_medias += d.a_medias;
          suma.errores.push(...d.errores);
          quedan = d.total - d.procesadas;
        },
        error: (err) => terminar(err),
        complete: () => terminar(),
      });
  }

  /** Termina la tanda en curso y no pide más: lo firmado queda firmado. */
  detenerFirma(): void {
    this.detener = true;
  }
}
