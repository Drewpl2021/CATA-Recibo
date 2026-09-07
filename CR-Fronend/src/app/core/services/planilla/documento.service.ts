import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Documento, DocumentoPayload } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class DocumentoService extends EntityDataService<Documento> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.documentos);
  }

  /** GET /documentos?empleado_id=&tipo= */
  listar(filtros?: { empleado_id?: string; tipo?: string; contrato_id?: string }): Observable<ApiResponse<Documento[]>> {
    return this.getAll(filtros);
  }

  crear(payload: DocumentoPayload) {
    return this.create<DocumentoPayload>(payload);
  }

  /** GET /documentos/{id}/descargar — devuelve el PDF ya guardado en disco. */
  descargar(documentoId: string): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/${END_POINTS_ACCIONES.descargarDocumento(documentoId)}`, {
      responseType: 'blob',
    });
  }

  /** POST /documentos/{id}/firmar-empleador — RRHH firma el lado del empleador. */
  firmarComoEmpleador(documentoId: string, password: string): Observable<ApiResponse<Documento>> {
    return this.http.post<ApiResponse<Documento>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.firmarComoEmpleador(documentoId)}`,
      { password }
    );
  }
}
