import type { TipoDocumentoAnterior } from '../models/documentos-anteriores.model';

/**
 * De quién es un archivo de antes del sistema, qué es y de qué mes, a partir
 * de su texto y de su nombre.
 *
 * La boleta del Excel del colegio, leída con pdf.js, dice:
 *
 *   "Boleta de Pago de Remuneraciones"   → es una boleta
 *   "DNI: 12345678"                      → de quién
 *   "Del 01/03/2026 al 31/03/2026"       → de qué mes
 *   "Mes de Marzo" … "31 de marzo de 2026"
 *
 * Lo de dentro manda; el nombre del archivo ("42558107 2024-03.pdf") rellena
 * lo que no se encontró, que es lo que pasa con un escaneo o un Word.
 *
 * Sin dependencias a propósito: se prueba solo, con PDFs de verdad.
 */

export interface DocumentoIdentificado {
  tipo: TipoDocumentoAnterior | null;
  /** Los DNI escritos dentro del archivo, sin repetir. Un contrato trae dos. */
  dnis: string[];
  /** El DNI del nombre del archivo. */
  dniNombre: string | null;
  mes: number | null;
  anio: number | null;
}

interface Periodo {
  mes: number | null;
  anio: number | null;
}

const MESES: Record<string, number> = {
  enero: 1, febrero: 2, marzo: 3, abril: 4, mayo: 5, junio: 6, julio: 7,
  agosto: 8, septiembre: 9, setiembre: 9, octubre: 10, noviembre: 11, diciembre: 12,
};
const UN_MES = `(${Object.keys(MESES).join('|')})`;
const UN_ANIO = '((?:19|20)\\d{2})';

const SIN_PERIODO: Periodo = { mes: null, anio: null };

export function identificarDocumento(texto: string, nombreArchivo: string): DocumentoIdentificado {
  const t = normalizar(texto);
  const n = normalizar(nombreArchivo.replace(/\.[a-z0-9]+$/i, '').replace(/_/g, ' '));

  const tipo = tipoDelTexto(t) ?? tipoDelNombre(n);
  const delTexto = periodoDelTexto(t, tipo);
  const delNombre = periodoDelNombre(n);

  return {
    tipo,
    dnis: dnisDelTexto(t),
    dniNombre: n.match(/(?:^|\D)(\d{8})(?!\d)/)?.[1] ?? null,
    mes: delTexto.mes ?? delNombre.mes,
    anio: delTexto.anio ?? delNombre.anio,
  };
}

/** Minúsculas, sin tildes y con un solo espacio: "Setiembre" y "SETIEMBRE" son lo mismo. */
function normalizar(texto: string): string {
  return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ');
}

/**
 * Una boleta dice "boleta de pago" y trae totales. Un contrato puede
 * mencionar la boleta ("se le entregará su boleta de pago"), por eso los
 * totales, y la boleta del colegio dice "CONTRATADO", por eso la palabra
 * entera.
 */
function tipoDelTexto(t: string): TipoDocumentoAnterior | null {
  if (/boleta de pago/.test(t) && /(neto a pagar|total ingresos|total descuentos)/.test(t)) return 'boleta_anterior';
  if (/\bcontrato\b/.test(t)) return 'contrato_anterior';
  if (/\bboleta\b/.test(t)) return 'boleta_anterior';
  return null;
}

function tipoDelNombre(n: string): TipoDocumentoAnterior | null {
  if (/boleta|bolpago/.test(n)) return 'boleta_anterior';
  if (/contrato/.test(n)) return 'contrato_anterior';
  return null;
}

/**
 * "DNI: 12345678", "D.N.I. N° 1234567", "Documento Nacional de Identidad Nº 12345678".
 *
 * Entre la palabra y el número se acepta cualquier cosa corta que no sea una
 * cifra: "N°", "Nro.", ":" o el "NÂ°" que deja un archivo mal codificado. Lo
 * que no se acepta es un número pegado a más cifras (un RUC, una cuenta).
 */
function dnisDelTexto(t: string): string[] {
  const patron = /(?:\bd\.? ?n\.? ?i\b\.?|documento nacional de identidad)[^\d]{0,10}?(?<!\d)(\d{7,8})(?!\d)/g;
  const encontrados = [...t.matchAll(patron)].map((m) => m[1].padStart(8, '0'));
  return [...new Set(encontrados)];
}

function periodoDelTexto(t: string, tipo: TipoDocumentoAnterior | null): Periodo {
  // "Del 01/03/2026 al 31/03/2026": en una boleta cuenta el mes pagado (el
  // final del rango); en un contrato, el año en que empezó.
  const rango = t.match(/\bdel \d{1,2}\/(\d{1,2})\/(\d{4}) al \d{1,2}\/(\d{1,2})\/(\d{4})/);
  if (rango) {
    return tipo === 'contrato_anterior'
      ? valido(null, +rango[2])
      : valido(+rango[3], +rango[4]);
  }

  // "31 de marzo de 2026": la fecha de firma o de inicio.
  const fecha = t.match(new RegExp(`\\b\\d{1,2} de ${UN_MES} (?:de |del )?${UN_ANIO}`));

  // "Mes de Marzo", con o sin año.
  const mesDe = t.match(new RegExp(`\\bmes de ${UN_MES}(?: (?:de |del )?${UN_ANIO})?`));
  if (mesDe && tipo !== 'contrato_anterior') {
    const mes = MESES[mesDe[1]];
    if (mesDe[2]) return valido(mes, +mesDe[2]);
    if (!fecha) return valido(mes, null);
    // La boleta de diciembre se firma en enero: es del año anterior.
    const anio = +fecha[2];
    return valido(mes, MESES[fecha[1]] < mes ? anio - 1 : anio);
  }

  if (fecha) {
    return tipo === 'contrato_anterior' ? valido(null, +fecha[2]) : valido(MESES[fecha[1]], +fecha[2]);
  }

  return SIN_PERIODO;
}

/** "42558107 2024-03", "boleta 03-2024", "marzo 2024", o solo "2024". */
function periodoDelNombre(n: string): Periodo {
  let m = n.match(new RegExp(`(?:^|\\D)${UN_ANIO}[-. ](0?[1-9]|1[0-2])(?!\\d)`));
  if (m) return valido(+m[2], +m[1]);

  m = n.match(new RegExp(`(?:^|\\D)(0?[1-9]|1[0-2])[-. ]${UN_ANIO}(?!\\d)`));
  if (m) return valido(+m[1], +m[2]);

  m = n.match(new RegExp(`${UN_MES}[-. ]*${UN_ANIO}(?!\\d)`));
  if (m) return valido(MESES[m[1]], +m[2]);

  m = n.match(new RegExp(`(?:^|\\D)${UN_ANIO}(?!\\d)`));
  return m ? valido(null, +m[1]) : SIN_PERIODO;
}

function valido(mes: number | null, anio: number | null): Periodo {
  return {
    mes: mes !== null && mes >= 1 && mes <= 12 ? mes : null,
    anio: anio !== null && anio >= 1990 && anio <= 2100 ? anio : null,
  };
}
