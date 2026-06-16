import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

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
  private readonly apiUrl = environment.apiUrl;

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

  /** PDF de boleta de cualquier empleado (Admin/RRHH). */
  generarBoletaAdmin(empleadoId: string, mes: number, anio: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/boleta/${empleadoId}/${mes}/${anio}`, { responseType: 'blob' });
  }

  /** Generar boletas masivamente para todos los empleados activos (Admin/RRHH). */
  generarMasivo(mes: number, anio: number): Observable<{ success: boolean; message: string; generadas: number; omitidas: number }> {
    return this.http.post<{ success: boolean; message: string; generadas: number; omitidas: number }>(
      `${this.apiUrl}/boletas/generar-masivo`, { mes, anio }
    );
  }
}
