import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiResponse, END_POINTS } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Los ajustes del sistema, tal como los devuelve ConfiguracionController. */
export interface AjustesSistema {
  /** Si RR.HH. puede armar planillas y boletas de años anteriores, de registro. */
  permitir_anios_anteriores: boolean;
}

@Injectable({ providedIn: 'root' })
export class AjustesService {
  private http = inject(HttpClient);
  private url = `${environment.apiUrl}/${END_POINTS.admin.ajustes}`;

  obtener(): Observable<ApiResponse<AjustesSistema>> {
    return this.http.get<ApiResponse<AjustesSistema>>(this.url);
  }

  guardar(ajustes: Partial<AjustesSistema>): Observable<ApiResponse<AjustesSistema>> {
    return this.http.put<ApiResponse<AjustesSistema>>(this.url, ajustes);
  }
}
