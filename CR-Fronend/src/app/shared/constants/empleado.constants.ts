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
  // Quien emite recibo por honorarios no está en planilla: no aporta a
  // ninguna pensión. Al elegirlo, la pantalla deja el sistema de pensiones
  // en "ninguno" para que no se le descuente algo que no le toca.
  { label: 'Recibo por honorarios', value: 'honorarios' },
  { label: 'Otro', value: 'otro' },
];

/**
 * Bancos para el campo "Banco" (Empleado.entidad_financiera).
 *
 * Es una ayuda para escribir, no una lista cerrada: el campo sigue siendo
 * texto libre y admite cualquier entidad que no esté acá — cajas
 * municipales, cooperativas o un banco nuevo — sin tener que tocar código.
 * Por eso va como lista de nombres y no como catálogo en la base.
 */
export const BANCOS_PERU: readonly string[] = [
  'Banco de Crédito del Perú (BCP)',
  'BBVA Perú',
  'Scotiabank Perú',
  'Interbank',
  'Banco Pichincha',
  'BanBif',
  'Banco GNB Perú',
  'Banco Santander Perú',
  'Citibank del Perú',
  'Banco Ripley',
  'Banco Falabella',
  'Banco de Comercio',
  'Banco Alfin',
  'ICBC Perú Bank',
  'Bank of China (Sucursal Perú)',
  'Bci Perú',
  'Caja Cusco',
];

/** Empleado.sistema_pensiones */
export const SISTEMA_PENSIONES_OPCIONES: readonly Opcion[] = [
  { label: 'ONP', value: 'ONP' },
  { label: 'AFP', value: 'AFP' },
  // El jubilado que vuelve a dictar y el extranjero con convenio no aportan
  // a ninguna pensión. Sin esta opción se les guardaba ONP y se les
  // descontaba el 13% que por ley no les toca.
  { label: 'Ninguno — no aporta', value: '' },
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
