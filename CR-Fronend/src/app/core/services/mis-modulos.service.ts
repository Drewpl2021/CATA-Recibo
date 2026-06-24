import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface ModuloHijo {
  id: string;
  nombre: string;
  ruta: string;
  icono: string;
  orden: number;
}

export interface ModuloPadre {
  id: string;
  nombre: string;
  icono: string;
  orden: number;
  modulos: ModuloHijo[];
}

@Injectable({ providedIn: 'root' })
export class MisModulosService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMisModulos(): Observable<{ success: boolean; data: ModuloPadre[] }> {
    return this.http.get<{ success: boolean; data: ModuloPadre[] }>(`${this.apiUrl}/mis-modulos`);
  }
}
