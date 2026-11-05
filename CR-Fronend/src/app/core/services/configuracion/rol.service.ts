import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Rol } from '../../models';
import { ApiResponse, END_POINTS, EntityDataService } from '../../utils';

/** RRHH solo puede leer roles; crear/editar/borrar es exclusivo de Admin (backend). */
@Injectable({ providedIn: 'root' })
export class RolService extends EntityDataService<Rol> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.roles);
  }

  /** Da o quita un solo módulo: es a la vez su menú y su permiso real. */
  cambiarModulo(rolId: string, moduloId: string, activo: boolean): Observable<ApiResponse<{ activo: boolean }>> {
    return this.http.put<ApiResponse<{ activo: boolean }>>(`${this.baseUrl}/${rolId}/modules/${moduloId}`, { activo });
  }
}
