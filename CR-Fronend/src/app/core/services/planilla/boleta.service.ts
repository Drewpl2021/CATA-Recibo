import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { GeneracionMasivaBoletas, Planilla } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Generación y descarga de boletas en PDF (no es un CRUD, por eso no hereda). */
@Injectable({ providedIn: 'root' })
export class BoletaService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /** GET /mi-planilla — planillas del empleado autenticado. */
  getMiPlanilla(filtros?: { mes?: number; anio?: number }): Observable<ApiResponse<Planilla[]>> {
    const params: Record<string, string> = {};
    if (filtros?.mes) params['mes'] = String(filtros.mes);
    if (filtros?.anio) params['anio'] = String(filtros.anio);
    return this.http.get<ApiResponse<Planilla[]>>(`${this.apiUrl}/${END_POINTS.autoservicio.miPlanilla}`, { params });
  }

  /** GET /mis-boletas/{mes}/{anio} — PDF propio del empleado. */
  descargarMiBoleta(mes: number, anio: number | string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.miBoleta(mes, anio)}`, { responseType: 'blob' });
  }

  /** GET /boleta/{empleado_id}/{mes}/{anio} — PDF de cualquier empleado (RRHH/Admin). */
  generarBoletaEmpleado(empleadoId: string, mes: number, anio: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.boletaIndividual(empleadoId, mes, anio)}`, {
      responseType: 'blob',
    });
  }

  /** POST /boletas/generar-masivo */
  generarMasivo(mes: number, anio: number): Observable<GeneracionMasivaBoletas> {
    return this.http.post<GeneracionMasivaBoletas>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.boletasMasivo}`,
      { mes, anio }
    );
  }
}
