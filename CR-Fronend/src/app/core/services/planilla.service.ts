import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Planilla {
  id?: string;
  empleado_id: string;
  periodo_id?: string | null;
  mes: number;
  anio: number;
  sueldo_base?: number; // Convertimos de string a number al recibir
  bonificaciones: number;
  descuentos: number;
  total?: number;
  estado_registro?: string;
  empleado?: {
    id: string;
    nombre: string;
    apellido: string;
    dni: string;
    cargo_id?: string | null;
    area_id?: string | null;
    telefono?: string;
    direccion?: string;
    fecha_ingreso?: string;
    estado?: string;
    sueldo_base?: number;
    tipo_contrato?: string | null;
    forma_pago?: string | null;
    sede_id?: string | null;
    entidad_financiera?: string | null;
    numero_cuenta?: string | null;
    tiene_hijos?: number;
    firma_imagen?: string | null;
    nivel_estudios?: string | null;
    especialidad?: string | null;
    institucion_estudios?: string | null;
    contacto_emergencia_nombre?: string | null;
    contacto_emergencia_telefono?: string | null;
    fecha_nacimiento?: string | null;
    created_at?: string;
    updated_at?: string;
  } | null;
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
