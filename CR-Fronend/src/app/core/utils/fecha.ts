/**
 * Fechas legibles, iguales en cualquier computadora: siempre dd/mm/aaaa.
 *
 * Dos problemas que se corrigen acá:
 *
 * 1. El desfase de zona horaria. El backend manda fechas puras
 *    ("2026-03-01"), y `new Date('2026-03-01')` las interpreta como
 *    medianoche UTC: en Perú (UTC-5) eso cae el 28 de febrero a las 19:00, y
 *    la pantalla mostraba el día anterior. Cuando el valor es una fecha sin
 *    hora se construye en horario local.
 *
 * 2. El formato dependía del navegador. Antes se usaba
 *    `toLocaleDateString('es-PE')`, que arma la fecha con la tabla de idiomas
 *    de CADA navegador: la misma fecha salía "1/12/2026" en una máquina y con
 *    otro formato en otra, según el idioma y la región de quien la abría.
 *    Ahora se arma a mano, con ceros a la izquierda, y sale igual en todas.
 *
 * Vive acá porque lo necesitan la tabla, la ficha del empleado y los
 * documentos; tenerlo copiado era garantía de que una copia se quedara atrás.
 */

const dosCifras = (n: number) => String(n).padStart(2, '0');

/** Una fecha ya construida → "01/12/2026". */
export function formatoDia(fecha: Date): string {
  return `${dosCifras(fecha.getDate())}/${dosCifras(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;
}

/** Una fecha ya construida → "01/12/2026 14:05". */
export function formatoDiaHora(fecha: Date): string {
  return `${formatoDia(fecha)} ${dosCifras(fecha.getHours())}:${dosCifras(fecha.getMinutes())}`;
}

/** Lo que manda el backend ("2026-12-01" o con hora) → "01/12/2026". */
export function fechaLegible(valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '';

  const texto = String(valor);

  if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) {
    const [anio, mes, dia] = texto.split('-').map(Number);
    return formatoDia(new Date(anio, mes - 1, dia));
  }

  const fecha = new Date(texto);
  return isNaN(fecha.getTime()) ? texto : formatoDia(fecha);
}

/** Lo que manda el backend con hora → "01/12/2026 14:05". */
export function fechaHoraLegible(valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '';

  const fecha = new Date(String(valor));
  return isNaN(fecha.getTime()) ? String(valor) : formatoDiaHora(fecha);
}

/**
 * Cuánto tiempo pasó entre dos fechas de calendario, en palabras:
 * "3 años y 4 meses", "8 meses", "Entra hoy". `hasta` es hoy si no se
 * manda —para un trabajador activo—, o su fecha de cese si ya no lo está:
 * la antigüedad de alguien que se fue no debería seguir creciendo cada
 * día después de que se fue.
 *
 * Vive junto a fechaLegible porque lo necesita el mismo sitio: el
 * expediente, para decir cuánto lleva alguien en el colegio sin que RR.HH.
 * tenga que restar la fecha de ingreso a mano.
 */
export function antiguedad(desde: string | null | undefined, hasta?: string | null): string {
  if (!desde) return '';

  const [anioD, mesD, diaD] = String(desde).slice(0, 10).split('-').map(Number);
  const fin = hasta ? String(hasta).slice(0, 10).split('-').map(Number) : null;
  const [anioH, mesH, diaH] = fin ?? (() => {
    const hoy = new Date();
    return [hoy.getFullYear(), hoy.getMonth() + 1, hoy.getDate()];
  })();

  let anios = anioH - anioD;
  let meses = mesH - mesD;
  if (diaH < diaD) meses -= 1;
  if (meses < 0) { anios -= 1; meses += 12; }

  if (anios < 0 || (anios === 0 && meses === 0 && diaH <= diaD)) return 'Entra hoy';
  if (anios === 0) return meses === 1 ? '1 mes' : `${meses} meses`;
  if (meses === 0) return anios === 1 ? '1 año' : `${anios} años`;

  const partesAnios = anios === 1 ? '1 año' : `${anios} años`;
  const partesMeses = meses === 1 ? '1 mes' : `${meses} meses`;
  return `${partesAnios} y ${partesMeses}`;
}
