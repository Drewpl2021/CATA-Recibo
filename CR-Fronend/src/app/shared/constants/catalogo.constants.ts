import { ColumnaTabla } from '../components/data-table/data-table.models';

/**
 * Piezas compartidas por los catálogos de Configuración Base
 * (Áreas, Cargos, Sedes… y los que vengan).
 *
 * Los tres llevan la misma columna `estado`, así que se define UNA vez acá
 * en lugar de copiarla en cada pantalla: si mañana cambian los valores o el
 * color de la etiqueta, se toca este archivo y los tres quedan iguales.
 */

export interface OpcionEstado {
  label: string;
  value: 'activo' | 'inactivo';
}

/** Las dos opciones del <select> de estado. */
export const ESTADO_CATALOGO_OPCIONES: readonly OpcionEstado[] = [
  { label: 'Activo', value: 'activo' },
  { label: 'Inactivo', value: 'inactivo' },
];

/** Valor con el que nace un registro nuevo. */
export const ESTADO_CATALOGO_POR_DEFECTO = 'activo';

/** "activo" → "Activo". Un registro viejo sin estado se asume activo. */
export function etiquetaEstado(valor: unknown): string {
  return valor === 'inactivo' ? 'Inactivo' : 'Activo';
}

/**
 * La columna "Estado" lista para <app-data-table>.
 *
 *   columnas: ColumnaTabla<Area>[] = [
 *     { campo: 'nombre', header: 'Nombre' },
 *     columnaEstado<Area>(),
 *   ];
 */
export function columnaEstado<T>(ancho = '12%'): ColumnaTabla<T> {
  return {
    campo: 'estado',
    header: 'Estado',
    ancho,
    tipo: 'badge',
    formatear: (valor) => etiquetaEstado(valor),
    badgeSeveridad: (valor) => (valor === 'inactivo' ? 'secondary' : 'success'),
  };
}
