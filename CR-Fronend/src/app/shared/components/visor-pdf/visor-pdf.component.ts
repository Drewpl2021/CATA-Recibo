import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PdfViewerModule } from 'ng2-pdf-viewer';

/**
 * El visor de PDF, en su propio componente y a propósito.
 *
 * ng2-pdf-viewer arrastra pdf.js: 500 kB de los 517 que pesaba la pantalla de
 * Mis Boletas. Y esa es la pantalla que abre TODO docente al entrar, aunque
 * la mayoría solo mire la lista y descargue el archivo sin abrir el visor.
 *
 * Al estar aquí suelto y ser autónomo, la pantalla lo carga con `@defer` en el
 * momento en que se abre el modal. Quien no abra ninguna boleta no se
 * descarga pdf.js nunca — y en una conexión de Juliaca medio mega se nota.
 */
@Component({
  selector: 'app-visor-pdf',
  standalone: true,
  imports: [CommonModule, PdfViewerModule],
  template: `
    <pdf-viewer
      *ngIf="src"
      [src]="src"
      [render-text]="true"
      [original-size]="false"
      [fit-to-page]="true"
      style="display: block; width: 100%; height: 100%;"
    ></pdf-viewer>
  `,
})
export class VisorPdfComponent {
  /** La dirección del PDF: vale una URL o un object URL de un blob. */
  @Input() src: string | null = null;

  constructor() {
    // El motor de pdf.js sale de nuestro propio servidor.
    //
    // Por defecto ng2-pdf-viewer se lo descarga de cdn.jsdelivr.net. Eso son
    // dos problemas: un script de un tercero ejecutándose dentro del sistema
    // de planillas —la política de seguridad del despliegue lo bloquea, y con
    // razón— y un visor que deja de abrir boletas en cuanto el colegio se
    // queda sin internet. El archivo se copia a assets/pdf al compilar.
    (window as unknown as Record<string, string>)['pdfWorkerSrc'] =
      'assets/pdf/pdf.worker.min.mjs';
  }
}
