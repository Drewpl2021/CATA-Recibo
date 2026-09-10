import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PlanillaCorrida, PlanillaCorridaPayload, ResultadoGeneracion } from '../../models';
import { ApiResponse, END_POINTS, EntityDataService } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Las corridas de planilla: el primer nivel de la pantalla de Planillas.
 *
 * El CRUD lo hereda; lo de aquí abajo son las acciones que no son CRUD:
 * generar, mover trabajadores dentro y sacarlos.
 */
@Injectable({ providedIn: 'root' })
export class PlanillaCorridaService extends EntityDataService<PlanillaCorrida> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.corridas);
  }

  /**
   * Crea la planilla y, con `generar`, le arma dentro las filas del grupo en
   * la misma llamada. Es el botón "Generar planillas".
   */
  crear(payload: PlanillaCorridaPayload): Observable<ApiResponse<ResultadoGeneracion | PlanillaCorrida>> {
    return this.create<PlanillaCorridaPayload>(payload) as unknown as
      Observable<ApiResponse<ResultadoGeneracion | PlanillaCorrida>>;
  }

  /** Le arma la planilla a más gente dentro de una corrida que ya existe. */
  generar(
    corridaId: string,
    grupo: { empleado_ids?: string[]; area_id?: string | null; cargo_id?: string | null; sede_id?: string | null }
  ): Observable<ApiResponse<ResultadoGeneracion>> {
    return this.http.post<ApiResponse<ResultadoGeneracion>>(
      `${environment.apiUrl}/${END_POINTS.planilla.corridas}/${corridaId}/generar`,
      grupo
    );
  }

  /** Mete planillas que ya existen dentro de esta corrida. */
  mover(corridaId: string, planillaIds: string[]): Observable<ApiResponse<{ movidas: number; yaEstaban: number; corrida: PlanillaCorrida }>> {
    return this.http.post<ApiResponse<{ movidas: number; yaEstaban: number; corrida: PlanillaCorrida }>>(
      `${environment.apiUrl}/${END_POINTS.planilla.corridas}/${corridaId}/mover`,
      { planilla_ids: planillaIds }
    );
  }

  /** Las saca de su corrida y las deja en "Sin agrupar". No borra nada. */
  sacar(planillaIds: string[]): Observable<ApiResponse<{ sacadas: number }>> {
    return this.http.post<ApiResponse<{ sacadas: number }>>(
      `${environment.apiUrl}/${END_POINTS.planilla.corridas}/sacar`,
      { planilla_ids: planillaIds }
    );
  }
}
