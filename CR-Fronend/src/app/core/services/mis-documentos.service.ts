import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface MiDocumento {
  id: string;
  empleado_id: string;
  planilla_id: string;
  tipo: string;
  archivo: string;
  estado_firma: 'pendiente' | 'visto' | 'firmado';
  fecha_visto: string | null;
  fecha_firma: string | null;
  firmado_por: string | null;
  codigo_firma: string | null;
  created_at: string;
  planilla?: {
    mes: number;
    anio: number;
  };
}

@Injectable({ providedIn: 'root' })
export class MisDocumentosService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMisDocumentos(): Observable<{ success: boolean; data: MiDocumento[] }> {
    return this.http.get<{ success: boolean; data: MiDocumento[] }>(
      `${this.apiUrl}/mis-documentos`
    );
  }

  marcarVisto(id: string): Observable<{ success: boolean; data: MiDocumento }> {
    return this.http.patch<{ success: boolean; data: MiDocumento }>(
      `${this.apiUrl}/mis-documentos/${id}/visto`, {}
    );
  }

  firmar(id: string, password: string): Observable<{ success: boolean; message: string; data: MiDocumento }> {
    return this.http.post<{ success: boolean; message: string; data: MiDocumento }>(
      `${this.apiUrl}/mis-documentos/${id}/firmar`,
      { password }
    );
  }
}
