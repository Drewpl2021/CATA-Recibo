/** Catálogos de Configuración Base (áreas, cargos, sedes, periodos, conceptos). */

/**
 * Los tres catálogos base llevan `estado` ('activo' | 'inactivo'): así se
 * da de baja un área, cargo o sede sin borrarla y sin dejar huérfanos a
 * los empleados que ya la tienen asignada.
 */
export type EstadoCatalogo = 'activo' | 'inactivo';

export interface Area {
  id: string;
  nombre: string;
  descripcion?: string | null;
  estado?: EstadoCatalogo;
}

export interface AreaPayload {
  nombre: string;
  descripcion?: string | null;
  estado?: EstadoCatalogo;
}

export interface Cargo {
  id: string;
  nombre: string;
  descripcion?: string | null;
  estado?: EstadoCatalogo;
}

export interface CargoPayload {
  nombre: string;
  descripcion?: string | null;
  estado?: EstadoCatalogo;
}

export interface Sede {
  id: string;
  nombre: string;
  direccion?: string | null;
  telefono?: string | null;
  estado?: EstadoCatalogo;
}

export interface SedePayload {
  nombre: string;
  direccion?: string | null;
  telefono?: string | null;
  estado?: EstadoCatalogo;
}

export interface Periodo {
  id: string;
  nombre: string;
  fecha_inicio: string;
  fecha_fin: string;
  estado?: string;
}

export interface PeriodoPayload {
  nombre: string;
  fecha_inicio: string;
  fecha_fin: string;
}

export interface Rol {
  id: string;
  nombre: string;
  descripcion?: string | null;
}

/**
 * PaymentConcept.tipo — valores reales del backend (PaymentConceptSeeder).
 * Ojo: antes el frontend tenía 'ingreso', que no existe en el backend, y le
 * faltaba 'adelanto'.
 */
export type TipoConcepto = 'bonificacion' | 'descuento' | 'aportacion' | 'adelanto';
export type TipoCalculo = 'fijo' | 'porcentaje';

export interface PaymentConcept {
  id: string;
  nombre: string;
  tipo: TipoConcepto;
  calculo?: TipoCalculo | null;
  valor?: number | null;
  descripcion?: string | null;
  aplica_a_todos?: boolean;
}

export interface PaymentConceptPayload {
  nombre: string;
  tipo: TipoConcepto;
  calculo?: TipoCalculo | null;
  valor?: number | null;
  descripcion?: string | null;
  aplica_a_todos?: boolean;
}
