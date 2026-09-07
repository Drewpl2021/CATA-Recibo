import { Opcion } from './models';

/** Empleado.tipo_contrato — EmpleadoController@store/update (backend) */
export const TIPO_CONTRATO_OPCIONES: readonly Opcion[] = [
  { label: 'Indeterminado', value: 'indeterminado' },
  { label: 'Plazo fijo', value: 'plazo_fijo' },
  { label: 'Suplencia', value: 'suplencia' },
  { label: 'Prácticas', value: 'practicas' },
];

/** Empleado.forma_pago */
export const FORMA_PAGO_OPCIONES: readonly Opcion[] = [
  { label: 'Depósito en banco', value: 'banco' },
  { label: 'Efectivo', value: 'efectivo' },
  { label: 'Otro', value: 'otro' },
];

/** Empleado.sistema_pensiones */
export const SISTEMA_PENSIONES_OPCIONES: readonly Opcion[] = [
  { label: 'ONP', value: 'ONP' },
  { label: 'AFP', value: 'AFP' },
];

/** Empleado.afp — solo aplica si sistema_pensiones = AFP */
export const AFP_ENTIDAD_OPCIONES: readonly Opcion[] = [
  { label: 'Hábitat', value: 'Habitat' },
  { label: 'Integra', value: 'Integra' },
  { label: 'Prima', value: 'Prima' },
  { label: 'Profuturo', value: 'Profuturo' },
];

/** Empleado.nivel_estudios */
export const NIVEL_ESTUDIOS_OPCIONES: readonly Opcion[] = [
  { label: 'Primaria', value: 'primaria' },
  { label: 'Secundaria', value: 'secundaria' },
  { label: 'Técnico', value: 'tecnico' },
  { label: 'Universitario', value: 'universitario' },
  { label: 'Maestría', value: 'maestria' },
  { label: 'Doctorado', value: 'doctorado' },
];

/** Empleado.estado */
export const ESTADO_EMPLEADO_OPCIONES: readonly Opcion[] = [
  { label: 'Activo', value: 'activo' },
  { label: 'Inactivo', value: 'inactivo' },
];
