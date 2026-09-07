import { PaymentConcept } from './configuracion.model';
import { Empleado } from './empleado.model';

export interface Planilla {
  /** Opcional porque los formularios arman el objeto antes de guardarlo. */
  id?: string;
  empleado_id: string;
  periodo_id?: string | null;
  mes: number;
  anio: number;
  /** Llega como string decimal desde Laravel; se castea al mostrar. */
  sueldo_base?: number | string;
  bonificaciones?: number | string;
  descuentos?: number | string;
  total?: number | string;
  estado_registro?: string;
  empleado?: Empleado | null;
  payroll_detalles?: PayrollDetalle[];
}

export interface PlanillaPayload {
  empleado_id: string;
  mes: number;
  anio: number;
  periodo_id?: string | null;
  bonificaciones?: number;
  descuentos?: number;
}

/**
 * Detalle de planilla. Ojo con los nombres: el backend usa
 * payment_concept_id y monto_calculado (no concepto_id/monto, como tenía
 * mal el frontend anterior).
 */
export interface PayrollDetalle {
  id: string;
  planilla_id: string;
  payment_concept_id: string;
  monto_calculado: number | string;
  descripcion?: string | null;
  estado?: string;
  payment_concept?: PaymentConcept;
  planilla?: Planilla;
}

export interface PayrollDetallePayload {
  planilla_id: string;
  payment_concept_id: string;
  monto_calculado: number;
  descripcion?: string | null;
}

/** Respuesta de POST /periodos/{id}/generar-planilla */
export interface GeneracionMasivaPlanilla {
  periodo: string;
  mes: number;
  anio: number;
  /** `evaluados` es a cuántos se apuntó; generadas + omitidas suman eso. */
  resumen: { generadas: number; omitidas: number; evaluados?: number };
  detalle: Array<{
    empleado: string;
    empleado_id: string;
    estado: string;
    planilla_id?: string;
    motivo?: string;
  }>;
}

/** Respuesta de POST /boletas/generar-masivo */
export interface GeneracionMasivaBoletas {
  success: boolean;
  message: string;
  generadas: number;
  omitidas: number;
}

/** Respuesta de POST /payment-concepts/{id}/aplicar-a-grupo */
export interface AplicacionConceptoGrupo {
  concepto: string;
  mes: number;
  anio: number;
  resumen: { aplicadas: number; omitidas: number };
  detalle: Array<{
    empleado: string;
    estado: string;
    monto?: number;
    motivo?: string;
  }>;
}
