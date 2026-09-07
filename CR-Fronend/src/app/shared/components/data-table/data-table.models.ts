/**
 * Definición de una columna, reutilizada por cualquier pantalla que use
 * <app-data-table>. "campo" soporta rutas con punto (ej. "cargo.nombre")
 * para leer relaciones anidadas que ya vienen del backend con ?with=...
 */
export interface ColumnaTabla<T = any> {
  campo: string;
  header: string;
  tipo?: 'texto' | 'fecha' | 'fecha-hora' | 'moneda' | 'badge' | 'boolean' | 'icono';
  ancho?: string;
  /** Solo para tipo 'badge': decide la severidad (color) según el valor de la celda. */
  badgeSeveridad?: (valor: any, fila: T) => 'success' | 'info' | 'warning' | 'danger' | 'secondary';
  /** Transforma el valor crudo antes de mostrarlo (ej. traducir un enum a texto legible). */
  formatear?: (valor: any, fila: T) => string;
  ordenable?: boolean;
}

export function leerCampo(fila: any, ruta: string): any {
  return ruta.split('.').reduce((obj, key) => (obj == null ? undefined : obj[key]), fila);
}

/**
 * Botón extra en la columna de acciones, para lo que cada pantalla necesite
 * además de ver/editar/eliminar (ej. "Generar planillas" en Periodos).
 *
 *   accionesPersonalizadas: AccionPersonalizada<Periodo>[] = [
 *     { id: 'generar', titulo: 'Generar planillas', icono: 'table_chart' },
 *   ];
 *   (accionPersonalizada)="alAccionar($event)"
 */
export interface AccionPersonalizada<T = any> {
  /** Lo que llega en el evento para saber qué se pulsó. */
  id: string;
  /** Texto del tooltip. */
  titulo: string;
  /** Clave del catálogo de íconos (shared/icons/icon-map). */
  icono: string;
  /** Texto junto al ícono. Sin él, el botón queda cuadrado y solo con ícono. */
  etiqueta?: string;
  /** Color del botón; por defecto el azul de marca. */
  severidad?: 'brand' | 'success' | 'warning' | 'danger';
  /** Devuelve false para ocultar el botón en esa fila concreta. */
  visible?: (fila: T) => boolean;
}
