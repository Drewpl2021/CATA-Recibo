import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { SaldoVacaciones, Vacacion, VacacionPayload } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Vacaciones.
 *
 * El listado lo recorta el backend según quién pregunta: RR.HH. recibe las de
 * todo el personal y el trabajador solo las suyas. Por eso la pantalla de
 * "Mis Vacaciones" no manda ningún empleado_id — y aunque lo mandara, a un
 * docente se le ignora.
 */
@Injectable({ providedIn: 'root' })
export class VacacionService extends EntityDataService<Vacacion> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.vacaciones);
  }

  listar(filtros?: { empleado_id?: string; estado?: string }): Observable<ApiResponse<Vacacion[]>> {
    return this.getAll(filtros);
  }

  /**
   * Días ganados, gastados y disponibles.
   *
   * Sin empleado_id devuelve los del usuario de la sesión; con él (solo RR.HH.)
   * los de cualquiera.
   */
  saldo(filtros?: { empleado_id?: string; anio?: number }): Observable<ApiResponse<SaldoVacaciones>> {
    let params = new HttpParams();
    if (filtros?.empleado_id) params = params.set('empleado_id', filtros.empleado_id);
    if (filtros?.anio) params = params.set('anio', String(filtros.anio));

    return this.http.get<ApiResponse<SaldoVacaciones>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.saldoVacaciones}`,
      { params }
    );
  }

  /** Al crear, el backend devuelve además cuántos días le quedan al empleado. */
  crear(payload: VacacionPayload): Observable<ApiResponse<Vacacion> & { dias_restantes: number }> {
    return this.http.post<ApiResponse<Vacacion> & { dias_restantes: number }>(this.baseUrl, payload);
  }

  /** Aprobar o rechazar. Solo RR.HH. y Administración. */
  resolver(
    id: string,
    estado: 'aprobado' | 'rechazado' | 'pendiente',
    observacion?: string | null
  ): Observable<ApiResponse<Vacacion>> {
    return this.update(id, { estado, observacion: observacion ?? null } as Partial<Vacacion>);
  }
}
