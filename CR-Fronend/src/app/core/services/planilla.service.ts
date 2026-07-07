import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Planilla {
  id?: string;
  empleado_id: string;
  periodo_id?: string;
  mes: number;
  anio: number;
  sueldo_base?: number; // Es opcional en el form, lo auto-asigna el backend
  bonificaciones: number;
  descuentos: number;
  total?: number;
  empleado?: {
    id: string;
    nombres: string;
    apellidos: string;
    numero_documento: string;
  };
}

@Injectable({
  providedIn: 'root'
})
export class PlanillaService {
  private apiUrl = environment.apiUrl + '/planilla';

  constructor(private http: HttpClient) {}

  getPlanillas(filtros: { empleado_id?: string; mes?: number; anio?: number }): Observable<{ success: boolean; data: Planilla[] }> {
    return this.http.get<{ success: boolean; data: Planilla[] }>(this.apiUrl, { params: filtros as any });
  }

  getPlanilla(id: string): Observable<{ success: boolean; data: Planilla }> {
    return this.http.get<{ success: boolean; data: Planilla }>(`${this.apiUrl}/${id}`);
  }

  crearPlanilla(data: Planilla): Observable<{ success: boolean; data: Planilla }> {
    return this.http.post<{ success: boolean; data: Planilla }>(this.apiUrl, data);
  }

  actualizarPlanilla(id: string, data: Partial<Planilla>): Observable<{ success: boolean; data: Planilla }> {
    return this.http.put<{ success: boolean; data: Planilla }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarPlanilla(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
