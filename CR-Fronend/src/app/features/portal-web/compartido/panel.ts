import { HttpErrorResponse } from '@angular/common/http';
import { FormArray } from '@angular/forms';

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

/** Mueve un elemento de una lista del formulario un lugar arriba (-1) o abajo (+1). */
export function moverEn(lista: FormArray, i: number, direccion: -1 | 1): void {
  const j = i + direccion;
  if (j < 0 || j >= lista.length) return;
  const control = lista.at(i);
  lista.removeAt(i);
  lista.insert(j, control);
  lista.markAsDirty();
}

/**
 * Lleva el foco al primer campo con error del modal abierto, después de que
 * la plantilla lo marque. Para los modales cortos; los largos (con
 * pw-cuerpo-desplazable) usan su irAlPrimerError(), que además desplaza.
 */
export function enfocarPrimerError(): void {
  setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
}

/** Hoy en Lima, AAAA-MM-DD: la fecha que usa el colegio (y el portal, para «En curso» o «Terminó»). */
export function hoyEnLima(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' }).format(new Date());
}

/**
 * La dirección para ver en el panel una imagen subida: su ruta relativa
 * (/api/portal/medios/…), porque el panel y los medios viven en el mismo
 * servidor. La dirección pública, la del portal, sigue siendo la absoluta.
 */
export function urlParaVer(url: string | null | undefined): string {
  const u = url ?? '';
  const i = u.indexOf('/api/portal/medios/');
  return i >= 0 ? u.slice(i) : u;
}
