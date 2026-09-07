/** Un paso de <app-wizard>. */
export interface PasoWizard {
  /** Identificador con el que el padre decide qué sección pintar. */
  id: string;
  titulo: string;
  /** Clave del catálogo de íconos (shared/icons/icon-map). */
  icono?: string;
  /**
   * Nombres de los controles del formulario que viven en este paso.
   * El wizard los usa para marcar el paso en rojo cuando alguno es
   * inválido, y para no dejar avanzar con errores dentro.
   */
  campos?: string[];
}
