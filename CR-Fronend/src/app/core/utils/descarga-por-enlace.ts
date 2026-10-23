import { environment } from '../../../environments/environment';

/**
 * Baja un archivo con un enlace firmado del servidor (los .zip de boletas,
 * de CTS y de gratificación): lo maneja la barra de descargas del navegador,
 * como cualquier archivo.
 *
 * Bajado por dentro de la página, un .zip grande se cortaba a la mitad en
 * computadoras con un gestor de descargas o el escudo web de un antivirus.
 * El servidor firma la dirección relativa (/api/...): se arma con la misma
 * base de la API para que valga en desarrollo y en el colegio.
 */
export function bajarPorEnlaceFirmado(rutaFirmada: string): void {
  const enlace = document.createElement('a');
  enlace.href = `${environment.apiUrl}${rutaFirmada.replace(/^\/api/, '')}`;
  enlace.rel = 'noopener';
  enlace.click();
}
