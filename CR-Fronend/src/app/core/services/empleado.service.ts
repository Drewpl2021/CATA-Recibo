import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface Area {
  id: string;
  nombre: string;
}

export interface Cargo {
  id: string;
  nombre: string;
}

export interface Empleado {
  id: string;
  dni: string;
  nombre: string;
  apellido: string;
  telefono: string;
  direccion: string;
  fecha_ingreso: string;
  estado: string;
  area_id?: string;
  cargo_id?: string;
  area?: Area;
  cargo?: Cargo;
  sistema_pensiones?: string;
  afp?: string;
  cuspp?: string;
  entidad_financiera?: string;
  numero_cuenta?: string;
  tiene_hijos?: boolean;
  sueldo_base?: number;
  sede_id?: string;
  sede?: { id: string; nombre: string };
  firma_imagen?: string;
}

export interface EmpleadoResponse {
  success: boolean;
  data: Empleado | Empleado[];
}

@Injectable({
  providedIn: 'root'
})
export class EmpleadoService {
  private apiUrl = environment.apiUrl + '/empleados';

  constructor(private http: HttpClient) {}

  getEmpleados(): Observable<{success: boolean, data: Empleado[]}> {
    return this.http.get<{success: boolean, data: Empleado[]}>(this.apiUrl);
  }

  getEmpleado(id: string): Observable<{success: boolean, data: Empleado}> {
    return this.http.get<{success: boolean, data: Empleado}>(`${this.apiUrl}/${id}`);
  }

  createEmpleado(data: any): Observable<{success: boolean, data: Empleado}> {
    return this.http.post<{success: boolean, data: Empleado}>(this.apiUrl, data);
  }

  updateEmpleado(id: string, data: any): Observable<{success: boolean, data: Empleado}> {
    return this.http.put<{success: boolean, data: Empleado}>(`${this.apiUrl}/${id}`, data);
  }

  deleteEmpleado(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }

  subirFirma(id: string, file: File): Observable<{ success: boolean, data: { firma_imagen: string } }> {
    const formData = new FormData();
    formData.append('firma', file);
    return this.http.post<{ success: boolean, data: { firma_imagen: string } }>(`${this.apiUrl}/${id}/firma`, formData);
  }
}
