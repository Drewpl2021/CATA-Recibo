import type { AvisoImportacion, FilaImportada } from './importacion-conceptos.model';

/**
 * Importar empleados desde un Excel — ImportacionEmpleadosController.
 *
 * Comparte con la importación de conceptos la forma de mandar las filas y de
 * devolver los avisos; lo propio es qué dato de la ficha es cada columna.
 */

export type EstadoColumnaEmpleado = 'reconocida' | 'informativa' | 'sin_titulo';

export interface ColumnaEmpleadoReconocida {
  indice: number;
  titulo: string;
  /** El dato de la ficha; null si la columna se ignora. */
  campo: string | null;
  estado: EstadoColumnaEmpleado;
  motivo: string;
}

/** Un dato de la ficha que se puede importar. */
export interface CampoFicha {
  campo: string;
  titulo: string;
  /** Hace falta para dar de alta a alguien nuevo. */
  obligatorio: boolean;
}

export interface ResultadoReconocerEmpleados {
  columnas: ColumnaEmpleadoReconocida[];
  campos: CampoFicha[];
}

export interface PayloadImportacionEmpleados {
  archivo: string;
  columnas: { indice: number; titulo: string; campo: string | null }[];
  filas: FilaImportada[];
  /** Los DNIs que tienen hoja de vida elegida, para avisar de los que no calzan. */
  cvs: string[];
}

export interface CambioFicha {
  campo: string;
  titulo: string;
  /** null en una alta: no había nada antes. */
  antes: string | null;
  despues: string;
}

export interface FilaEmpleadoImportada {
  fila: number;
  dni: string;
  nombre: string;
  modo: 'alta' | 'actualizar';
  /** Entra como cesado, o pasa a cesado: sin acceso y con el contrato cerrado. */
  cesado: boolean;
  cv: boolean;
  cambios: CambioFicha[];
}

export interface ResumenImportacionEmpleados {
  filas_leidas: number;
  altas: number;
  actualizaciones: number;
  sin_cambios: number;
  errores: number;
  advertencias: number;
  cvs: number;
  cvs_sin_trabajador: string[];
}

export interface VistaPreviaEmpleados {
  resumen: ResumenImportacionEmpleados;
  filas: FilaEmpleadoImportada[];
  errores: AvisoImportacion[];
  advertencias: AvisoImportacion[];
}

export interface ResultadoImportacionEmpleados {
  resumen: ResumenImportacionEmpleados;
  mensaje: string;
}
