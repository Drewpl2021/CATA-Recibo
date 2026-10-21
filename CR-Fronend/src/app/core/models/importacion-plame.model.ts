/**
 * Importar el PLAME de RR.HH. (ver ImportacionPlameController en el backend).
 *
 * El archivo se manda entero al servidor: el PLAME es un .xls antiguo que
 * el navegador no abre. Se lee en memoria y no se guarda.
 */

/** crear: el mes no tenía planillas · reemplazar: ya se había importado · comparar: ya tiene planillas del sistema. */
export type ModoPlame = 'crear' | 'reemplazar' | 'comparar';

export type EstadoColumnaPlame = 'reconocida' | 'otros' | 'dato' | 'total' | 'informativa' | 'ignorada' | 'falta';

/** Lo que RR.HH. puede decidir para una columna que no es de siempre. */
export type DecisionColumnaPlame = 'otros_ingresos' | 'otros_descuentos' | 'ignorar' | string;

export type SituacionPlame =
  | 'existe'
  | 'nuevo_activo'
  | 'nuevo_cesado'
  | 'igual'
  | 'con_diferencias'
  | 'sin_planilla'
  | 'sin_ficha';

export interface ColumnaPlame {
  indice: number;
  letra: string;
  titulo: string;
  grupo: string;
  rol: 'dato' | 'informativa' | 'sueldo' | 'concepto' | 'otros' | 'total' | 'ignorar' | 'falta';
  clave: string | null;
  concepto: string | null;
  concepto_id: string | null;
  tipo: string | null;
  estado: EstadoColumnaPlame;
  motivo: string;
  /** Las que no son de siempre: RR.HH. elige a qué concepto van. */
  editable: boolean;
  ejemplo: string;
}

export interface LineaPlame {
  concepto: string;
  tipo: string;
  monto: number;
  descripcion: string | null;
}

export interface DiferenciaPlame {
  concepto: string;
  plame: number;
  sistema: number;
  diferencia: number;
}

export interface TrabajadorPlame {
  fila: number;
  dni: string;
  nombre: string;
  sueldo: number;
  neto: number;
  neto_plame: number | null;
  neto_sistema: number | null;
  situacion: SituacionPlame;
  fecha_cese?: string | null;
  lineas: LineaPlame[];
  diferencias: DiferenciaPlame[];
  avisos: string[];
}

export interface AvisoPlame {
  fila: number | null;
  dni: string | null;
  columna: string | null;
  mensaje: string;
}

export interface ResumenPlame {
  mes: number;
  anio: number;
  periodo: string;
  archivo: string;
  hoja: string | null;
  modo: ModoPlame;
  filas_leidas: number;
  trabajadores: number;
  existentes: number;
  nuevos_activos: number;
  nuevos_cesados: number;
  con_diferencias: number;
  con_avisos: number;
  neto_total: number;
  errores: number;
  advertencias: number;
  recalculadas?: number;
}

export interface VistaPreviaPlame {
  resumen: ResumenPlame;
  columnas: ColumnaPlame[];
  trabajadores: TrabajadorPlame[];
  solo_en_sistema: { dni: string; nombre: string; neto_sistema: number }[];
  errores: AvisoPlame[];
  advertencias: AvisoPlame[];
}

export interface ResultadoPlame {
  resumen: ResumenPlame;
  mensaje: string;
}

export interface DecisionesPlame {
  /** índice de columna → qué hacer con ella */
  columnas: Record<number, DecisionColumnaPlame>;
  /** DNI de alguien nuevo → si entra activo o cesado */
  personas: Record<string, 'activo' | 'cesado'>;
}
