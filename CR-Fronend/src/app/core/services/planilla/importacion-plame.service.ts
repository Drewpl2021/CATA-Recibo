import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { DecisionesPlame, ResultadoPlame, VistaPreviaPlame } from '../../models';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';

/**
 * Importar el PLAME de RR.HH.: la planilla de un mes tal como se pagó.
 *
 * `previsualizar` y `aplicar` reciben lo mismo —el archivo, el mes y lo que
 * RR.HH. decidió— y el backend vuelve a revisarlo todo al aplicar.
 */
@Injectable({ providedIn: 'root' })
export class ImportacionPlameService {
  private http = inject(HttpClient);

  /** Qué pasaría con cada trabajador. No guarda nada. */
  previsualizar(archivo: File, mes: number, anio: number, decisiones: DecisionesPlame): Observable<ApiResponse<VistaPreviaPlame>> {
    return this.http.post<ApiResponse<VistaPreviaPlame>>(
      this.url(END_POINTS_ACCIONES.importacionPlame.previsualizar),
      this.formulario(archivo, mes, anio, decisiones)
    );
  }

  /** Lo guarda todo, o nada si aparece un solo error. */
  aplicar(archivo: File, mes: number, anio: number, decisiones: DecisionesPlame): Observable<ApiResponse<ResultadoPlame>> {
    return this.http.post<ApiResponse<ResultadoPlame>>(
      this.url(END_POINTS_ACCIONES.importacionPlame.aplicar),
      this.formulario(archivo, mes, anio, decisiones)
    );
  }

  private formulario(archivo: File, mes: number, anio: number, decisiones: DecisionesPlame): FormData {
    const datos = new FormData();
    datos.append('archivo', archivo);
    datos.append('mes', String(mes));
    datos.append('anio', String(anio));
    datos.append('decisiones', JSON.stringify(decisiones));
    return datos;
  }

  private url(ruta: string): string {
    return `${environment.apiUrl}/${ruta}`;
  }
}
