import { Empleado } from './empleado.model';
import { Planilla } from './planilla.model';

export type EstadoFirma = 'pendiente' | 'visto' | 'firmado';

export interface Documento {
  id: string;
  empleado_id: string;
  planilla_id?: string | null;
  contrato_id?: string | null;
  tipo: string;
  archivo: string;
  estado_registro?: string;

  // Firma del trabajador
  estado_firma: EstadoFirma;
  firmado_por: string | null;
  codigo_firma: string | null;
  fecha_firma: string | null;
  fecha_visto: string | null;

  // Firma del empleador (RRHH) — ver DocumentoController@firmarComoEmpleador
  empleador_id?: string | null;
  estado_firma_empleador?: 'pendiente' | 'firmado';
  firmado_por_empleador?: string | null;
  codigo_firma_empleador?: string | null;
  fecha_firma_empleador?: string | null;

  created_at?: string;
  planilla?: Pick<Planilla, 'mes' | 'anio'> | null;
  empleado?: Pick<Empleado, 'id' | 'nombre' | 'apellido' | 'dni'> | null;
}

export interface DocumentoPayload {
  empleado_id: string;
  tipo: string;
  archivo: string;
  contrato_id?: string | null;
  firmado_por?: string | null;
}

export interface Vacacion {
  id: string;
  empleado_id: string;
  fecha_inicio: string;
  fecha_fin: string;
  /** Lo calcula el backend a partir de las fechas; nunca se manda. */
  dias_solicitados: number;
  estado: 'pendiente' | 'aprobado' | 'rechazado';
  motivo?: string | null;
  /** Por qué RR.HH. resolvió así — sobre todo cuando rechaza. */
  observacion?: string | null;
  aprobado_por?: string | null;
  aprobado_at?: string | null;
  estado_registro?: string;
  empleado?: Pick<Empleado, 'id' | 'nombre' | 'apellido' | 'dni'>;
}

/**
 * Lo que se manda al pedir vacaciones.
 *
 * Ni los días ni el estado van acá a propósito: los días salen de las fechas
 * y el estado lo pone el sistema. Mandarlos no serviría de nada — el backend
 * los ignora.
 */
export interface VacacionPayload {
  /** Solo lo usa RR.HH. al registrar la solicitud de otro; el trabajador lo omite. */
  empleado_id?: string;
  fecha_inicio: string;
  fecha_fin: string;
  motivo?: string | null;
}

/** Cuántos días le tocan a alguien, cuántos gastó y cuántos le quedan. */
export interface SaldoVacaciones {
  anio: number;
  mesesTrabajados: number;
  diasGanados: number;
  diasUsados: number;
  diasDisponibles: number;
}
