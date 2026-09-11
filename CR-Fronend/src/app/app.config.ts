import { ApplicationConfig, provideZoneChangeDetection } from '@angular/core';
import { NavigationError, provideRouter, withNavigationErrorHandler } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';

import { routes } from './app.routes';
import { authInterceptor } from './core/interceptors/auth.interceptor';

/**
 * Tras un despliegue, la pestaña que ya estaba abierta sigue con el bundle
 * viejo y, al cambiar de pantalla, pide un trozo (chunk) que ya no existe:
 * "Failed to fetch dynamically imported module". Sin esto la navegación
 * muere en silencio y la persona se queda clavada en la pantalla de antes
 * —pasó con la de los términos: firmaba y no entraba—.
 *
 * Se recarga la página UNA vez, directo a donde iba, para traer la versión
 * nueva. La hora de la última recarga evita un bucle si el fallo fuera otro.
 */
function recargarSiFaltaUnTrozo(error: NavigationError): void {
  const mensaje = String((error.error as Error)?.message ?? error.error ?? '');
  const faltaUnTrozo = /Failed to fetch dynamically imported module|Loading chunk [\w-]+ failed|Importing a module script failed/i.test(mensaje);

  if (!faltaUnTrozo) return;

  const MARCA = 'recarga-por-version-nueva';
  const ultima = Number(sessionStorage.getItem(MARCA) || 0);

  // Si ya se recargó hace nada y sigue fallando, no es la versión: no insistir.
  if (Date.now() - ultima < 10_000) return;

  sessionStorage.setItem(MARCA, String(Date.now()));
  window.location.assign(error.url);
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes, withNavigationErrorHandler(recargarSiFaltaUnTrozo)),
    provideHttpClient(withInterceptors([authInterceptor])),
  ]
};
