import { Rol } from './configuracion.model';

/** Fila de la tabla users (UserController). */
export interface Usuario {
  id: number;
  name: string;
  email: string;
  rol_id?: string;
  empleado_id?: string | null;
  estado_registro?: string;
  es_institucional?: boolean;
  rol?: Rol | string;
  /** La firma de los términos: 'pendiente' | 'firmado' | 'desactualizado'. */
  terminos_estado?: EstadoTerminos;
  terminos_firmados_en?: string | null;
  terminos_version?: string | null;
}

/**
 * En qué anda alguien con los términos de uso.
 *
 * 'desactualizado' es el caso que el papel no tenía: firmó, pero una versión
 * anterior a la que rige hoy, así que tiene que volver a firmar.
 */
export type EstadoTerminos = 'pendiente' | 'firmado' | 'desactualizado';

/** El documento que se lee antes de entrar por primera vez. */
export interface TerminosDeUso {
  version: string;
  titulo: string;
  resumen: string;
  secciones: { titulo: string; texto: string }[];
  firmados: boolean;
  firmadosEn: string | null;
  versionFirmada: string | null;
  /** Firmó una versión anterior: hay que pedirle la firma otra vez. */
  esVersionNueva: boolean;
}

/** Usuario ya normalizado por AuthService (rol siempre string). */
export interface AuthUser {
  id: number;
  name: string;
  email: string;
  rol: string;
  empleado_id: string | null;
  /** Sigue con la contraseña que le dieron y tiene que cambiarla. */
  debe_cambiar_password?: boolean;
}

export interface LoginPayload {
  email: string;
  password: string;
}

/**
 * Cuerpo de POST /register. Crea la cuenta Y la ficha de empleado, por eso
 * pide el DNI: el backend ya no lo inventa.
 */
export interface RegisterPayload {
  nombre: string;
  apellido: string;
  dni: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface CambiarPasswordPayload {
  password_actual: string;
  password_nuevo: string;
  password_nuevo_confirmation: string;
}

/** Cuerpo de POST /restablecer-password — el token viene del correo. */
export interface RestablecerPasswordPayload {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface SesionData {
  user: AuthUser;
  token: string;
  /**
   * La cuenta sigue con la contraseña que le dieron (su DNI). Mientras esté
   * en true el backend responde 423 a todo lo demás, así que la app manda
   * derecho a cambiarla.
   */
  debe_cambiar_password?: boolean;
}
