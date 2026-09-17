/**
 * Boletas y contratos de antes del sistema, subidos en lote —
 * DocumentosAnterioresController.
 */

export type TipoDocumentoAnterior = 'boleta_anterior' | 'contrato_anterior';

/** Lo que se sabe de un archivo antes de subirlo. */
export interface ArchivoAnteriorPayload {
  /** Su posición en la lista elegida: con eso se vuelve a encontrar el File. */
  indice: number;
  nombre: string;
  huella: string | null;
  tipo: TipoDocumentoAnterior | null;
  /** Los DNI encontrados dentro del archivo. */
  dnis: string[];
  dni_nombre: string | null;
  /** El DNI que eligió RR.HH. en la revisión: manda sobre lo encontrado. */
  dni_elegido: string | null;
  mes: number | null;
  anio: number | null;
}

/**
 *   lista     se sube
 *   omitida   ya estaba (el mismo archivo, o la boleta de ese mes)
 *   elegir    el archivo trae el DNI de varias personas
 *   error     falta el dueño, el tipo o el mes
 */
export type EstadoArchivoAnterior = 'lista' | 'omitida' | 'elegir' | 'error';

export interface TrabajadorDelArchivo {
  id: string;
  dni: string;
  /** "Apellidos, Nombres". */
  nombre: string;
  estado: string;
}

export interface FilaArchivoAnterior {
  indice: number;
  nombre: string;
  tipo: TipoDocumentoAnterior | null;
  mes: number | null;
  anio: number | null;
  estado: EstadoArchivoAnterior;
  mensaje: string;
  /** De dónde salió el dueño: dentro del PDF, el nombre del archivo o a mano. */
  origen: 'pdf' | 'nombre' | 'elegido' | null;
  empleado: TrabajadorDelArchivo | null;
  candidatos: TrabajadorDelArchivo[];
}

export interface VistaDocumentosAnteriores {
  filas: FilaArchivoAnterior[];
  resumen: {
    archivos: number;
    listas: number;
    omitidas: number;
    por_revisar: number;
  };
}

export interface DatosDocumentoAnterior {
  dni: string;
  tipo: TipoDocumentoAnterior;
  mes: number | null;
  anio: number | null;
}
