import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Dashboard } from '../../models';
import { ApiResponse } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Las cifras del Panel de Control.
 *
 * Una sola llamada trae todo lo que pinta la pantalla: son ocho consultas
 * de agregado en el servidor, y partirlas en ocho peticiones solo añadiría
 * latencia y parpadeo.
 */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private http = inject(HttpClient);
  private url = `${environment.apiUrl}/dashboard`;

  /** Sin mes ni año, el backend usa el mes en curso. */
  obtener(mes?: number, anio?: number, sedeId?: string | null): Observable<ApiResponse<Dashboard>> {
    let params = new HttpParams();
    if (mes) params = params.set('mes', String(mes));
    if (anio) params = params.set('anio', String(anio));
    // Un colegio con dos locales necesita poder mirar uno solo.
    if (sedeId) params = params.set('sede_id', sedeId);
    return this.http.get<ApiResponse<Dashboard>>(this.url, { params });
  }

  /**
   * GET /dashboard/exportar — el panel en Excel, con el filtro puesto.
   *
   * Lo que se ve es lo que baja: el mes y la sede van en la petición, así
   * que el archivo no puede salir de un periodo distinto del de la pantalla.
   * Vuelve como blob porque es un archivo, no el { success, data } del resto.
   */
  exportar(mes: number, anio: number, sedeId?: string | null): Observable<Blob> {
    let params = new HttpParams().set('mes', String(mes)).set('anio', String(anio));
    if (sedeId) params = params.set('sede_id', sedeId);
    return this.http.get(`${this.url}/export`, { params, responseType: 'blob' });
  }
}
