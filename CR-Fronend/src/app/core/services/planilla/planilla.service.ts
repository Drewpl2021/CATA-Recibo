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

  /**
   * GET /planilla?empleado_id=&mes=&anio=&periodo_id=&page=&size=&search=
   *     &sede_id=&area_id=&cargo_id=&tipo_contrato_id=&estado_empleado=
   */
  listarPagina(filtros: {
    empleado_id?: string;
    mes?: number | string;
    anio?: number | string;
    periodo_id?: string;
    /** Los del trabajador: la planilla no los guarda, los tiene su empleado. */
    sede_id?: string;
    area_id?: string;
    cargo_id?: string;
    tipo_contrato_id?: string;
    estado_empleado?: string;
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
   * PUT /planilla/{id}/recalcular — vuelve a tomar el sueldo ACTUAL de la
   * ficha del trabajador, lo prorratea de nuevo por los días que le tocan
   * ese mes, y regenera pensión, EsSalud, Asignación Familiar y Renta de
   * 5ta. Hace falta porque generar la planilla es una foto: si el sueldo
   * de la ficha cambia después, la planilla se queda con el viejo hasta
   * que alguien pida esto. No toca las líneas que RR.HH. agregó a mano.
   */
  recalcular(planillaId: string): Observable<ApiResponse<Planilla>> {
    return this.http.put<ApiResponse<Planilla>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.recalcularPlanilla(planillaId)}`,
      {}
    );
  }

  /**
   * GET /payrolls/renta-5ta — la Renta de 5ta de ese mes, SIN guardar nada.
   * Con `sueldo`, la calcula con ese (lo que se está escribiendo en pantalla).
   */
  rentaQuinta(params: { empleado_id: string; mes: number; anio: number; sueldo?: number | null; bonificacion_cargo?: number | null }): Observable<ApiResponse<{ monto: number }>> {
    const query: Record<string, string> = {
      empleado_id: params.empleado_id,
      mes: String(params.mes),
      anio: String(params.anio),
    };
    if (params.sueldo !== null && params.sueldo !== undefined) query['sueldo'] = String(params.sueldo);
    if (params.bonificacion_cargo) query['bonificacion_cargo'] = String(params.bonificacion_cargo);

    return this.http.get<ApiResponse<{ monto: number }>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.rentaQuinta}`,
      { params: query }
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
    /** Los del trabajador: la planilla no los guarda, los tiene su empleado. */
    sede_id?: string;
    area_id?: string;
    cargo_id?: string;
    tipo_contrato_id?: string;
    estado_empleado?: string;
  }, ids?: string[]): Observable<Blob> {
    const url = `${environment.apiUrl}/${END_POINTS_ACCIONES.exportarPlanilla}`;
    // Las marcadas van en el cuerpo: con cientos no caben en la dirección.
    if (ids?.length) {
      return this.http.post(url, { ...filtros, ids }, { responseType: 'blob' });
    }
    return this.http.get(url, { params: this.construirParams(filtros), responseType: 'blob' });
  }
}
