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

/**
 * Los montos de ley de un año (ValorLegal en el backend). Los montos van en
 * soles; el resto son porcentajes (13 = 13%).
 */
export interface ValorLegal {
  anio: number;
  uit: number;
  asignacion_familiar: number;
  onp: number;
  essalud: number;
  aporte_afp: number;
  prima_seguro_afp: number;
  comision_habitat: number;
  comision_integra: number;
  comision_prima: number;
  comision_profuturo: number;
}

export type CamposValorLegal = Omit<ValorLegal, 'anio'>;

@Injectable({ providedIn: 'root' })
export class AjustesService {
  private http = inject(HttpClient);
  private url = `${environment.apiUrl}/${END_POINTS.admin.ajustes}`;
  private urlLey = `${environment.apiUrl}/${END_POINTS.admin.valoresLegales}`;

  obtener(): Observable<ApiResponse<AjustesSistema>> {
    return this.http.get<ApiResponse<AjustesSistema>>(this.url);
  }

  guardar(ajustes: Partial<AjustesSistema>): Observable<ApiResponse<AjustesSistema>> {
    return this.http.put<ApiResponse<AjustesSistema>>(this.url, ajustes);
  }

  /** Todos los años cargados, del más reciente al más antiguo. */
  valoresLegales(): Observable<ApiResponse<ValorLegal[]>> {
    return this.http.get<ApiResponse<ValorLegal[]>>(this.urlLey);
  }

  /** Los que usa ese año (si no está cargado, los del último año anterior). */
  valoresLegalesDelAnio(anio: number): Observable<ApiResponse<ValorLegal>> {
    return this.http.get<ApiResponse<ValorLegal>>(`${this.urlLey}/${anio}`);
  }

  /** Un año nuevo; lo que no se mande se copia del año anterior. */
  agregarAnio(anio: number, valores: Partial<CamposValorLegal> = {}): Observable<ApiResponse<ValorLegal>> {
    return this.http.post<ApiResponse<ValorLegal>>(this.urlLey, { anio, ...valores });
  }

  actualizarAnio(anio: number, valores: CamposValorLegal): Observable<ApiResponse<ValorLegal>> {
    return this.http.put<ApiResponse<ValorLegal>>(`${this.urlLey}/${anio}`, valores);
  }
}
