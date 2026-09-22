/**
 * Los filtros de una tabla, declarados por la pantalla que los usa.
 *
 * La pantalla dice QUÉ se puede filtrar; el componente se encarga del icono,
 * del panel, de los chips de lo que está puesto y de avisar cuando cambia.
 * Las claves son las mismas que entiende el backend, así que lo que sale de
 * acá se le pasa tal cual a la petición.
 */

/** Una opción de un filtro de lista. */
export interface OpcionFiltro {
  valor: string;
  etiqueta: string;
}

export interface CampoFiltro {
  /** El parámetro que viaja al backend (ej. 'sede_id'). */
  clave: string;
  etiqueta: string;
  /**
   * - `opciones`: un desplegable.
   * - `fecha`: dos fechas, desde (`clave`) y hasta (`claveHasta`).
   * - `si-no`: una casilla; marcada manda "1".
   */
  tipo: 'opciones' | 'fecha' | 'si-no';
  opciones?: OpcionFiltro[];
  /** Solo en `opciones`: el texto de "sin filtrar". Por defecto "Todos". */
  vacio?: string;
  /** Solo en `fecha`: la clave de la segunda fecha. */
  claveHasta?: string;
  /** Una línea corta debajo del campo, cuando hace falta explicarlo. */
  ayuda?: string;
  /** Ocupa las dos columnas del panel (para un rango de fechas). */
  ancho?: 'normal' | 'doble';
}

/** Lo que se manda al backend: clave => valor, ya sin los vacíos. */
export type ValoresFiltro = Record<string, string>;
