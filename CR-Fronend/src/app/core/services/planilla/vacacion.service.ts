import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Vacacion, VacacionPayload } from '../../models';
import { ApiResponse, END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class VacacionService extends EntityDataService<Vacacion> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.vacaciones);
  }

  listar(filtros?: { empleado_id?: string; estado?: string }): Observable<ApiResponse<Vacacion[]>> {
    return this.getAll(filtros);
  }

  /** Al crear, el backend devuelve además cuántos días le quedan al empleado. */
  crear(payload: VacacionPayload): Observable<ApiResponse<Vacacion> & { dias_restantes: number }> {
    return this.http.post<ApiResponse<Vacacion> & { dias_restantes: number }>(this.baseUrl, payload);
  }
}
