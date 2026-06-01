import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Planilla {
  id: string;
  empleado_id: string;
  mes: number;
  anio: number;
  sueldo_base: number;
  bonificaciones: number;
  descuentos: number;
  total: number;
  created_at: string;
  updated_at: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class BoletasService {
  private readonly apiUrl = 'http://cr-backend.test/api';

  constructor(private http: HttpClient) {}

  /** Planilla del empleado autenticado (token). Filtro opcional por año. */
  getMiPlanilla(anio?: string): Observable<ApiResponse<Planilla[]>> {
    const params: Record<string, string> = {};
    if (anio) {
      params['anio'] = anio;
    }
    return this.http.get<ApiResponse<Planilla[]>>(`${this.apiUrl}/mi-planilla`, { params });
  }

  /** PDF de boleta del empleado autenticado. */
  descargarBoleta(mes: number, anio: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/mis-boletas/${mes}/${anio}`, { responseType: 'blob' });
  }
}
