import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { Documento } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, Pagina, ParametrosPagina } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Lo que se le puede pedir a /mis-documentos, además de la página. */
export interface FiltrosMisDocumentos extends ParametrosPagina {
  tipo?: string;
  anio?: number | string;
  sin_firmar?: boolean;
}

/**
 * Autoservicio de documentos del trabajador de la sesión.
 *
 * Dos maneras de pedir, y la diferencia importa:
 *
 *   paginar()             una página, para la pantalla que la va a pintar.
 *   refrescarPendientes() SOLO lo que le falta firmar, para la campanita.
 *
 * Antes había un único método que se traía la carrera entera del trabajador
 * —todas sus boletas de todos los años— y las tres pantallas se repartían
 * ese montón filtrando en el navegador. La campanita, que solo enseña los
 * pendientes, era la que más pedía: se cargaba en cada entrada al sistema.
 */
@Injectable({ providedIn: 'root' })
export class MisDocumentosService {
  private readonly apiUrl = environment.apiUrl;

  /**
   * Los documentos sin firmar, para el globito y el desplegable de la
   * campana. No es la lista completa: es a propósito.
   */
  private documentosSubject = new BehaviorSubject<Documento[]>([]);
  public documentos$ = this.documentosSubject.asObservable();

  /** Cuántos avisos caben en la campana sin volverla un listado. */
  private readonly PENDIENTES_EN_CAMPANA = 20;

  constructor(private http: HttpClient) {}

  /** GET /mis-documentos?page=&size=&tipo=&anio=&search= — una página. */
  paginar(filtros: FiltrosMisDocumentos = {}): Observable<ApiResponse<Pagina<Documento>>> {
    const conDefectos: Record<string, string> = {};
    Object.entries({ page: 0, size: 10, ...filtros }).forEach(([clave, valor]) => {
      if (valor !== undefined && valor !== null && valor !== '') conDefectos[clave] = String(valor);
    });

    return this.http.get<ApiResponse<Pagina<Documento>>>(
      `${this.apiUrl}/${END_POINTS.autoservicio.misDocumentos}`,
      { params: conDefectos }
    );
  }

  /** Recarga los pendientes de la campana. */
  refrescarPendientes(): Observable<ApiResponse<Pagina<Documento>>> {
    return this.paginar({ sin_firmar: true, page: 0, size: this.PENDIENTES_EN_CAMPANA }).pipe(
      tap((res) => {
        if (res.success) this.documentosSubject.next(res.data.content);
      })
    );
  }

  marcarVisto(id: string): Observable<ApiResponse<Documento>> {
    return this.http
      .patch<ApiResponse<Documento>>(`${this.apiUrl}/${END_POINTS_ACCIONES.marcarDocumentoVisto(id)}`, {})
      .pipe(tap((res) => { if (res.success) this.refrescarPendientes().subscribe(); }));
  }

  firmar(id: string, password: string): Observable<ApiResponse<Documento> & { message?: string }> {
    return this.http
      .post<ApiResponse<Documento> & { message?: string }>(
        `${this.apiUrl}/${END_POINTS_ACCIONES.firmarMiDocumento(id)}`,
        { password }
      )
      .pipe(tap((res) => { if (res.success) this.refrescarPendientes().subscribe(); }));
  }
}
