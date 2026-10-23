import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { ApiResponse, bajarPorEnlaceFirmado } from '../../utils';

/** Julio paga el semestre enero–junio; diciembre, julio–diciembre. */
export type PeriodoGratificacion = 'julio' | 'diciembre';

/**
 * Cómo está la gratificación de alguien (ver MotorGratificacion en el servidor):
 *   pagada           su planilla tiene lo que corresponde por ley
 *   diferencia       su planilla pagó otro monto (o algo que no le tocaba)
 *   por_pagar        todavía no hay planilla de ese mes
 *   no_corresponde   prácticas, jornada parcial, ingresó después…
 */
export type EstadoGratificacion = 'pagada' | 'diferencia' | 'por_pagar' | 'no_corresponde';

export interface FilaGratificacion {
  id: string;
  dni: string;
  nombre: string;
  apellido: string;
  nombres: string;
  cargo_id: string | null;
  cargo: string | null;
  area_id: string | null;
  area: string | null;
  sede_id: string | null;
  sede: string | null;
  estado: string;
  fecha_ingreso: string;
  fecha_cese: string | null;
  aplica: boolean;
  motivo: string | null;
  /** Cesó a mitad del semestre: se le paga la trunca en el mes de su cese. */
  trunca: boolean;
  meses: number;
  /** El mes de la planilla donde se paga: julio, diciembre o el del cese. */
  mes_planilla: number;
  anio_planilla: number;
  planilla_id: string | null;
  planilla_del_plame: boolean;
  sueldo: number;
  bonificacion_cargo: number;
  asignacion_familiar: number;
  remuneracion_fuente: 'planilla' | 'ficha' | null;
  remuneracion_computable: number;
  gratificacion: number;
  bonificacion_extraordinaria: number;
  total: number;
  pagado_gratificacion: number;
  pagado_bonificacion: number;
  pagado_total: number;
  /** Pagado − corresponde: negativo es que se le pagó de menos. */
  diferencia: number;
  estado_grati: EstadoGratificacion;
  documento_id: string | null;
}

export interface ListaGratificacion {
  anio: number;
  periodo: PeriodoGratificacion;
  desde: string;
  hasta: string;
  /** 15 de julio o 15 de diciembre. */
  limite: string;
  filas: FilaGratificacion[];
  resumen: {
    trabajadores: number;
    con_derecho: number;
    pagadas: number;
    por_pagar: number;
    diferencias: number;
    truncas: number;
    gratificacion: number;
    bonificacion: number;
    total: number;
    pagado: number;
    entregadas: number;
  };
}

/** Un mes del semestre de alguien, de su planilla. */
export interface MesGratificacion {
  mes: number;
  anio: number;
  situacion: 'planilla' | 'sin_planilla' | 'no_trabajaba';
  sueldo_base: number;
  conceptos: { nombre: string; tipo: string; monto: number }[];
  ingresos: number;
  descuentos: number;
  aportes: number;
  neto: number;
  /** En este mes se paga la gratificación. */
  es_pago: boolean;
}

export interface HistorialGratificacion {
  anio: number;
  periodo: PeriodoGratificacion;
  empleado: { id: string; dni: string; nombre: string };
  meses: MesGratificacion[];
}

@Injectable({ providedIn: 'root' })
export class GratificacionService {
  private http = inject(HttpClient);
  private readonly url = `${environment.apiUrl}/bonuses`;

  /** Sin año ni periodo, el servidor elige la próxima gratificación. */
  lista(anio?: number | null, periodo?: PeriodoGratificacion | null): Observable<ApiResponse<ListaGratificacion>> {
    const params: Record<string, string> = {};
    if (anio) params['anio'] = String(anio);
    if (periodo) params['periodo'] = periodo;
    return this.http.get<ApiResponse<ListaGratificacion>>(this.url, { params });
  }

  historial(empleadoId: string, anio: number, periodo: PeriodoGratificacion): Observable<ApiResponse<HistorialGratificacion>> {
    return this.http.get<ApiResponse<HistorialGratificacion>>(`${this.url}/${empleadoId}/history`, { params: { anio: String(anio), periodo } });
  }

  hoja(empleadoId: string, anio: number, periodo: PeriodoGratificacion): Observable<Blob> {
    return this.http.get(`${this.url}/${empleadoId}/sheet`, { params: { anio: String(anio), periodo }, responseType: 'blob' });
  }

  /** Deja la hoja en el expediente de cada uno (solo de quien ya la tiene pagada). */
  entregar(anio: number, periodo: PeriodoGratificacion, ids: string[]): Observable<ApiResponse<{ entregadas: number; saltadas: string[]; message: string }>> {
    return this.http.post<ApiResponse<{ entregadas: number; saltadas: string[]; message: string }>>(`${this.url}/deliver`, { anio, periodo, ids });
  }

  /**
   * Las hojas del periodo en un .zip: de los marcados o, sin marcados, de
   * todos los que tienen hoja. Lo baja el navegador con un enlace firmado.
   * Devuelve cuántas hojas trae.
   */
  bajarHojasEnZip(anio: number, periodo: PeriodoGratificacion, ids?: string[]): Observable<number> {
    return this.http
      .post<ApiResponse<{ url: string; cantidad: number }>>(`${this.url}/zip/link`, { anio, periodo, ...(ids?.length ? { ids } : {}) })
      .pipe(
        map((res) => {
          bajarPorEnlaceFirmado(res.data.url);
          return res.data.cantidad;
        })
      );
  }
}
