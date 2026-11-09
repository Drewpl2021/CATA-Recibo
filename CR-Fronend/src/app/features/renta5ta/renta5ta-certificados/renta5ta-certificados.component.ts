import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { FilaCertificado5ta, ListaCertificados5ta, Renta5taService } from '../../../core/services/planilla/renta5ta.service';
import { ToastService } from '../../../core/services';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { FirmarAquiComponent, TandaDeFirma } from '../../../shared/components/firmar-aqui/firmar-aqui.component';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PistaDirective } from '../../../shared/directives/pista.directive';

/** Cómo va el certificado de cada uno, en palabras y con su tono. */
const SITUACION: Record<'firmado' | 'por_firmar' | 'sin_entregar' | 'no_corresponde', { texto: string; tono: 'success' | 'info' | 'warning' | 'secondary' }> = {
  firmado: { texto: 'Firmado y entregado', tono: 'success' },
  por_firmar: { texto: 'Por firmar', tono: 'warning' },
  sin_entregar: { texto: 'Sin entregar', tono: 'info' },
  no_corresponde: { texto: 'Todavía no', tono: 'secondary' },
};

type FilaVista = FilaCertificado5ta & { situacion: keyof typeof SITUACION };

/**
 * Los Certificados de Rentas y Retenciones de 5ta del año: a quién ya se le
 * puede entregar, entregarlos a su expediente, firmarlos con «Firmar aquí»
 * (como las boletas) y bajarlos en un .zip. Se entrega antes del 1 de marzo
 * del año siguiente, o al cese.
 */
@Component({
  selector: 'app-renta5ta-certificados',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, FormModalComponent, FirmarAquiComponent, DataTableComponent, CeldaTablaDirective, IconComponent, PistaDirective],
  templateUrl: './renta5ta-certificados.component.html',
  styleUrl: './renta5ta-certificados.component.scss',
})
export class Renta5taCertificadosComponent implements OnChanges {
  private readonly servicio = inject(Renta5taService);
  private readonly toast = inject(ToastService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();

  /** Por defecto el año pasado: el certificado se entrega cuando el año ya cerró. */
  anio = new Date().getFullYear() - 1;
  readonly anios = [new Date().getFullYear(), new Date().getFullYear() - 1, new Date().getFullYear() - 2];

  datos: ListaCertificados5ta | null = null;
  filas: FilaVista[] = [];
  cargando = false;
  entregando = false;
  bajandoZip = false;
  modalFirmar = false;
  puedeFirmar = false;
  pistaFirmar = '';
  porEntregar = 0;

  readonly columnas: ColumnaTabla<FilaVista>[] = [
    { campo: 'nombre', header: 'Trabajador', ancho: '34%' },
    { campo: 'retenido', header: 'Retenido en el año', tipo: 'moneda', ancho: '16%' },
    { campo: 'saldo', header: 'Saldo', ancho: '18%' },
    {
      campo: 'situacion', header: 'Certificado', tipo: 'badge', ancho: '18%',
      formatear: (_v, f) => SITUACION[f.situacion].texto,
      badgeSeveridad: (_v, f) => SITUACION[f.situacion].tono,
    },
  ];

  readonly acciones: AccionPersonalizada<FilaVista>[] = [
    { id: 'pdf', titulo: 'Ver su certificado en PDF', icono: 'download', visible: (f) => f.puede || !!f.documento_id },
  ];

  /** Una tanda de «Firmar aquí» con los certificados del año que se está viendo. */
  readonly firmarTanda: TandaDeFirma = (clave, desde) => this.servicio.firmarCertificados(this.anio, clave, desde);

  ngOnChanges(cambios: SimpleChanges): void {
    if (cambios['visible'] && this.visible) this.cargar();
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.certificados(this.anio).subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.datos = res.data;
        this.filas = res.data.filas.map((f) => ({
          ...f,
          situacion: f.firma_colegio === 'completa' ? 'firmado' : f.documento_id ? 'por_firmar' : f.puede ? 'sin_entregar' : 'no_corresponde',
        }));
        this.porEntregar = this.filas.filter((f) => f.situacion === 'sin_entregar').length;
        const c = res.data.firma.mi_certificado;
        this.puedeFirmar = !!c && !c.vencido && c.por_firmar > 0;
        this.pistaFirmar = !c ? 'Primero pon tu certificado en Ajustes → Boletas.'
          : c.vencido ? 'Tu certificado venció: renuévalo y ponlo de nuevo en Ajustes → Boletas.'
          : !c.por_firmar ? 'No hay certificados de este año esperando tu firma.'
          : `Firma de una vez los ${c.por_firmar} certificado(s) que esperan tu firma. Te pide la clave de tu certificado.`;
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudieron leer los certificados.'));
      },
    });
  }

  /** «Faltó retener S/ 12.30» / «Retenido de más S/ 22.61» / «Sin saldo». */
  textoSaldo(f: FilaCertificado5ta): string {
    if (f.saldo === null) return '—';
    if (f.saldo > 0.005) return `Faltó retener S/ ${f.saldo.toFixed(2)}`;
    if (f.saldo < -0.005) return `Retenido de más S/ ${(-f.saldo).toFixed(2)}`;
    return 'Sin saldo';
  }

  entregar(): void {
    if (this.entregando || !this.porEntregar) return;
    this.entregando = true;
    this.servicio.entregarCertificados(this.anio).subscribe({
      next: (res) => {
        this.entregando = false;
        this.toast.success('Certificados entregados', res.data.message);
        this.cargar();
      },
      error: (err) => {
        this.entregando = false;
        this.toast.error('No se entregaron', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }

  bajarZip(): void {
    if (this.bajandoZip) return;
    this.bajandoZip = true;
    this.servicio.bajarCertificadosEnZip(this.anio).subscribe({
      next: (cantidad) => {
        this.bajandoZip = false;
        this.toast.success('Descarga iniciada', `${cantidad} certificado(s) en el .zip.`);
      },
      error: (err) => {
        this.bajandoZip = false;
        this.toast.error('No se descargó', mensajeErrorApi(err, 'No se pudieron juntar los certificados.'));
      },
    });
  }

  alAccionar(evento: { accion: string; fila: FilaVista }): void {
    if (evento.accion !== 'pdf') return;
    const f = evento.fila;
    this.servicio.certificado(f.id, this.anio).subscribe({
      next: (blob) => guardarArchivo(blob, `Certificado de 5ta ${this.anio} - ${f.nombre}.pdf`),
      error: (err) => this.toast.error('No se descargó', mensajeErrorApi(err, 'No se pudo armar su certificado.')),
    });
  }
}
