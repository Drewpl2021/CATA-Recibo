import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import {
  ArchivoAnteriorPayload,
  DatosDocumentoAnterior,
  Documento,
  VistaDocumentosAnteriores,
} from '../../models';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';

/**
 * Boletas y contratos de antes del sistema, en lote.
 *
 * `previsualizar` recibe solo lo que el navegador leyó de cada archivo (DNI,
 * tipo, mes), nunca los archivos. Después se suben de a uno: un PDF que falle
 * no tumba a los demás, y se puede decir cuál fue.
 */
@Injectable({ providedIn: 'root' })
export class DocumentosAnterioresService {
  private http = inject(HttpClient);

  previsualizar(archivos: ArchivoAnteriorPayload[]): Observable<ApiResponse<VistaDocumentosAnteriores>> {
    return this.http.post<ApiResponse<VistaDocumentosAnteriores>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.documentosAnteriores.previsualizar}`,
      { archivos }
    );
  }

  subir(datos: DatosDocumentoAnterior, archivo: File): Observable<ApiResponse<{ documento: Documento; dni: string; nombre: string }>> {
    const formulario = new FormData();
    formulario.append('archivo', archivo);
    formulario.append('dni', datos.dni);
    formulario.append('tipo', datos.tipo);
    if (datos.mes !== null) formulario.append('mes', String(datos.mes));
    if (datos.anio !== null) formulario.append('anio', String(datos.anio));

    return this.http.post<ApiResponse<{ documento: Documento; dni: string; nombre: string }>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.documentosAnteriores.subir}`,
      formulario
    );
  }
}
