import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Expediente, FilaExpediente, FiltroExpedientes, ResumenExpedientes } from '../../models';
import { ApiResponse, END_POINTS_ACCIONES, Pagina } from '../../utils';

/**
 * Documentos del personal: el expediente de cada trabajador.
 *
 * Solo lee. Subir, descargar y quitar un documento siguen yendo por
 * DocumentoService, que es el mismo camino que usa el resto del sistema.
 */
@Injectable({ providedIn: 'root' })
export class ExpedienteService {
  private http = inject(HttpClient);

  /** GET /expedientes — el personal, con lo que le falta a cada uno. */
  paginar(filtros: {
    page: number;
    size: number;
    search?: string;
    filtro?: FiltroExpedientes;
  }): Observable<ApiResponse<Pagina<FilaExpediente> & { resumen: ResumenExpedientes }>> {
    const params: Record<string, string | number> = { page: filtros.page, size: filtros.size };
    if (filtros.search) params['search'] = filtros.search;
    if (filtros.filtro) params['filtro'] = filtros.filtro;

    return this.http.get<ApiResponse<Pagina<FilaExpediente> & { resumen: ResumenExpedientes }>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.expedientes}`,
      { params }
    );
  }

  /** GET /expedientes/{empleadoId} — todo lo que hay a su nombre. */
  obtener(empleadoId: string): Observable<ApiResponse<Expediente>> {
    return this.http.get<ApiResponse<Expediente>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.expediente(empleadoId)}`
    );
  }
}
