import type { TipoConcepto } from './configuracion.model';

/**
 * Importar conceptos de pago desde un Excel — ImportacionConceptosController.
 *
 * El Excel se lee en el navegador: al servidor solo viajan los títulos y las
 * celdas, nunca el archivo.
 */

/** Lo que el sistema entendió de una columna. */
export type EstadoColumna =
  | 'sin_titulo'
  | 'dni'
  | 'detalle'
  | 'reconocida'
  | 'informativa'
  | 'protegida'
  | 'dudosa'
  | 'nueva';

/** Lo que se hace con ella al importar. */
export type AccionColumna = 'dni' | 'usar' | 'crear' | 'detalle' | 'ignorar';

// El tipo de concepto es el mismo del catálogo: no se repite aquí.

export interface ConceptoCandidato {
  id: string;
  nombre: string;
  tipo: TipoConcepto;
  /** De 0 a 1: cuánto se parece al título de la columna. */
  puntaje?: number;
}

export interface ColumnaReconocida {
  indice: number;
  titulo: string;
  estado: EstadoColumna;
  /** 'elegir' = se parece a varios y RR.HH. tiene que decidir. */
  accion: AccionColumna | 'elegir';
  origen: 'recordado' | 'exacto' | 'parecido' | null;
  payment_concept_id: string | null;
  candidatos: ConceptoCandidato[];
  nuevo: { nombre: string | null; tipo: TipoConcepto | null } | null;
  de_columna: number | null;
  motivo: string;
}

export interface ResultadoReconocer {
  columnas: ColumnaReconocida[];
  /** Los conceptos que se pueden importar, para los desplegables. */
  catalogo: ConceptoCandidato[];
}

export type Celda = string | number | null;

export interface FilaImportada {
  /** Número de fila en el Excel, para que los errores digan dónde mirar. */
  numero: number;
  celdas: Celda[];
}

export interface ColumnaMapeada {
  indice: number;
  titulo: string;
  accion: AccionColumna;
  payment_concept_id: string | null;
  nuevo: { nombre: string; tipo: TipoConcepto } | null;
  de_columna: number | null;
}

export interface PayloadImportacion {
  mes: number;
  anio: number;
  archivo: string;
  columnas: ColumnaMapeada[];
  filas: FilaImportada[];
}

export interface AvisoImportacion {
  fila: number | null;
  dni: string | null;
  columna: string | null;
  mensaje: string;
}

export interface CambioImportado {
  columna: string;
  concepto: string;
  tipo: TipoConcepto;
  accion: 'agregar' | 'cambiar' | 'quitar';
  antes: number | null;
  despues: number;
  descripcion: string | null;
}

export interface TrabajadorImportado {
  fila: number;
  dni: string;
  nombre: string;
  planilla: string;
  neto_antes: number;
  neto_despues: number;
  cambios: CambioImportado[];
}

export interface ResumenImportacion {
  mes: number;
  anio: number;
  periodo: string;
  filas_leidas: number;
  trabajadores: number;
  lineas_nuevas: number;
  lineas_cambiadas: number;
  lineas_quitadas: number;
  errores: number;
  advertencias: number;
  conceptos_nuevos: { nombre: string; tipo: TipoConcepto }[];
  /** Solo al aplicar. */
  conceptos_creados?: string[];
}

export interface VistaPreviaImportacion {
  resumen: ResumenImportacion;
  trabajadores: TrabajadorImportado[];
  errores: AvisoImportacion[];
  advertencias: AvisoImportacion[];
}

export interface ResultadoImportacion {
  resumen: ResumenImportacion;
  mensaje: string;
}
