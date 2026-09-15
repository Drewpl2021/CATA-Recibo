import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Planilla, PlanillaPayload } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService, Pagina } from '../../utils';

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
    /** Las filas de UNA planilla con nombre. */
    corrida_id?: string;
    /** Las que no están en ninguna: el grupo "Sin agrupar". */
    sin_corrida?: boolean;
    page?: number;
    size?: number;
    search?: string;
  }): Observable<ApiResponse<PaginaPlanillas>> {
    return this.getPagina(filtros) as Observable<ApiResponse<PaginaPlanillas>>;
  }

  crear(payload: PlanillaPayload | Partial<Planilla>) {
    return this.create<PlanillaPayload | Partial<Planilla>>(payload);
  }

  /**
   * POST /planilla/{id}/conceptos — deja sus líneas como diga la pantalla.
   *
   * Se manda la lista entera de una vez y el backend la sincroniza en una
   * transacción: crea o actualiza los que traen monto y borra los que van en
   * cero. Los conceptos que NO se nombran se quedan como estaban, así que las
   * líneas que calcula el motor (pensión, EsSalud, Renta de 5ta, Asignación
   * Familiar) no se pierden — y además el backend las rechaza si se intentan
   * mandar a mano.
   */
  sincronizarConceptos(
    planillaId: string,
    conceptos: { nombre: string; monto: number | null }[]
  ): Observable<ApiResponse<{ planilla: Planilla; resumen: { puestos: number; quitados: number } }>> {
    return this.http.post<ApiResponse<{ planilla: Planilla; resumen: { puestos: number; quitados: number } }>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.sincronizarConceptosPlanilla(planillaId)}`,
      { conceptos }
    );
  }

  /**
   * GET /planilla/exportar — el reporte completo en Excel.
   *
   * Toma los mismos filtros que el listado a propósito: lo que se ve en
   * pantalla es lo que baja. Vuelve como blob porque es un archivo, no el
   * { success, data } del resto de la API.
   */
  exportar(filtros: {
    empleado_id?: string;
    mes?: number | string;
    anio?: number | string;
    periodo_id?: string;
    corrida_id?: string;
    sin_corrida?: boolean;
    search?: string;
  }): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/${END_POINTS_ACCIONES.exportarPlanilla}`, {
      params: this.construirParams(filtros),
      responseType: 'blob',
    });
  }
}
