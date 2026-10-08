import { Area, Cargo, Sede, TipoContrato } from './configuracion.model';
import { Usuario } from './usuario.model';

export interface Empleado {
  id: string;
  dni: string;
  nombre: string;
  apellido: string;
  telefono?: string | null;
  direccion?: string | null;
  fecha_ingreso: string;
  /** Cuándo dejó de trabajar, o cuándo termina su contrato actual si sigue activo. */
  fecha_cese?: string | null;
  fecha_nacimiento?: string | null;
  estado: string;

  // Relaciones (el backend las manda con with(...))
  area_id?: string | null;
  cargo_id?: string | null;
  sede_id?: string | null;
  tipo_contrato_id?: string | null;
  area?: Area | null;
  cargo?: Cargo | null;
  sede?: Sede | null;
  tipo_contrato?: TipoContrato | null;
  usuario?: Usuario | null;

  // Datos de planilla
  sueldo_base?: number | null;
  /** Monto fijo al mes (la "Bonificación por Función" del PLAME): cada planilla la trae sola. */
  bonificacion_cargo?: number | string | null;
  /** Desde cuándo está en su AFP u ONP. Opcional. */
  fecha_afiliacion?: string | null;
  forma_pago?: string | null;
  sistema_pensiones?: string;
  afp?: string | null;
  /** 'flujo' (normal desde 2013) o 'mixta' (de antes: la AFP cobra su comisión del fondo, no de la planilla). */
  tipo_comision_afp?: string | null;
  cuspp?: string | null;
  entidad_financiera?: string | null;
  numero_cuenta?: string | null;
  /** Código de Cuenta Interbancario, 20 dígitos. Opcional. */
  cci?: string | null;
  tiene_hijos?: boolean;
  /** El Diezmo (10% del sueldo) se le aplica a todos por defecto; en false queda excluido. */
  aplica_diezmo?: boolean;

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
  bonificacion_cargo?: number | null;
  fecha_afiliacion?: string | null;
  tipo_contrato_id: string | null;
  /**
   * Fin del primer contrato (solo si el tipo lleva plazo) o cese real si se
   * está registrando a alguien que ya se fue: es el mismo dato.
   */
  fecha_cese?: string | null;
  email: string;
  rol_id: string;
  estado?: string;
  /** null = no aporta a ninguna pensión (jubilado, extranjero con convenio). */
  sistema_pensiones?: string | null;
  afp?: string | null;
  tipo_comision_afp?: string | null;
  cuspp?: string | null;
  entidad_financiera?: string | null;
  numero_cuenta?: string | null;
  cci?: string | null;
  tiene_hijos?: boolean;
  aplica_diezmo?: boolean;
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
  tipo_contrato_id: string;
  tipo_contrato?: TipoContrato;
  fecha_inicio: string;
  fecha_fin?: string | null;
  estado: string;
  motivo_fin?: string | null;
  observaciones?: string | null;
  empleado?: Empleado;
}

export interface ContratoPayload {
  empleado_id: string;
  tipo_contrato_id: string;
  fecha_inicio: string;
  fecha_fin?: string | null;
  observaciones?: string | null;
}

/**
 * Lo que devuelve buscar un DNI antes de dar de alta a alguien.
 *
 * Tres desenlaces posibles, y por eso `encontrado` no basta:
 *   - Está en el padrón      → encontrado, con sus nombres.
 *   - Ya trabaja en el colegio → no encontrado, pero con `yaEsEmpleado`.
 *   - No está en ningún lado  → no encontrado, y se escribe a mano.
 */
export interface PersonaPorDni {
  encontrado: boolean;
  dni?: string;
  nombres?: string;
  apellidos?: string;
  apellido_paterno?: string;
  apellido_materno?: string;
  nombre_completo?: string;
  fecha_nacimiento?: string | null;
  direccion?: string | null;
  /** De dónde salieron los datos: "RENIEC" o "Base del colegio". */
  fuente?: string;
  /** Si ese DNI ya tiene ficha en el colegio. */
  yaEsEmpleado?: { id: string; nombre: string; estado: string };
  mensaje?: string;
}

/**
 * Lo que devuelve GET /my-profile, para la tarjeta de "Mi perfil".
 *
 * Vienen dos cosas porque no toda cuenta es de un trabajador: las de
 * Administración y RR.HH. nacen sin ficha (son para operar el sistema, no
 * personas en planilla), y aun así su perfil tiene qué enseñar.
 */
export interface MiPerfil {
  /** Su ficha de trabajador, o null si la cuenta no está vinculada a una. */
  empleado: Empleado | null;
  cuenta: {
    nombre: string;
    correo: string;
    rol: string | null;
    estado: string | null;
    creada_en: string | null;
    terminos_estado: string | null;
    terminos_en: string | null;
  };
}
