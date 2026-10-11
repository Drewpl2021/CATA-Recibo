import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../core/services';
import { mensajeErrorApi } from '../../../core/utils';

/**
 * Lo que comparten las pantallas del panel «Portal Web».
 */

/**
 * Muestra un error del backend del panel.
 *
 *  - 409: alguien guardó entre medio; se pide volver a cargar.
 *  - 422 con `contrato`: el portal no podría mostrar lo que quedaría.
 *  - 422 de campos: si la pantalla sabe ponerlos junto a su campo, ahí.
 */
export function mostrarErrorDelPanel(
  toast: ToastService,
  err: HttpErrorResponse,
  porDefecto: string,
  alCampo?: (campo: string, mensaje: string) => void,
): void {
  if (err.status === 409) {
    toast.warning('Alguien guardó antes', mensajeErrorApi(err, porDefecto));
    return;
  }

  const errores: Record<string, string[]> = err.error?.errors ?? {};

  if (errores['contrato']?.length) {
    toast.error('El portal no podría mostrarlo', errores['contrato'].join(' '));
    return;
  }

  if (alCampo && err.status === 422) {
    Object.entries(errores).forEach(([campo, mensajes]) => alCampo(campo, mensajes[0]));
  }

  toast.error('No se guardó', mensajeErrorApi(err, porDefecto));
}

/**
 * Los ids de una lista con uno de ellos subido (-1) o bajado (+1) un lugar.
 * null si ya está en el borde. El backend recibe siempre la lista entera.
 */
export function idsMovidos(ids: string[], id: string, direccion: -1 | 1): string[] | null {
  const i = ids.indexOf(id);
  const j = i + direccion;
  if (i < 0 || j < 0 || j >= ids.length) return null;

  const nuevos = [...ids];
  [nuevos[i], nuevos[j]] = [nuevos[j], nuevos[i]];
  return nuevos;
}
