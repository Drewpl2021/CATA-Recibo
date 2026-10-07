import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../utils';

/** Un trabajador en la lista del módulo. */
export interface FilaRenta5ta {
  id: string;
  dni: string;
  nombre: string;
  cargo: string | null;
  estado: string;
  renta_bruta: number;
  impuesto_anual: number;
  retenido: number;
  por_retener: number;
  retencion_del_mes: number;
  meses_sin_dato: number[];
}

export interface ListaRenta5ta {
  anio: number;
  mes: number;
  uit: number;
  filas: FilaRenta5ta[];
  resumen: { trabajadores: number; con_retencion: number; impuesto_anual: number; retenido: number; sin_historial: number };
}

/** Un mes de la hoja de retención, con todo el cálculo (ver MotorRenta5ta en el servidor). */
export interface MesRenta5ta {
  mes: number;
  fuente: 'planilla' | 'historial' | 'proyectado' | 'no_trabaja';
  trabaja: boolean;
  remuneracion_mes: number;
  remuneracion_mensual: number;
  meses_que_faltan: number;
  remuneracion_proyectada: number;
  gratificaciones: number;
  remuneraciones_anteriores: number;
  meses_sin_dato: number[];
  renta_bruta: number;
  deduccion: number;
  renta_neta: number;
  impuesto_anual: number;
  retenido_antes: number;
  divisor: number;
  retencion_ordinaria: number;
  extraordinario: number;
  retencion_adicional: number;
  retencion: number;
  retencion_real: number | null;
}

export interface HojaRenta5ta {
  anio: number;
  uit: number;
  empleado?: { id: string; dni: string; nombre: string; cargo: string | null; area: string | null; estado: string; sueldo_base: number; fecha_ingreso: string };
  meses: MesRenta5ta[];
  resumen: { impuesto_anual: number; retenido: number; por_retener: number; meses_con_dato: number[]; meses_sin_dato: number[] };
  recalculadas?: number;
}

export interface CargaHistorial5ta {
  meses_guardados: number;
  trabajadores: number;
  meses_en_sistema: number;
  no_encontrados: string[];
  recalculadas: number;
}

/**
 * El módulo «Renta de 5ta»: la retención de cada trabajador con el
 * procedimiento de SUNAT, su historial de los meses antes del sistema,
 * recalcular y exportar.
 */
@Injectable({ providedIn: 'root' })
export class Renta5taService {
  private http = inject(HttpClient);
  private readonly url = `${environment.apiUrl}/income-tax`;

  lista(anio: number): Observable<ApiResponse<ListaRenta5ta>> {
    return this.http.get<ApiResponse<ListaRenta5ta>>(this.url, { params: { anio: String(anio) } });
  }

  hoja(empleadoId: string, anio: number): Observable<ApiResponse<HojaRenta5ta>> {
    return this.http.get<ApiResponse<HojaRenta5ta>>(`${this.url}/${empleadoId}`, { params: { anio: String(anio) } });
  }

  /** Un mes pagado antes del sistema. Los dos vacíos: se borra ese mes. */
  guardarMes(empleadoId: string, anio: number, mes: number, remuneracion: number | null, retencion: number | null): Observable<ApiResponse<HojaRenta5ta>> {
    return this.http.put<ApiResponse<HojaRenta5ta>>(`${this.url}/${empleadoId}/history`, { anio, mes, remuneracion, retencion });
  }

  modeloHistorial(anio: number): Observable<Blob> {
    return this.http.get(`${this.url}/history/template`, { params: { anio: String(anio) }, responseType: 'blob' });
  }

  cargarHistorial(anio: number, archivo: File): Observable<ApiResponse<CargaHistorial5ta>> {
    const datos = new FormData();
    datos.append('anio', String(anio));
    datos.append('archivo', archivo);
    return this.http.post<ApiResponse<CargaHistorial5ta>>(`${this.url}/history`, datos);
  }

  recalcular(anio: number): Observable<ApiResponse<{ recalculadas: number; saltadas: number; trabajadores: number }>> {
    return this.http.post<ApiResponse<{ recalculadas: number; saltadas: number; trabajadores: number }>>(`${this.url}/recalculate`, { anio });
  }

  exportar(anio: number): Observable<Blob> {
    return this.http.get(`${this.url}/export`, { params: { anio: String(anio) }, responseType: 'blob' });
  }
}
