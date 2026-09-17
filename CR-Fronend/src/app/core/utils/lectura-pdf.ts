/**
 * Leer un archivo en el navegador: el texto de un PDF y la huella de
 * cualquier archivo.
 *
 * Se importa por RUTA y no desde el índice de utils, igual que
 * lectura-excel: pdf.js pesa, y así solo lo baja la pantalla que lo usa.
 */

/** El motor de pdf.js sale de nuestro propio servidor, no de un CDN. */
const MOTOR_PDF = 'assets/pdf/pdf.worker.min.mjs';

/**
 * El texto de las primeras páginas de un PDF. Con tres sobra: la boleta del
 * colegio es de una página, y un contrato dice de quién es al principio.
 */
export async function textoDePdf(archivo: File, paginas = 3): Promise<string> {
  const pdfjs = await import('pdfjs-dist');
  pdfjs.GlobalWorkerOptions.workerSrc = MOTOR_PDF;

  const pdf = await pdfjs.getDocument({ data: new Uint8Array(await archivo.arrayBuffer()) }).promise;
  try {
    const partes: string[] = [];
    for (let p = 1; p <= Math.min(pdf.numPages, paginas); p++) {
      const contenido = await (await pdf.getPage(p)).getTextContent();
      for (const item of contenido.items) {
        if ('str' in item) partes.push(item.str, item.hasEOL ? '\n' : ' ');
      }
    }
    return partes.join('');
  } finally {
    await pdf.destroy();
  }
}

/**
 * El SHA-256 del archivo. Dos archivos iguales tienen la misma huella, y así
 * subir la misma carpeta dos veces no duplica nada.
 *
 * null si el navegador no lo permite: solo lo hace en páginas seguras (https
 * o localhost). El servidor igual vuelve a revisarlo al subir.
 */
export async function huellaDeArchivo(archivo: File): Promise<string | null> {
  if (!globalThis.crypto?.subtle) return null;
  const resumen = await crypto.subtle.digest('SHA-256', await archivo.arrayBuffer());
  return Array.from(new Uint8Array(resumen), (b) => b.toString(16).padStart(2, '0')).join('');
}
