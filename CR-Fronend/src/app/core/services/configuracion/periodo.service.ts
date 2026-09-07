import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Periodo, GeneracionMasivaPlanilla } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class PeriodoService extends EntityDataService<Periodo> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.periodos);
  }

  /**
   * POST /periodos/{id}/generar-planilla
   *
   * Arma la planilla del mes. Sin `empleado_ids` va a TODO el personal
   * activo; con la lista, solo a esos. El backend acepta además acotar por
   * area_id / cargo_id / sede_id, aunque la pantalla prefiere mandar la
   * lista explícita para que se vea a quién le va a caer.
   */
  generarPlanillaMasiva(
    periodoId: string,
    mes: number,
    anio: number,
    empleadoIds?: string[]
  ): Observable<ApiResponse<GeneracionMasivaPlanilla>> {
    const cuerpo: Record<string, unknown> = { mes, anio };
    if (empleadoIds?.length) cuerpo['empleado_ids'] = empleadoIds;

    return this.http.post<ApiResponse<GeneracionMasivaPlanilla>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.generarPlanillaPeriodo(periodoId)}`,
      cuerpo
    );
  }
}
