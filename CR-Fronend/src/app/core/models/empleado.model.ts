import { Area, Cargo, Sede } from './configuracion.model';
import { Usuario } from './usuario.model';

export interface IdentidadFirma {
  id: string;
  empleado_id?: string;
  firma_imagen: string | null;
  huella_imagen: string | null;
  registrado_por?: number | null;
}

export interface Empleado {
  id: string;
  dni: string;
  nombre: string;
  apellido: string;
  telefono?: string | null;
  direccion?: string | null;
  fecha_ingreso: string;
  fecha_nacimiento?: string | null;
  estado: string;

  // Relaciones (el backend las manda con with(...))
  area_id?: string | null;
  cargo_id?: string | null;
  sede_id?: string | null;
  area?: Area | null;
  cargo?: Cargo | null;
  sede?: Sede | null;
  usuario?: Usuario | null;

  // Datos de planilla
  sueldo_base?: number | null;
  tipo_contrato?: string | null;
  forma_pago?: string | null;
  sistema_pensiones?: string;
  afp?: string | null;
  cuspp?: string | null;
  entidad_financiera?: string | null;
  numero_cuenta?: string | null;
  tiene_hijos?: boolean;

  /** Relación identidades_firma (backend: identidadFirma()) — disco privado. */
  identidad_firma?: IdentidadFirma | null;

  // Datos académicos / de contacto
  nivel_estudios?: string | null;
  especialidad?: string | null;
  institucion_estudios?: string | null;
  contacto_emergencia_nombre?: string | null;
  contacto_emergencia_telefono?: string | null;
}

/**
 * Cuerpo que espera EmpleadoController@store (crear empleado + su usuario).
 * Los campos que el formulario puede mandar vacíos van como `| null`, tal
 * como los envía hoy la pantalla.
 */
export interface EmpleadoPayload {
  dni: string;
  nombre: string;
  apellido: string;
  cargo_id: string;
  area_id: string;
  sede_id: string;
  telefono: string;
  direccion: string;
  fecha_ingreso: string;
  fecha_nacimiento: string | null;
  sueldo_base: number | null;
  tipo_contrato: string | null;
  /** Fin del primer contrato. Solo se manda si el tipo lleva plazo. */
  fecha_fin_contrato?: string | null;
  email: string;
  rol_id: string;
  estado?: string;
  sistema_pensiones?: string;
  afp?: string | null;
  cuspp?: string | null;
  entidad_financiera?: string | null;
  numero_cuenta?: string | null;
  tiene_hijos?: boolean;
  forma_pago?: string | null;
  nivel_estudios?: string | null;
  especialidad?: string | null;
  institucion_estudios?: string | null;
  contacto_emergencia_nombre?: string | null;
  contacto_emergencia_telefono?: string | null;
}

export interface Contrato {
  id: string;
  empleado_id: string;
  tipo_contrato: string;
  fecha_inicio: string;
  fecha_fin?: string | null;
  estado: string;
  motivo_fin?: string | null;
  observaciones?: string | null;
  empleado?: Empleado;
}

export interface ContratoPayload {
  empleado_id: string;
  tipo_contrato: string;
  fecha_inicio: string;
  fecha_fin?: string | null;
  observaciones?: string | null;
}
