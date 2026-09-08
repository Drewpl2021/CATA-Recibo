/**
 * Forma estándar en que responde el backend Laravel de CATA-Recibo:
 *   { "success": true, "data": ... }
 * y en los errores de validación (422):
 *   { "message": "...", "errors": { "campo": ["mensaje"] } }
 */
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  mensaje?: string;
}

/** Respuesta de los endpoints que solo confirman una acción (ej. eliminar). */
export type ApiMessageResponse = ApiResponse<{ message: string }>;

/** Cuerpo de un error 422 de Laravel. */
export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

/**
 * Una página de resultados, tal como la devuelve el backend cuando se le
 * pide con ?page&size (ver App\Traits\ListadoPaginado).
 *
 *   { "success": true, "data": {
 *       "content": [...], "totalElements": 47,
 *       "currentPage": 0, "totalPages": 5 } }
 *
 * `currentPage` va en base 0, igual que la manda el frontend.
 */
export interface Pagina<T> {
  content: T[];
  totalElements: number;
  currentPage: number;
  totalPages: number;

  /*
   * Conteos que algunos listados añaden al lado de la página, calculados
   * sobre TODO lo que pasa el filtro y no sobre las filas devueltas (ver
   * ListadoPaginado::conteoPorEstado en el backend). Van opcionales porque
   * cada entidad manda los suyos: áreas, cargos, sedes, empleados y
   * usuarios cuentan activos e inactivos; los contratos, vigentes y
   * finalizados; las vacaciones, en qué estado están; los demás no mandan
   * ninguno.
   */
  total?: number;
  activos?: number;
  inactivos?: number;
  vigentes?: number;
  finalizados?: number;
  pendientes?: number;
  aprobadas?: number;
  rechazadas?: number;
  /** Notificaciones sin leer, para el globito de la campana. */
  noLeidas?: number;
}

/** Lo que el frontend le pide al backend para armar una página. */
export interface ParametrosPagina {
  page?: number;
  size?: number;
  search?: string;
}

/** Página vacía, para inicializar sin tener que comprobar null en la plantilla. */
export function paginaVacia<T>(size = 10): Pagina<T> {
  return { content: [], totalElements: 0, currentPage: 0, totalPages: 1 };
}
