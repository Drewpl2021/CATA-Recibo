/**
 * Traduce un error HTTP de Laravel a un mensaje mostrable. Antes cada
 * componente repetía este mismo bloque de if/else a mano.
 *
 * Formatos que maneja:
 *   422 → { message, errors: { campo: ["mensaje"] } }
 *   otros → { message } o { data: { message } }
 */
export function mensajeErrorApi(err: any, porDefecto = 'Ocurrió un error inesperado.'): string {
  const errores = err?.error?.errors;
  if (errores && typeof errores === 'object') {
    const mensajes = Object.values(errores).flat() as string[];
    if (mensajes.length) return mensajes.join(' ');
  }

  return err?.error?.message || err?.error?.data?.message || porDefecto;
}
