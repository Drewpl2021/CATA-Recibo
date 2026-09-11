import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RegistroAuditoria } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

/**
 * El registro de auditoría. Solo se lee: el backend no ofrece crear, editar
 * ni borrar filas, y aquí tampoco se usa.
 */
@Injectable({ providedIn: 'root' })
export class AuditoriaService extends EntityDataService<RegistroAuditoria> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.admin.auditoria);
  }
}
