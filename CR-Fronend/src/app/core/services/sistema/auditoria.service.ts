import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { DetalleAuditoria, RegistroAuditoria } from '../../models';
import { Observable } from 'rxjs';
import { ApiResponse, END_POINTS, EntityDataService } from '../../utils';

/**
 * El registro de auditoría. Solo se lee: el backend no ofrece crear, editar
 * ni borrar filas, y aquí tampoco se usa.
 */
@Injectable({ providedIn: 'root' })
export class AuditoriaService extends EntityDataService<RegistroAuditoria> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.admin.auditoria);
  }

  /** El «Ver más»: el registro en palabras, quién, sobre qué y lo que pasó alrededor. */
  detalle(id: number): Observable<ApiResponse<DetalleAuditoria>> {
    return this.http.get<ApiResponse<DetalleAuditoria>>(`${this.baseUrl}/${id}`);
  }
}
