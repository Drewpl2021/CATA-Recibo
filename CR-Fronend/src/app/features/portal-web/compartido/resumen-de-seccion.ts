/**
 * Cómo está una sección de una pantalla con índice (Inicio): la línea que
 * se lee bajo su nombre en el índice y lo que le falta, que sube a
 * «Pendientes». Cada panel lo emite al cargar y después de cada cambio.
 */
export interface PendienteDeSeccion {
  cuantos: number;
  /** Lo que sigue al número: «banners publicados sin afiche para celular». */
  texto: string;
}

export interface ResumenDeSeccion {
  /** «5 publicados · 2 sin celular». */
  estado: string;
  /** El estado se pinta en ámbar, con el ícono de aviso. */
  aviso: boolean;
  pendientes: PendienteDeSeccion[];
}

/** «1 banner» o «3 banners». */
export function cuantos(n: number, uno: string, varios: string): string {
  return `${n} ${n === 1 ? uno : varios}`;
}
