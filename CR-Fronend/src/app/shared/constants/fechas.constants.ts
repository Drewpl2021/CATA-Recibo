import { Opcion } from './models';

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
