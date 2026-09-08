import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Cargo {
  id: string;
  nombre: string;
  descripcion?: string | null;
}

@Injectable({ providedIn: 'root' })
export class CargoService {
  private apiUrl = environment.apiUrl + '/cargos';

  constructor(private http: HttpClient) {}

  getCargos(): Observable<{ success: boolean; data: Cargo[] }> {
    return this.http.get<{ success: boolean; data: Cargo[] }>(this.apiUrl);
  }

  getCargo(id: string): Observable<{ success: boolean; data: Cargo }> {
    return this.http.get<{ success: boolean; data: Cargo }>(`${this.apiUrl}/${id}`);
  }

  crearCargo(data: Partial<Cargo>): Observable<{ success: boolean; data: Cargo }> {
    return this.http.post<{ success: boolean; data: Cargo }>(this.apiUrl, data);
  }

  actualizarCargo(id: string, data: Partial<Cargo>): Observable<{ success: boolean; data: Cargo }> {
    return this.http.put<{ success: boolean; data: Cargo }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarCargo(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
