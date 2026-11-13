import { FotoDeCuenta } from '../../../shared/components/avatar/avatar.component';
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { ApiResponse, bajarPorEnlaceFirmado } from '../../utils';
import { FirmaColegio, ResultadoFirmarAqui, ResumenDeFirma } from './boleta.service';

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
  /** La cuenta CTS de su ficha (no la del sueldo), para proponerla al depositar. */
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
  /** Días de falta injustificada con que se calculó: ya restados de meses y días. */
  faltas: number;
  /** Días de licencia sin goce y de descanso médico pasado de 60 en el año: salen de Solicitudes. */
  dias_licencia?: number;
  monto: number;
  estado_cts: EstadoCts;
  /** Calculada, sin depositar, y ya no coincide con lo que daría hoy. */
  desactualizada: boolean;
  fecha_deposito: string | null;
  entidad_financiera: string | null;
  numero_cuenta: string | null;
  documento_id: string | null;
  /** Cómo va la firma digital del colegio en su hoja. */
  firma_colegio: FirmaColegio;
  /** Las faltas anotadas ahora; si difieren de `faltas`, falta volver a calcular. */
  faltas_registradas: number;
  faltas_observacion: string | null;
}

export interface ListaCts {
  anio: number;
  /** Los años del filtro: los que tienen datos, más el actual. */
  anios: number[];
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
  /** La firma digital del colegio en las hojas de este depósito. */
  firma: ResumenDeFirma;
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

export interface FaltasCts {
  empleado_id: string;
  anio: number;
  periodo: PeriodoCts;
  dias: number;
  observacion?: string | null;
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

  /** «Firmar aquí»: una tanda de las hojas del depósito que esperan la firma de quien pide. La clave no se guarda. */
  firmarAqui(anio: number, periodo: PeriodoCts, clave: string, desde: string | null = null, limite = 25): Observable<ApiResponse<ResultadoFirmarAqui>> {
    return this.http.post<ApiResponse<ResultadoFirmarAqui>>(`${this.url}/sign-here`, { anio, periodo, clave, desde, limite });
  }

  anularDeposito(liquidacionId: string): Observable<ApiResponse<{ message: string }>> {
    return this.http.delete<ApiResponse<{ message: string }>>(`${this.url}/${liquidacionId}/deposit`);
  }

  /** Anota sus días no laborados del semestre (0 los borra). Si ya estaba calculada, se recalcula al momento. */
  registrarFaltas(datos: FaltasCts): Observable<ApiResponse<{ faltas: number; monto: number; meses: number; dias: number }>> {
    return this.http.put<ApiResponse<{ faltas: number; monto: number; meses: number; dias: number }>>(`${this.url}/absences`, datos);
  }

  /** Los días no laborados de varios a la vez. Las depositadas se saltan y vuelven en `omitidas`. */
  registrarFaltasEnLote(
    anio: number,
    periodo: PeriodoCts,
    faltas: { empleado_id: string; dias: number; observacion: string | null }[],
  ): Observable<ApiResponse<{ cambiadas: number; omitidas: string[]; total: number }>> {
    return this.http.put<ApiResponse<{ cambiadas: number; omitidas: string[]; total: number }>>(`${this.url}/absences/batch`, { anio, periodo, faltas });
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
  /** La lista en Excel, con los mismos filtros que la pantalla (situacion, sede_id, area_id, cargo_id, search). */
  exportar(anio: number, periodo: PeriodoCts, filtros: Record<string, string> = {}): Observable<Blob> {
    return this.http.get(`${this.url}/export`, { params: { anio: String(anio), periodo, ...filtros }, responseType: 'blob' });
  }

  /** La relación de depósitos del semestre (a la cuenta CTS de cada uno): PDF para firmar o Excel. */
  relacionDeDepositos(anio: number, periodo: PeriodoCts, formato: 'pdf' | 'xlsx'): Observable<Blob> {
    return this.http.get(`${this.url}/deposit-report`, { params: { anio: String(anio), periodo, formato }, responseType: 'blob' });
  }

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
