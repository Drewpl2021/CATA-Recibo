import { Rol } from './configuracion.model';

/** Fila de la tabla users (UserController). */
export interface Usuario {
  id: number;
  name: string;
  email: string;
  rol_id?: string;
  empleado_id?: string | null;
  estado_registro?: string;
  /** Ruta de su foto en el disco privado. La imagen se pide aparte. */
  foto?: string | null;
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
  /**
   * Ruta de su foto en el disco privado, o null si no tiene.
   *
   * Es solo la señal de que existe: la imagen no se puede pedir con un
   * `<img src>` porque esa petición no lleva el token, así que la cabecera la
   * trae por HTTP y la pinta desde memoria.
   */
  foto?: string | null;
  /** Sigue con la contraseña que le dieron y tiene que cambiarla. */
  debe_cambiar_password?: boolean;
  /** Si firmó los términos de uso: sin 'firmado' el backend responde 428 a todo. */
  terminos_estado?: EstadoTerminos;
}

export interface LoginPayload {
  email: string;
  password: string;
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

/** Un paso de la guía de primeros pasos. */
export interface PasoGuia {
  clave: string;
  titulo: string;
  detalle: string;
  /** Clave del catálogo de íconos. */
  icono: string;
  /** A dónde lleva el botón del paso. */
  ruta: string;
  hecho: boolean;
}

/**
 * La guía de qué hacer al entrar, según el rol.
 *
 * `vista` dice si ya se le abrió sola alguna vez; lo demás se calcula en el
 * servidor con los datos de verdad, así que no puede quedar desfasado.
 */
export interface PrimerosPasos {
  vista: boolean;
  rol: string | null;
  pasos: PasoGuia[];
  hechos: number;
  total: number;
}
