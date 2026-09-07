import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Planilla, PlanillaPayload } from '../../models';
import { ApiResponse, END_POINTS, EntityDataService, Pagina } from '../../utils';

/**
 * Una página de planillas trae, además de las filas, la masa salarial de
 * TODAS las que pasan el filtro. Ese total lo suma la base de datos: hacerlo
 * acá daría solo el de las filas de la página que se está viendo.
 */
export interface PaginaPlanillas extends Pagina<Planilla> {
  masaSalarial: number;
}

@Injectable({ providedIn: 'root' })
export class PlanillaService extends EntityDataService<Planilla> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.planilla);
  }

  /** GET /planilla?empleado_id=&mes=&anio=&periodo_id= */
  listar(filtros?: {
    empleado_id?: string;
    mes?: number;
    anio?: number;
    periodo_id?: string;
  }): Observable<ApiResponse<Planilla[]>> {
    return this.getAll(filtros);
  }

  /** GET /planilla?empleado_id=&mes=&anio=&periodo_id=&page=&size=&search= */
  listarPagina(filtros: {
    empleado_id?: string;
    mes?: number | string;
    anio?: number | string;
    periodo_id?: string;
    page?: number;
    size?: number;
    search?: string;
  }): Observable<ApiResponse<PaginaPlanillas>> {
    return this.getPagina(filtros) as Observable<ApiResponse<PaginaPlanillas>>;
  }

  crear(payload: PlanillaPayload | Partial<Planilla>) {
    return this.create<PlanillaPayload | Partial<Planilla>>(payload);
  }
}
