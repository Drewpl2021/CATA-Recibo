import { Opcion } from './models';

/** Vacacion.estado — VacacionController (backend) */
export const ESTADO_VACACION_OPCIONES: readonly Opcion[] = [
  { label: 'Pendiente', value: 'pendiente' },
  { label: 'Aprobado', value: 'aprobado' },
  { label: 'Rechazado', value: 'rechazado' },
];

export const ESTADO_VACACION_SEVERIDAD: Record<string, 'success' | 'warning' | 'danger'> = {
  pendiente: 'warning',
  aprobado: 'success',
  rechazado: 'danger',
};
