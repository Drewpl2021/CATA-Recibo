/**
 * Los roles: cómo los guarda el backend y cómo se leen en pantalla.
 *
 * El backend los guarda en minúscula ('admin', 'rrhh', 'empleado') porque
 * ahí el nombre no es una etiqueta: es la llave con la que decide permisos
 * —está escrita tal cual en los `rol:admin` de las rutas y en comparaciones
 * como `!== 'rrhh'`—. Por eso no se cambia allá: se traduce acá.
 *
 * Regla para toda la app: el nombre crudo NUNCA se pinta. Cualquier sitio
 * que muestre un rol pasa por `etiquetaRol()`, para que el usuario lea
 * siempre lo mismo —Admin, RRHH, Empleado— esté donde esté.
 */
export const ROL_ADMIN = 'admin';
export const ROL_RRHH = 'rrhh';
export const ROL_EMPLEADO = 'empleado';

/** Los tres que el backend tiene escritos en el código y no se renombran. */
export const ROLES_DEL_SISTEMA: readonly string[] = [ROL_ADMIN, ROL_RRHH, ROL_EMPLEADO];

export const NOMBRE_ROL_LEGIBLE: Record<string, string> = {
  [ROL_ADMIN]: 'Admin',
  [ROL_RRHH]: 'RRHH',
  [ROL_EMPLEADO]: 'Empleado',
};

/** ¿Es uno de los tres del sistema? El nombre se compara en minúscula. */
export function esRolDelSistema(nombre: string | null | undefined): boolean {
  return ROLES_DEL_SISTEMA.includes((nombre ?? '').trim().toLowerCase());
}

/**
 * El rol tal como se lee: 'rrhh' → "RRHH".
 *
 * Acepta lo que llegue, porque el backend manda el rol de tres formas según
 * el endpoint: el objeto entero, solo el nombre, o nada.
 *
 * Un rol creado a mano desde la pantalla de Roles no está en la tabla de
 * arriba, así que se muestra con su primera letra en mayúscula en vez de
 * dejarlo en minúscula: "auxiliar" se lee "Auxiliar".
 */
export function etiquetaRol(rol: unknown, siNoHay = '—'): string {
  const nombre =
    typeof rol === 'string'
      ? rol
      : ((rol as { nombre?: string } | null)?.nombre ?? '');

  const clave = nombre.trim();
  if (!clave) return siNoHay;

  return NOMBRE_ROL_LEGIBLE[clave.toLowerCase()] ?? clave.charAt(0).toUpperCase() + clave.slice(1);
}
