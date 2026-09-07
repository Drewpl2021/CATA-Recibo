import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiMessageResponse, ApiResponse, Pagina, ParametrosPagina } from './api-response.model';

/**
 * CRUD genérico reutilizable. Antes, cada servicio (área, cargo, sede,
 * periodo, concepto, planilla...) repetía exactamente los mismos 5 métodos
 * con el mismo wrapper { success, data }. Ahora un servicio solo declara
 * su endpoint y hereda todo:
 *
 *   @Injectable({ providedIn: 'root' })
 *   export class AreaService extends EntityDataService<Area> {
 *     constructor(http: HttpClient) { super(http, END_POINTS.configuracion.areas); }
 *   }
 *
 * Si una entidad necesita algo extra (ej. aplicar concepto a un grupo), se
 * agrega solo ese método en su servicio; el CRUD base ya viene resuelto.
 */
export abstract class EntityDataService<T> {
  protected readonly baseUrl: string;

  protected constructor(
    protected readonly http: HttpClient,
    protected readonly endPoint: string
  ) {
    this.baseUrl = `${environment.apiUrl}/${endPoint}`;
  }

  /** GET /recurso — opcionalmente con query params (?empleado_id=..., ?mes=...). */
  getAll(filtros?: Record<string, string | number | boolean | undefined | null>): Observable<ApiResponse<T[]>> {
    return this.http.get<ApiResponse<T[]>>(this.baseUrl, { params: this.construirParams(filtros) });
  }

  /**
   * GET /recurso?page=&size=&search= — una página, contada por el backend.
   *
   * La diferencia con getAll() no es el endpoint sino quién hace el trabajo:
   * acá el servidor corta y cuenta, y el navegador recibe solo las filas que
   * va a pintar más el total. getAll() sigue existiendo para los desplegables
   * de los formularios, que necesitan la lista entera.
   */
  getPagina(
    parametros: ParametrosPagina & Record<string, string | number | boolean | undefined | null> = {}
  ): Observable<ApiResponse<Pagina<T>>> {
    const conDefectos = { page: 0, size: 10, ...parametros };
    return this.http.get<ApiResponse<Pagina<T>>>(this.baseUrl, { params: this.construirParams(conDefectos) });
  }

  /** GET /recurso/{id} */
  getById(id: string | number): Observable<ApiResponse<T>> {
    return this.http.get<ApiResponse<T>>(`${this.baseUrl}/${id}`);
  }

  /** POST /recurso */
  create<P = Partial<T>>(payload: P): Observable<ApiResponse<T>> {
    return this.http.post<ApiResponse<T>>(this.baseUrl, payload);
  }

  /** PUT /recurso/{id} */
  update<P = Partial<T>>(id: string | number, payload: P): Observable<ApiResponse<T>> {
    return this.http.put<ApiResponse<T>>(`${this.baseUrl}/${id}`, payload);
  }

  /** DELETE /recurso/{id} — en este backend casi todo es soft-delete. */
  delete(id: string | number): Observable<ApiMessageResponse> {
    return this.http.delete<ApiMessageResponse>(`${this.baseUrl}/${id}`);
  }

  /** Descarta claves vacías para no mandar "?mes=&anio=" al backend. */
  protected construirParams(filtros?: Record<string, string | number | boolean | undefined | null>): HttpParams {
    let params = new HttpParams();
    if (!filtros) return params;

    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor !== undefined && valor !== null && valor !== '') {
        params = params.set(clave, String(valor));
      }
    });
    return params;
  }
}
