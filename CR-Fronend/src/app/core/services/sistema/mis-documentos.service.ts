import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { Documento } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Autoservicio de documentos del empleado autenticado. Mantiene un
 * BehaviorSubject para que la campanita del layout se entere sola cuando
 * cambia el estado de firma de algún documento.
 */
@Injectable({ providedIn: 'root' })
export class MisDocumentosService {
  private readonly apiUrl = environment.apiUrl;

  private documentosSubject = new BehaviorSubject<Documento[]>([]);
  public documentos$ = this.documentosSubject.asObservable();

  constructor(private http: HttpClient) {}

  getMisDocumentos(): Observable<ApiResponse<Documento[]>> {
    return this.http.get<ApiResponse<Documento[]>>(`${this.apiUrl}/${END_POINTS.autoservicio.misDocumentos}`).pipe(
      tap((res) => {
        if (res.success) this.documentosSubject.next(res.data);
      })
    );
  }

  marcarVisto(id: string): Observable<ApiResponse<Documento>> {
    return this.http
      .patch<ApiResponse<Documento>>(`${this.apiUrl}/${END_POINTS_ACCIONES.marcarDocumentoVisto(id)}`, {})
      .pipe(tap((res) => { if (res.success) this.getMisDocumentos().subscribe(); }));
  }

  firmar(id: string, password: string): Observable<ApiResponse<Documento> & { message?: string }> {
    return this.http
      .post<ApiResponse<Documento> & { message?: string }>(
        `${this.apiUrl}/${END_POINTS_ACCIONES.firmarMiDocumento(id)}`,
        { password }
      )
      .pipe(tap((res) => { if (res.success) this.getMisDocumentos().subscribe(); }));
  }
}
