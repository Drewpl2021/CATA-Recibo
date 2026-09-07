import { Opcion } from './models';

/** PaymentConcept.tipo — PaymentConceptController (backend) */
export const TIPO_CONCEPTO_OPCIONES: readonly Opcion[] = [
  { label: 'Bonificación (ingreso)', value: 'bonificacion' },
  { label: 'Descuento', value: 'descuento' },
  { label: 'Aportación del empleador', value: 'aportacion' },
  { label: 'Adelanto', value: 'adelanto' },
];

/** PaymentConcept.calculo */
export const TIPO_CALCULO_OPCIONES: readonly Opcion[] = [
  { label: 'Monto fijo (S/)', value: 'fijo' },
  { label: 'Porcentaje (%)', value: 'porcentaje' },
];

/**
 * Nombre corto de cada tipo, para las etiquetas de la tabla.
 * En el formulario se usan los textos largos de TIPO_CONCEPTO_OPCIONES,
 * que explican qué hace cada uno; en una celda estrecha estorban.
 */
export const TIPO_CONCEPTO_CORTO: Record<string, string> = {
  bonificacion: 'Bonificación',
  descuento: 'Descuento',
  aportacion: 'Aportación',
  adelanto: 'Adelanto',
};

/**
 * Plural de cada tipo, para frases como "Solo aportaciones".
 * Existe porque en castellano el plural no es sumarle una "s":
 * "aportación" → "aportaciones", no "aportacións".
 */
export const TIPO_CONCEPTO_PLURAL: Record<string, string> = {
  bonificacion: 'bonificaciones',
  descuento: 'descuentos',
  aportacion: 'aportaciones',
  adelanto: 'adelantos',
};

/** Ícono con el que se reconoce cada tipo antes de leer su nombre. */
export const TIPO_CONCEPTO_ICONO: Record<string, string> = {
  bonificacion: 'trending_up',
  descuento: 'trending_down',
  aportacion: 'shield',
  adelanto: 'clock',
};
