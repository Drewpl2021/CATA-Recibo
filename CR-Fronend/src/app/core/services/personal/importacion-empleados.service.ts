import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import {
  Documento,
  PayloadImportacionEmpleados,
  ResultadoImportacionEmpleados,
  ResultadoReconocerEmpleados,
  VistaPreviaEmpleados,
} from '../../models';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';

/**
 * Importar empleados desde el Excel de RR.HH., y sus hojas de vida en lote.
 *
 * `previsualizar` y `aplicar` reciben lo mismo: el backend vuelve a revisarlo
 * todo al aplicar. Las hojas de vida se suben DESPUÉS, de a una, para que un
 * archivo que falle no tumbe a los demás y se pueda decir cuál fue.
 */
@Injectable({ providedIn: 'root' })
export class ImportacionEmpleadosService {
  private http = inject(HttpClient);

  /** El Excel modelo: los títulos que se reconocen, listas desplegables e instrucciones. */
  descargarModelo(): Observable<Blob> {
    return this.http.get(this.url(END_POINTS_ACCIONES.importacionEmpleados.modelo), { responseType: 'blob' });
  }

  reconocer(columnas: string[]): Observable<ApiResponse<ResultadoReconocerEmpleados>> {
    return this.http.post<ApiResponse<ResultadoReconocerEmpleados>>(
      this.url(END_POINTS_ACCIONES.importacionEmpleados.reconocer),
      { columnas }
    );
  }

  previsualizar(payload: PayloadImportacionEmpleados): Observable<ApiResponse<VistaPreviaEmpleados>> {
    return this.http.post<ApiResponse<VistaPreviaEmpleados>>(
      this.url(END_POINTS_ACCIONES.importacionEmpleados.previsualizar),
      payload
    );
  }

  aplicar(payload: PayloadImportacionEmpleados): Observable<ApiResponse<ResultadoImportacionEmpleados>> {
    return this.http.post<ApiResponse<ResultadoImportacionEmpleados>>(
      this.url(END_POINTS_ACCIONES.importacionEmpleados.aplicar),
      payload
    );
  }

  /** Una hoja de vida del lote; el trabajador se busca por el DNI. */
  subirHojaDeVida(dni: string, archivo: File): Observable<ApiResponse<{ documento: Documento; dni: string; nombre: string }>> {
    const datos = new FormData();
    datos.append('dni', dni);
    datos.append('archivo', archivo);
    return this.http.post<ApiResponse<{ documento: Documento; dni: string; nombre: string }>>(
      this.url(END_POINTS_ACCIONES.importacionEmpleados.hojaDeVida),
      datos
    );
  }

  private url(ruta: string): string {
    return `${environment.apiUrl}/${ruta}`;
  }
}
