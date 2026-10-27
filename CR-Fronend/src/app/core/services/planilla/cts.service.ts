import { FotoDeCuenta } from '../../../shared/components/avatar/avatar.component';
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { ApiResponse, bajarPorEnlaceFirmado } from '../../utils';

/** El depósito: mayo cubre noviembre–abril, noviembre cubre mayo–octubre. */
export type PeriodoCts = 'mayo' | 'noviembre';

/** Dónde está la CTS de alguien en el semestre. */
export type EstadoCts = 'por_calcular' | 'calculada' | 'depositada' | 'sin_derecho';

/** Un trabajador en la lista del semestre, con todo el cálculo (ver MotorCts en el servidor). */
export interface FilaCts {
  /** Para app-avatar: su foto o, sin foto, sus iniciales. */
  foto?: FotoDeCuenta | null;
  id: string;
  liquidacion_id: string | null;
  dni: string;
  nombre: string;
  /** Por separado, para ordenar por apellidos o por nombres. */
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
  entidad_ficha: string | null;
  cuenta_ficha: string | null;
  aplica: boolean;
  /** Por qué no tiene CTS, cuando no aplica. */
  motivo: string | null;
  desde: string | null;
  hasta: string | null;
  sueldo: number;
  asignacion_familiar: number;
  bonificacion_cargo: number;
  /** De dónde salió la remuneración: su planilla del mes de cierre, o la ficha si ese mes no pasó por el sistema. */
  remuneracion_fuente: 'planilla' | 'ficha' | null;
  gratificacion: number;
  /** De dónde salió la gratificación: su planilla, o calculada si ese mes no pasó por el sistema. */
  gratificacion_fuente: 'planilla' | 'calculada' | null;
  sexto_gratificacion: number;
  remuneracion_computable: number;
  meses: number;
  dias: number;
  monto: number;
  estado_cts: EstadoCts;
  /** Calculada, sin depositar, y ya no coincide con lo que daría hoy. */
  desactualizada: boolean;
  fecha_deposito: string | null;
  entidad_financiera: string | null;
  numero_cuenta: string | null;
  documento_id: string | null;
}

export interface ListaCts {
  anio: number;
  periodo: PeriodoCts;
  desde: string;
  hasta: string;
  /** Hasta cuándo hay que depositar (15 de mayo o 15 de noviembre). */
  limite: string;
  filas: FilaCts[];
  resumen: {
    trabajadores: number;
    con_derecho: number;
    por_calcular: number;
    calculadas: number;
    depositadas: number;
    desactualizadas: number;
    total: number;
    depositado: number;
  };
}

/** Un mes del semestre en el historial de alguien, de su planilla (ver MotorCts::historial). */
export interface MesHistorialCts {
  mes: number;
  anio: number;
  situacion: 'planilla' | 'sin_planilla' | 'no_trabajaba';
  sueldo_base: number;
  conceptos: { nombre: string; tipo: 'bonificacion' | 'descuento' | 'aportacion' | 'adelanto'; monto: number }[];
  /** Sueldo + bonificaciones del mes. */
  ingresos: number;
  /** Descuentos y adelantos. */
  descuentos: number;
  /** Lo que aporta el colegio (EsSalud): no sale del bolsillo del trabajador. */
  aportes: number;
  neto: number;
  /** De este mes sale la remuneración computable. */
  es_cierre: boolean;
  /** De este mes sale el sexto de la gratificación. */
  es_gratificacion: boolean;
}

export interface HistorialCts {
  anio: number;
  periodo: PeriodoCts;
  empleado: { id: string; dni: string; nombre: string };
  meses: MesHistorialCts[];
}

export interface DepositoCts {
  ids: string[];
  fecha_deposito: string;
  entidad_financiera?: string | null;
  numero_cuenta?: string | null;
}

@Injectable({ providedIn: 'root' })
export class CtsService {
  private http = inject(HttpClient);
  private readonly url = `${environment.apiUrl}/cts`;

  /** Sin año ni periodo, el servidor elige el próximo depósito. */
  lista(anio?: number | null, periodo?: PeriodoCts | null): Observable<ApiResponse<ListaCts>> {
    const params: Record<string, string> = {};
    if (anio) params['anio'] = String(anio);
    if (periodo) params['periodo'] = periodo;
    return this.http.get<ApiResponse<ListaCts>>(this.url, { params });
  }

  calcular(anio: number, periodo: PeriodoCts): Observable<ApiResponse<{ calculadas: number; depositadas: number; quitadas: number; total: number }>> {
    return this.http.post<ApiResponse<{ calculadas: number; depositadas: number; quitadas: number; total: number }>>(`${this.url}/calculate`, { anio, periodo });
  }

  depositar(datos: DepositoCts): Observable<ApiResponse<{ depositadas: number }>> {
    return this.http.post<ApiResponse<{ depositadas: number }>>(`${this.url}/deposit`, datos);
  }

  anularDeposito(liquidacionId: string): Observable<ApiResponse<{ message: string }>> {
    return this.http.delete<ApiResponse<{ message: string }>>(`${this.url}/${liquidacionId}/deposit`);
  }

  /** Lo que ganó mes por mes en el semestre, de sus planillas. */
  historial(empleadoId: string, anio: number, periodo: PeriodoCts): Observable<ApiResponse<HistorialCts>> {
    return this.http.get<ApiResponse<HistorialCts>>(`${this.url}/${empleadoId}/history`, { params: { anio: String(anio), periodo } });
  }

  /** La hoja de liquidación en PDF. */
  hoja(liquidacionId: string): Observable<Blob> {
    return this.http.get(`${this.url}/${liquidacionId}/sheet`, { responseType: 'blob' });
  }

  /**
   * Las hojas del periodo en un .zip: de los marcados o, sin marcados, de
   * todos los que tienen hoja. Lo baja el navegador con un enlace firmado.
   * Devuelve cuántas hojas trae.
   */
  bajarHojasEnZip(anio: number, periodo: PeriodoCts, ids?: string[]): Observable<number> {
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
