/**
 * Forma única en que TODAS las constantes de opciones (para <p-select>,
 * <p-selectButton>, etc.) representan un valor — así cualquier componente
 * reciclable que reciba "opciones" sabe qué forma esperar, sin importar
 * de qué catálogo vengan.
 */
export interface Opcion<T = string> {
  label: string;
  value: T;
}
