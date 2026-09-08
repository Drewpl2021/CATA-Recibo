import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Area {
  id: string;
  nombre: string;
  descripcion?: string | null;
}

@Injectable({ providedIn: 'root' })
export class AreaService {
  private apiUrl = environment.apiUrl + '/areas';

  constructor(private http: HttpClient) {}

  getAreas(): Observable<{ success: boolean; data: Area[] }> {
    return this.http.get<{ success: boolean; data: Area[] }>(this.apiUrl);
  }

  getArea(id: string): Observable<{ success: boolean; data: Area }> {
    return this.http.get<{ success: boolean; data: Area }>(`${this.apiUrl}/${id}`);
  }

  crearArea(data: Partial<Area>): Observable<{ success: boolean; data: Area }> {
    return this.http.post<{ success: boolean; data: Area }>(this.apiUrl, data);
  }

  actualizarArea(id: string, data: Partial<Area>): Observable<{ success: boolean; data: Area }> {
    return this.http.put<{ success: boolean; data: Area }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarArea(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
