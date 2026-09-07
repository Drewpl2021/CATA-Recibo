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
  obtener(mes?: number, anio?: number): Observable<ApiResponse<Dashboard>> {
    let params = new HttpParams();
    if (mes) params = params.set('mes', String(mes));
    if (anio) params = params.set('anio', String(anio));
    return this.http.get<ApiResponse<Dashboard>>(this.url, { params });
  }
}
