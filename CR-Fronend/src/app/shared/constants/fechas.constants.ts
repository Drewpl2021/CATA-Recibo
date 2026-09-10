import { Opcion } from './models';

/*
 * Los meses del año, en UN solo sitio.
 *
 * El nueve se escribe "Septiembre" en todo el sistema, boleta incluida. Las
 * dos formas son correctas, pero mezclarlas hacía que la pantalla de
 * Planillas y la boleta del mismo mes se llamaran distinto.
 */
export const MESES_OPCIONES: readonly Opcion<number>[] = [
  { label: 'Enero', value: 1 },
  { label: 'Febrero', value: 2 },
  { label: 'Marzo', value: 3 },
  { label: 'Abril', value: 4 },
  { label: 'Mayo', value: 5 },
  { label: 'Junio', value: 6 },
  { label: 'Julio', value: 7 },
  { label: 'Agosto', value: 8 },
  { label: 'Septiembre', value: 9 },
  { label: 'Octubre', value: 10 },
  { label: 'Noviembre', value: 11 },
  { label: 'Diciembre', value: 12 },
];

const MESES_MAP: Record<number, string> = Object.fromEntries(
  MESES_OPCIONES.map(m => [m.value, m.label])
);

export function nombreMes(mes: number | null | undefined): string {
  if (!mes) return '';
  return MESES_MAP[mes] ?? `Mes ${mes}`;
}

/*
 * Los días de la semana y la fecha en palabras.
 *
 * Se arma a mano y no con toLocaleDateString('es-PE'): la tabla de idiomas
 * del navegador escribe "setiembre" para Perú, y el sistema escribe
 * "septiembre" en todas partes. Dejarlo al navegador era tener las dos
 * formas en la misma pantalla.
 */
const DIAS_SEMANA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

/** "Jueves, 10 de septiembre de 2026". */
export function fechaEnPalabras(fecha: Date = new Date()): string {
  const dia = DIAS_SEMANA[fecha.getDay()];
  const mes = nombreMes(fecha.getMonth() + 1).toLowerCase();
  return `${dia}, ${fecha.getDate()} de ${mes} de ${fecha.getFullYear()}`;
}
