import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { AsignarRolesPayload, ModuloAdmin } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';

/**
 * Administración de los ítems del menú (solo Admin).
 *
 * El CRUD normal lo hereda de EntityDataService; lo único propio es
 * asignarRoles(), porque el backend guarda esa relación en su propio
 * endpoint y no como un campo más del módulo.
 */
@Injectable({ providedIn: 'root' })
export class ModuloService extends EntityDataService<ModuloAdmin> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.admin.modulos);
  }

  /**
   * Reemplaza la lista completa de roles que ven este módulo en su menú.
   * El backend hace `sync`, así que lo que no mandes se quita.
   *
   * Solo afecta a lo que se VE en la barra lateral: el permiso real sobre
   * los endpoints lo impone el middleware `rol:` del backend.
   */
  asignarRoles(moduloId: string, rolesIds: string[]): Observable<ApiResponse<ModuloAdmin>> {
    const payload: AsignarRolesPayload = { roles: rolesIds };
    const url = `${environment.apiUrl}/${END_POINTS_ACCIONES.asignarRolesModulo(moduloId)}`;
    return this.http.post<ApiResponse<ModuloAdmin>>(url, payload);
  }
}
