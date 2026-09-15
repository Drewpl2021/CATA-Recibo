import { readSheet } from 'read-excel-file/browser';
import type { Celda, FilaImportada } from '../models';

/**
 * Leer un Excel en el navegador, para las importaciones.
 *
 * Lo usan la importación de conceptos y la de empleados. El archivo NO se
 * sube: se lee aquí y al servidor solo viajan los títulos y las celdas.
 *
 * OJO: no se exporta desde core/utils/index.ts a propósito. Ese índice lo
 * importa media aplicación, y arrastraría la librería de Excel al paquete
 * principal. Se importa por su ruta, solo en las pantallas que la usan.
 */

export interface HojaLeida {
  titulos: string[];
  filas: FilaImportada[];
}

/** Sin tildes ni signos: para encontrar la fila de los títulos. */
function normalizar(texto: string): string {
  return texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

/**
 * Una celda tal como se manda: el número tal cual (así el backend puede
 * devolverle los ceros a un DNI), la fecha como aaaa-mm-dd (sin ambigüedad
 * de día y mes) y el resto como texto.
 */
export function celdaExcel(valor: unknown): Celda {
  if (valor === null || valor === undefined) return null;
  if (typeof valor === 'number') return Number.isFinite(valor) ? valor : null;
  if (valor instanceof Date) return isNaN(valor.getTime()) ? null : valor.toISOString().slice(0, 10);
  const texto = String(valor).trim();
  return texto === '' ? null : texto;
}

/**
 * Lee la primera hoja y separa los títulos de los datos.
 *
 * Muchas hojas del colegio llevan el nombre de la planilla o el mes en las
 * primeras filas, y los títulos van más abajo. Se toma como cabecera la
 * primera fila (de las 15 primeras) que tenga una columna de DNI.
 */
export async function leerHojaExcel(archivo: File, maxColumnas: number): Promise<HojaLeida> {
  const hoja = (await readSheet(archivo)) as unknown as unknown[][];
  const filas = hoja.map((fila) => (fila ?? []).map(celdaExcel));

  const conDni = filas
    .slice(0, 15)
    .findIndex((f) => f.some((c) => typeof c === 'string' && /\b(dni|documento)\b/.test(normalizar(c))));
  const cabecera = conDni >= 0 ? conDni : filas.findIndex((f) => f.some((c) => c !== null));
  if (cabecera < 0) return { titulos: [], filas: [] };

  const ancho = Math.min(maxColumnas, filas.reduce((max, f) => Math.max(max, f.length), 0));
  const titulos = Array.from({ length: ancho }, (_, i) => {
    const titulo = filas[cabecera][i];
    return titulo === null || titulo === undefined ? '' : String(titulo);
  });

  return {
    titulos,
    filas: filas
      .slice(cabecera + 1)
      .map((celdas, i) => ({ numero: cabecera + i + 2, celdas: celdas.slice(0, ancho) }))
      .filter((f) => f.celdas.some((c) => c !== null)),
  };
}

/** El DNI del nombre de un archivo: "42558107.pdf", "CV 42558107 Juan Pérez.docx". */
export function dniEnNombreDeArchivo(nombre: string): string | null {
  const encontrado = nombre.match(/(?:^|\D)(\d{8})(?:\D|$)/);
  return encontrado ? encontrado[1] : null;
}
