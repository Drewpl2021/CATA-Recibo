/**
 * El fondo quieto mientras hay una capa encima (un modal o el panel lateral).
 *
 * Se cuenta cuántas capas lo pidieron en vez de poner y quitar la clase a lo
 * bruto: el panel lateral del expediente abre encima el visor de un documento
 * o el modal de Subir, y al cerrar el de arriba el fondo no debe soltarse
 * mientras el panel siga abierto.
 */
let capas = 0;

export function tomarFondo(): void {
  capas++;
  document.body.classList.add('con-modal');
}

export function soltarFondo(): void {
  capas = Math.max(0, capas - 1);
  if (capas === 0) {
    document.body.classList.remove('con-modal');
  }
}
