import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
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

  private documentosSubject = new BehaviorSubject<MiDocumento[]>([]);
  public documentos$ = this.documentosSubject.asObservable();

  constructor(private http: HttpClient) {}

  getMisDocumentos(): Observable<{ success: boolean; data: MiDocumento[] }> {
    return this.http.get<{ success: boolean; data: MiDocumento[] }>(
      `${this.apiUrl}/mis-documentos`
    ).pipe(
      tap(res => {
        if (res.success) {
          this.documentosSubject.next(res.data);
        }
      })
    );
  }

  marcarVisto(id: string): Observable<{ success: boolean; data: MiDocumento }> {
    return this.http.patch<{ success: boolean; data: MiDocumento }>(
      `${this.apiUrl}/mis-documentos/${id}/visto`, {}
    ).pipe(
      tap(res => {
        if (res.success) {
          this.getMisDocumentos().subscribe();
        }
      })
    );
  }

  firmar(id: string, password: string): Observable<{ success: boolean; message: string; data: MiDocumento }> {
    return this.http.post<{ success: boolean; message: string; data: MiDocumento }>(
      `${this.apiUrl}/mis-documentos/${id}/firmar`,
      { password }
    ).pipe(
      tap(res => {
        if (res.success) {
          this.getMisDocumentos().subscribe();
        }
      })
    );
  }
}
