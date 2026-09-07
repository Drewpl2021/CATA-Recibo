/**
 * Fechas legibles sin el desfase de zona horaria.
 *
 * El backend manda fechas puras ("2026-03-01"), y `new Date('2026-03-01')`
 * las interpreta como medianoche UTC: en Perú (UTC-5) eso cae el 28 de
 * febrero a las 19:00, y la pantalla mostraba el día anterior. Cuando el
 * valor es una fecha sin hora se construye en horario local para evitarlo.
 *
 * Vive acá porque lo necesitan la tabla y la ficha del empleado; tenerlo
 * dos veces era garantía de que una de las dos copias se quedara atrás.
 */
export function fechaLegible(valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '';

  const texto = String(valor);
  const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(texto);

  if (soloFecha) {
    const [anio, mes, dia] = texto.split('-').map(Number);
    return new Date(anio, mes - 1, dia).toLocaleDateString('es-PE');
  }

  const fecha = new Date(texto);
  return isNaN(fecha.getTime()) ? texto : fecha.toLocaleDateString('es-PE');
}
