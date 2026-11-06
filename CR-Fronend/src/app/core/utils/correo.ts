/**
 * Un correo con dominio completo («empleado@empresa.com»). Validators.email
 * deja pasar «a@b», que el servidor rechaza y al que no se le puede enviar nada.
 */
export const CORREO_CON_DOMINIO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
