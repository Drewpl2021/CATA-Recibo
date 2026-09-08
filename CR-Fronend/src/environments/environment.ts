/**
 * Producción — lo que se compila para el servidor.
 *
 * La dirección del API es RELATIVA a propósito: el mismo nginx
 * sirve la aplicación por "/" y Laravel por "/api", así que el
 * navegador pide a la dirección por la que entró.
 *
 * Eso es lo que hace que funcione igual entrando por la IP del
 * VPS hoy y por el dominio del colegio mañana, sin volver a
 * compilar nada. Y de paso no hay CORS: para el navegador es un
 * solo origen.
 */
export const environment = {
  production: true,
  apiUrl: '/api'
};
