import { Component, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DocumentoService, ToastService } from '../../../core/services';
import { Documento } from '../../../core/models';
import {
  esImagen, esPdf, formatoDocumento, guardarArchivo, mensajeErrorApi,
  nombreArchivoDocumento, nombreDocumento,
} from '../../../core/utils';
import { FormModalComponent } from '../form-modal/form-modal.component';
import { VisorPdfComponent } from '../visor-pdf/visor-pdf.component';

/**
 * Abre un documento sin salir de la pantalla: el PDF en su visor y la imagen
 * —una foto, la copia del DNI, un certificado escaneado— a tamaño completo.
 *
 * Lo que el navegador no sabe mostrar (Word) no se baja a escondidas: se
 * dice qué es y se ofrece el botón de descarga, que es lo que antes pasaba
 * sin avisar y dejaba a la persona buscando en su carpeta de descargas.
 *
 * Lo usan Mis Documentos y el expediente de RR.HH. Antes cada pantalla pedía
 * el archivo, armaba la URL temporal y tenía que acordarse de soltarla.
 *
 *   <app-visor-documento [(documento)]="docAbierto" [persona]="nombre"></app-visor-documento>
 *   ver(doc) { this.docAbierto = doc; }
 */
@Component({
  selector: 'app-visor-documento',
  standalone: true,
  imports: [CommonModule, FormModalComponent, VisorPdfComponent],
  templateUrl: './visor-documento.component.html',
})
export class VisorDocumentoComponent implements OnChanges, OnDestroy {
  private documentoService = inject(DocumentoService);
  private toast = inject(ToastService);

  @Input() documento: Documento | null = null;

  /** Con false, se puede leer pero no bajar: falta firmarlo. */
  @Input() puedeDescargar = true;
  @Output() documentoChange = new EventEmitter<Documento | null>();
  /** De quién es, para el nombre del archivo descargado. */
  @Input() persona = '';

  visible = false;
  url: string | null = null;
  private blob: Blob | null = null;

  readonly nombreDocumento = nombreDocumento;
  readonly formato = formatoDocumento;

  /**
   * Qué se está enseñando: el visor de PDF, la imagen, o un aviso de que ese
   * archivo no se puede mostrar.
   *
   * "ninguno" es su propio caso y no se mezcla con "no-se-puede": al cerrar,
   * el documento pasa a null por un instante, y si eso contaba como "no se
   * puede" el aviso intentaba leer el formato de un documento que ya no
   * existía. Eso reventaba el pintado a medias y dejaba el modal abierto sin
   * forma de cerrarlo.
   */
  get modo(): 'ninguno' | 'pdf' | 'imagen' | 'no-se-puede' {
    if (!this.documento) return 'ninguno';
    if (esPdf(this.documento)) return 'pdf';

    return esImagen(this.documento) ? 'imagen' : 'no-se-puede';
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (!cambios['documento']) return;

    const doc = this.documento;
    if (!doc) {
      this.limpiar();
      return;
    }

    this.documentoService.ver(doc.id).subscribe({
      next: (blob) => {
        // Se cerró o se abrió otro mientras bajaba.
        if (this.documento !== doc) return;

        this.limpiar();
        this.blob = blob;
        this.url = URL.createObjectURL(blob);
        this.visible = true;
      },
      error: (err) => {
        this.toast.error('No se pudo abrir', mensajeErrorApi(err, 'Intenta descargarlo de nuevo.'));
        this.cerrar();
      },
    });
  }

  descargar(): void {
    if (this.blob && this.documento) {
      guardarArchivo(this.blob, nombreArchivoDocumento(this.documento, this.persona));
    }
  }

  cerrar(): void {
    this.limpiar();
    this.documentoChange.emit(null);
  }

  ngOnDestroy(): void {
    this.limpiar();
  }

  private limpiar(): void {
    this.visible = false;
    if (this.url) URL.revokeObjectURL(this.url);
    this.url = null;
    this.blob = null;
  }
}
