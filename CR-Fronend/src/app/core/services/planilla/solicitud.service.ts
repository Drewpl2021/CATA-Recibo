import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiResponse } from '../../utils';
import { environment } from '../../../../environments/environment';

export type TipoSolicitud = 'permiso' | 'licencia' | 'descanso_medico' | 'justificar_falta' | 'cambio_cuenta';
export type EstadoSolicitud = 'pendiente' | 'aprobada' | 'rechazada' | 'cancelada' | 'anulada';

/** Una solicitud como la devuelve el servidor (la cuenta, enmascarada). */
export interface Solicitud {
  id: string;
  tipo: TipoSolicitud;
  tipo_texto: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  hora_inicio: string | null;
  hora_fin: string | null;
  dias: number | null;
  con_goce: boolean | null;
  motivo: string;
  datos: { destino: 'sueldo' | 'cts'; entidad: string; numero: string; cci?: string } | null;
  tiene_sustento: boolean;
  sustento_nombre: string | null;
  estado: EstadoSolicitud;
  respuesta: string | null;
  resuelto_por: string | null;
  resuelto_at: string | null;
  pedida: string;
  empleado: { id: string; dni: string; nombre: string; area: string | null; cargo: string | null } | null;
}

/** Las faltas de CTS que RR.HH. podría restar al aprobar una justificación. */
export interface FaltaCts {
  anio: number;
  periodo: string;
  anotadas: number;
  a_restar: number;
  se_puede: boolean;
  motivo: string | null;
}

/** Lo que pasaría al aprobar: lo dice el servidor, la pantalla solo lo muestra. */
export interface PreviaSolicitud {
  avisos: string[];
  faltas_cts: FaltaCts[];
  datos: { destino: 'sueldo' | 'cts'; entidad: string; numero: string; cci?: string } | null;
  sustento_visto: boolean;
  propia: boolean;
}

/**
 * Las solicitudes a RR.HH.: «Mis solicitudes» del trabajador y la bandeja de
 * RR.HH. (Solicitudes). Lo que hace cada aprobación en la planilla y la CTS
 * lo decide el servidor (EfectosDeSolicitud).
 */
@Injectable({ providedIn: 'root' })
export class SolicitudService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  // ── El trabajador ──
  mias(): Observable<ApiResponse<Solicitud[]>> {
    return this.http.get<ApiResponse<Solicitud[]>>(`${this.api}/my-requests`);
  }

  /** Va como formulario: lleva el sustento. */
  pedir(datos: FormData): Observable<ApiResponse<Solicitud>> {
    return this.http.post<ApiResponse<Solicitud>>(`${this.api}/my-requests`, datos);
  }

  cancelar(id: string): Observable<ApiResponse<{ message: string }>> {
    return this.http.delete<ApiResponse<{ message: string }>>(`${this.api}/my-requests/${id}`);
  }

  miSustento(id: string): Observable<Blob> {
    return this.http.get(`${this.api}/my-requests/${id}/support`, { responseType: 'blob' });
  }

  // ── RR.HH. ──
  todas(filtros: { estado?: string; tipo?: string } = {}): Observable<ApiResponse<{ filas: Solicitud[]; pendientes: number }>> {
    let params = new HttpParams();
    if (filtros.estado) params = params.set('estado', filtros.estado);
    if (filtros.tipo) params = params.set('tipo', filtros.tipo);
    return this.http.get<ApiResponse<{ filas: Solicitud[]; pendientes: number }>>(`${this.api}/requests`, { params });
  }

  previa(id: string, conGoce: boolean | null = null): Observable<ApiResponse<PreviaSolicitud>> {
    const params = conGoce === null ? undefined : new HttpParams().set('con_goce', conGoce ? '1' : '0');
    return this.http.get<ApiResponse<PreviaSolicitud>>(`${this.api}/requests/${id}/preview`, { params });
  }

  sustento(id: string): Observable<Blob> {
    return this.http.get(`${this.api}/requests/${id}/support`, { responseType: 'blob' });
  }

  aprobar(id: string, datos: { con_goce?: boolean | null; restar_faltas?: boolean; respuesta?: string | null }): Observable<ApiResponse<Solicitud>> {
    return this.http.post<ApiResponse<Solicitud>>(`${this.api}/requests/${id}/approve`, datos);
  }

  rechazar(id: string, respuesta: string): Observable<ApiResponse<Solicitud>> {
    return this.http.post<ApiResponse<Solicitud>>(`${this.api}/requests/${id}/reject`, { respuesta });
  }

  anular(id: string, respuesta: string): Observable<ApiResponse<Solicitud & { avisos: string[] }>> {
    return this.http.post<ApiResponse<Solicitud & { avisos: string[] }>>(`${this.api}/requests/${id}/annul`, { respuesta });
  }
}
