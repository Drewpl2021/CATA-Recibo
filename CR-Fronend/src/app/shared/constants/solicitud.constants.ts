/**
 * Las solicitudes a RR.HH.: cómo se nombra cada tipo y cada estado, y qué
 * se le explica al trabajador antes de pedir. Lo que de verdad pasa en la
 * planilla y la CTS lo decide el servidor (EfectosDeSolicitud); aquí solo
 * se cuenta.
 */
export const TIPOS_SOLICITUD: { valor: 'permiso' | 'licencia' | 'descanso_medico' | 'justificar_falta' | 'cambio_cuenta'; titulo: string; ayuda: string }[] = [
  { valor: 'permiso', titulo: 'Permiso por horas', ayuda: 'Salir unas horas: una cita, un trámite. No toca tu CTS.' },
  { valor: 'licencia', titulo: 'Licencia', ayuda: 'Uno o más días. Con goce se paga; sin goce no se paga ni cuenta para tu CTS.' },
  { valor: 'descanso_medico', titulo: 'Descanso médico', ayuda: 'Con tu certificado médico. Se paga y cuenta para tu CTS hasta 60 días al año.' },
  { valor: 'justificar_falta', titulo: 'Justificar una falta', ayuda: 'Un día que ya faltaste, con su sustento. Justificada, no te resta CTS.' },
  { valor: 'cambio_cuenta', titulo: 'Cambiar mi cuenta', ayuda: 'La de tu sueldo o la de tu CTS, con la constancia del banco.' },
];

export const ESTADO_SOLICITUD_TEXTO: Record<string, string> = {
  pendiente: 'Pendiente',
  aprobada: 'Aprobada',
  rechazada: 'Rechazada',
  cancelada: 'Cancelada',
  anulada: 'Anulada',
};

export const ESTADO_SOLICITUD_SEVERIDAD: Record<string, 'success' | 'warning' | 'danger' | 'secondary'> = {
  pendiente: 'warning',
  aprobada: 'success',
  rechazada: 'danger',
  cancelada: 'secondary',
  anulada: 'secondary',
};

/** «12/11/2026 de 09:00 a 11:00», «del 02/11/2026 al 06/11/2026 (5 días)», «—». */
export function cuandoSolicitud(s: { tipo: string; fecha_inicio: string | null; fecha_fin: string | null; hora_inicio: string | null; hora_fin: string | null; dias: number | null }): string {
  if (!s.fecha_inicio) return '—';
  const f = (iso: string) => iso.split('-').reverse().join('/');
  if (s.tipo === 'permiso') return `${f(s.fecha_inicio)} de ${s.hora_inicio} a ${s.hora_fin}`;
  if (!s.fecha_fin || s.fecha_fin === s.fecha_inicio) return f(s.fecha_inicio);
  return `Del ${f(s.fecha_inicio)} al ${f(s.fecha_fin)} (${s.dias} días)`;
}
