/**
 * Producción — lo que se compila para el servidor.
 *
 * La dirección del API es RELATIVA y sin barra al principio, a propósito:
 * el navegador la resuelve contra el <base href> con que se compiló.
 *
 *   - En el hosting (cata.edu.pe/rrhh/, base href /rrhh/) → /rrhh/api
 *   - En el VPS con Docker (base href /)                  → /api
 *   - En el entorno local (base href /, con proxy)        → /api
 *
 * Así el mismo código sirve en los tres sin cambios locales, entrando por la
 * IP o por el dominio, y sin CORS: para el navegador es un solo origen.
 */
export const environment = {
  production: true,
  apiUrl: 'api'
};
