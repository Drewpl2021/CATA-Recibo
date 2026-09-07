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
}

/** Usuario ya normalizado por AuthService (rol siempre string). */
export interface AuthUser {
  id: number;
  name: string;
  email: string;
  rol: string;
  empleado_id: string | null;
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

export interface SesionData {
  user: AuthUser;
  token: string;
}
