import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Vacacion {
  id: string;
  empleado_id: string;
  fecha_inicio: string;
  fecha_fin: string;
  dias_solicitados: number;
  estado: 'pendiente' | 'aprobado' | 'rechazado';
  motivo?: string;
  aprobado_por?: string;
  estado_registro?: 'activo' | 'inactivo';
  empleado?: { nombre: string; apellido: string; dni: string };
}

export interface SaldoVacaciones {
  anio: number;
  mesesTrabajados: number;
  diasGanados: number;
  diasUsados: number;
  diasDisponibles: number;
}

@Injectable({ providedIn: 'root' })
export class VacacionService {
  private apiUrl = environment.apiUrl + '/vacaciones';

  constructor(private http: HttpClient) {}

  getVacaciones(filtros?: { empleado_id?: string; estado?: string; anio?: number }): Observable<{ success: boolean; data: Vacacion[] }> {
    return this.http.get<{ success: boolean; data: Vacacion[] }>(this.apiUrl, { params: (filtros || {}) as any });
  }

  getSaldo(empleadoId?: string, anio?: number): Observable<{ success: boolean; data: SaldoVacaciones }> {
    const params: any = {};
    if (empleadoId) params.empleado_id = empleadoId;
    if (anio) params.anio = anio;
    return this.http.get<{ success: boolean; data: SaldoVacaciones }>(`${this.apiUrl}/saldo`, { params });
  }

  getVacacion(id: string): Observable<{ success: boolean; data: Vacacion }> {
    return this.http.get<{ success: boolean; data: Vacacion }>(`${this.apiUrl}/${id}`);
  }

  crearVacacion(data: Partial<Vacacion>): Observable<{ success: boolean; data: Vacacion; dias_restantes: number }> {
    return this.http.post<{ success: boolean; data: Vacacion; dias_restantes: number }>(this.apiUrl, data);
  }

  actualizarVacacion(id: string, data: Partial<Vacacion>): Observable<{ success: boolean; data: Vacacion }> {
    return this.http.put<{ success: boolean; data: Vacacion }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarVacacion(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
