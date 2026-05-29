import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Documento {
  id: string;
  empleado_id: string;
  tipo: string;
  archivo: string;
  firmado_por: string | null;
  codigo_firma: string | null;
  fecha_firma: string | null;
  created_at: string;
  updated_at: string;
}

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

export interface DocumentosResponse {
  data: Documento[];
  [key: string]: any;
}

export interface PlanillaResponse {
  data: Planilla[];
  [key: string]: any;
}

@Injectable({ providedIn: 'root' })
export class BoletasService {
  private readonly apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  /** Obtiene todas las boletas (documentos tipo=boleta) de un empleado */
  getMisDocumentos(empleadoId: string, tipo: string = 'boleta'): Observable<any> {
    return this.http.get(`${this.apiUrl}/documentos`, {
      params: { empleado_id: empleadoId, tipo }
    });
  }

  /** Obtiene los registros de planilla de un empleado filtrados por año */
  getPlanilla(empleadoId: string, anio: string): Observable<any> {
    return this.http.get(`${this.apiUrl}/planilla`, {
      params: { empleado_id: empleadoId, anio }
    });
  }

  /** Descarga el PDF de una boleta */
  descargarBoleta(empleadoId: string, mes: number, anio: string): Observable<Blob> {
    const url = `${this.apiUrl}/boleta/${empleadoId}/${mes}/${anio}`;
    return this.http.get(url, { responseType: 'blob' });
  }
}
