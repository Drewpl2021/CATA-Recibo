import { Opcion } from './models';

/**
 * Contrato.tipo_contrato_id ya NO es una lista fija acá: es el catálogo
 * administrable de Tipos de Contrato (TipoContratoService). Ver
 * tipos-contrato-list para su CRUD.
 */

/** Contrato.estado */
export const ESTADO_CONTRATO_OPCIONES: readonly Opcion[] = [
  { label: 'Vigente', value: 'vigente' },
  { label: 'Finalizado', value: 'finalizado' },
  { label: 'Renovado', value: 'renovado' },
];

/** Contrato.motivo_fin — solo aplica si estado = finalizado */
export const MOTIVO_FIN_CONTRATO_OPCIONES: readonly Opcion[] = [
  { label: 'Renuncia', value: 'renuncia' },
  { label: 'Despido', value: 'despido' },
  { label: 'Fin de contrato a plazo', value: 'fin_contrato_plazo' },
  { label: 'Fin de año escolar', value: 'fin_año_escolar' },
  { label: 'No renovación', value: 'no_renovacion' },
  { label: 'Jubilación', value: 'jubilacion' },
  { label: 'Otro', value: 'otro' },
];
