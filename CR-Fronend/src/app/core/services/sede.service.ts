import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Sede {
  id: string;
  nombre: string;
  direccion?: string | null;
  telefono?: string | null;
  estado?: string | null;
}

export interface SedeResponse {
  success: boolean;
  data: Sede[];
}

@Injectable({ providedIn: 'root' })
export class SedeService {
  private readonly apiUrl = environment.apiUrl + '/sedes';

  constructor(private http: HttpClient) {}

  getSedes(): Observable<SedeResponse> {
    return this.http.get<SedeResponse>(this.apiUrl);
  }

  getSede(id: string): Observable<{ success: boolean; data: Sede }> {
    return this.http.get<{ success: boolean; data: Sede }>(`${this.apiUrl}/${id}`);
  }

  crearSede(data: Partial<Sede>): Observable<{ success: boolean; data: Sede }> {
    return this.http.post<{ success: boolean; data: Sede }>(this.apiUrl, data);
  }

  actualizarSede(id: string, data: Partial<Sede>): Observable<{ success: boolean; data: Sede }> {
    return this.http.put<{ success: boolean; data: Sede }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarSede(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
