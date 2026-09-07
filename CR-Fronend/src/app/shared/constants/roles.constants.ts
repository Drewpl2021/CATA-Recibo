/**
 * Nombres de rol tal como los guarda el backend (roles.nombre). Los roles en
 * sí se gestionan dinámicamente (pantalla de Roles), pero estos 3 nombres
 * son los únicos con lógica especial hardcodeada en el backend (permisos,
 * middleware "rol:"), así que sí conviene tenerlos como constante acá.
 */
export const ROL_ADMIN = 'admin';
export const ROL_RRHH = 'rrhh';
export const ROL_EMPLEADO = 'empleado';

export const NOMBRE_ROL_LEGIBLE: Record<string, string> = {
  [ROL_ADMIN]: 'Administrador',
  [ROL_RRHH]: 'Recursos Humanos',
  [ROL_EMPLEADO]: 'Empleado',
};
