import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import {
  PayloadImportacion,
  ResultadoImportacion,
  ResultadoReconocer,
  VistaPreviaImportacion,
} from '../../models';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';

/**
 * Importar conceptos de pago desde el Excel de RR.HH.
 *
 * Los tres pasos del asistente. `previsualizar` y `aplicar` reciben
 * exactamente lo mismo: el backend vuelve a revisarlo todo al aplicar, así que
 * no hay forma de guardar algo distinto de lo que se vio en la revisión.
 */
@Injectable({ providedIn: 'root' })
export class ImportacionConceptosService {
  private http = inject(HttpClient);

  /** El Excel modelo del mes: la gente con planilla y una columna por concepto. */
  descargarModelo(mes: number, anio: number): Observable<Blob> {
    return this.http.get(this.url(END_POINTS_ACCIONES.importacionConceptos.modelo), {
      params: { mes, anio },
      responseType: 'blob',
    });
  }

  /** Qué concepto es cada columna, según su título. */
  reconocer(columnas: string[]): Observable<ApiResponse<ResultadoReconocer>> {
    return this.http.post<ApiResponse<ResultadoReconocer>>(
      this.url(END_POINTS_ACCIONES.importacionConceptos.reconocer),
      { columnas }
    );
  }

  /** Qué cambiaría en cada planilla. No guarda nada. */
  previsualizar(payload: PayloadImportacion): Observable<ApiResponse<VistaPreviaImportacion>> {
    return this.http.post<ApiResponse<VistaPreviaImportacion>>(
      this.url(END_POINTS_ACCIONES.importacionConceptos.previsualizar),
      payload
    );
  }

  /** Lo guarda todo, o nada si aparece un solo error. */
  aplicar(payload: PayloadImportacion): Observable<ApiResponse<ResultadoImportacion>> {
    return this.http.post<ApiResponse<ResultadoImportacion>>(
      this.url(END_POINTS_ACCIONES.importacionConceptos.aplicar),
      payload
    );
  }

  private url(ruta: string): string {
    return `${environment.apiUrl}/${ruta}`;
  }
}
