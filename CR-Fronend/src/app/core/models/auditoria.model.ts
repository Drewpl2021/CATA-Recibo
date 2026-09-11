/**
 * Una línea del registro de auditoría: quién hizo qué, y cuándo.
 *
 * `cambios` trae, para lo que se cambió, el valor de antes y el de después
 * ({ sueldo_base: ['2700.00', '3000.00'] }). De la contraseña llega
 * '(oculto)': se sabe QUE cambió, nunca a qué.
 */
export interface RegistroAuditoria {
  id: number;
  user_id: number | null;
  usuario_nombre: string | null;
  accion: string;
  entidad: string;
  entidad_id: string | null;
  descripcion: string;
  cambios: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
}
