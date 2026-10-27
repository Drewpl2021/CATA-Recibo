import { FotoDeCuenta } from '../../shared/components/avatar/avatar.component';
/**
 * Una línea del registro de auditoría: quién hizo qué, y cuándo.
 *
 * `cambios` trae, para lo que se cambió, el valor de antes y el de después
 * ({ sueldo_base: ['2700.00', '3000.00'] }). De la contraseña llega
 * '(oculto)': se sabe QUE cambió, nunca a qué.
 */
export interface RegistroAuditoria {
  /** La foto de quien hizo el cambio (app-avatar). */
  foto?: FotoDeCuenta | null;
  id: number;
  user_id: number | null;
  usuario_nombre: string | null;
  accion: string;
  entidad: string;
  entidad_id: string | null;
  descripcion: string;
  cambios: Record<string, unknown> | null;
  ip: string | null;
  /** El navegador tal como llegó; solo en los registros desde el 21/10/2026. */
  navegador?: string | null;
  created_at: string;
}

/** Una línea de tiempo: otro registro del mismo trabajador, o de la misma persona en esos minutos. */
export interface RegistroAuditoriaBreve {
  id: number;
  created_at: string;
  usuario_nombre: string | null;
  accion: string;
  entidad: string;
  descripcion: string;
}

/** Un campo ya en palabras: «Sueldo base: S/ 2,700.00 → S/ 3,000.00». */
export interface CampoAuditoria {
  campo: string;
  etiqueta: string;
  /** Solo en un cambio; en un alta o una baja es null. */
  antes: string | null;
  despues: string;
  es_par: boolean;
}

/** El «Ver más» de la auditoría (GET /audit-log/{id}, ver DetalleDeAuditoria en el backend). */
export interface DetalleAuditoria {
  registro: RegistroAuditoria;
  quien: {
    nombre: string | null;
    correo: string | null;
    rol: string | null;
    sigue_activo: boolean | null;
    ip: string | null;
    /** «Chrome en Windows». */
    navegador: string | null;
  };
  sobre: {
    entidad: string;
    /** Cómo se llama hoy el registro (el trabajador, la planilla…). */
    nombre: string | null;
    /** Dónde se abre en el sistema. */
    enlace: string | null;
    ya_no_existe: boolean;
  };
  campos: CampoAuditoria[];
  es_alta_o_baja: boolean;
  historial: RegistroAuditoriaBreve[];
  cercanos: RegistroAuditoriaBreve[];
}
