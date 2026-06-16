import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Periodo {
  id: string;
  nombre: string;
  fecha_inicio: string;
  fecha_fin: string;
  activo: boolean;
}

@Injectable({ providedIn: 'root' })
export class PeriodoService {
  private apiUrl = environment.apiUrl + '/periodos';

  constructor(private http: HttpClient) {}

  getPeriodos(): Observable<{ success: boolean; data: Periodo[] }> {
    return this.http.get<{ success: boolean; data: Periodo[] }>(this.apiUrl);
  }

  getPeriodo(id: string): Observable<{ success: boolean; data: Periodo }> {
    return this.http.get<{ success: boolean; data: Periodo }>(`${this.apiUrl}/${id}`);
  }

  crearPeriodo(data: Partial<Periodo>): Observable<{ success: boolean; data: Periodo }> {
    return this.http.post<{ success: boolean; data: Periodo }>(this.apiUrl, data);
  }

  actualizarPeriodo(id: string, data: Partial<Periodo>): Observable<{ success: boolean; data: Periodo }> {
    return this.http.put<{ success: boolean; data: Periodo }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarPeriodo(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
