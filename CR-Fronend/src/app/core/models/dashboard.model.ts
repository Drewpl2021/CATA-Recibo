/**
 * Las cifras del Panel de Control, tal como las manda el backend.
 *
 * Todo sale de la base de datos: antes estos números estaban escritos a
 * mano en el componente, así que la pantalla enseñaba lo mismo aunque el
 * colegio no tuviera ni un trabajador dado de alta.
 */

/** Un par etiqueta/valor: lo que come cualquiera de los gráficos. */
export interface DatoGrafico {
  etiqueta: string;
  valor: number;
}

export interface ResumenDashboard {
  empleadosActivos: number;
  altasDelMes: number;
  nominaDelMes: number;
  /** El neto del mes anterior (en enero, el diciembre del año pasado). */
  nominaMesAnterior: number;
  /** Lo que pone el colegio encima del neto: EsSalud y demás aportes. */
  aportesColegio: number;
  planillasDelMes: number;
  boletasEmitidas: number;
  contratosPorVencer: number;
}

export interface FirmaBoletas {
  firmadas: number;
  vistas: number;
  pendientes: number;
}

export interface ContratoPorVencer {
  nombre: string;
  cargo: string;
  fecha: string;
  /** Días que faltan; negativo si ya venció. */
  dias: number;
  urgencia: 'urgente' | 'proximo' | 'normal';
}

/** Una cosa que RR.HH. tiene pendiente de hacer, con dónde se resuelve. */
export interface PendienteRrhh {
  clave: string;
  cuantos: number;
  texto: string;
  ruta: string;
}

/** Lo que se pagó en un área, y a cuántas personas. */
export interface RemuneracionArea extends DatoGrafico {
  personas: number;
}

/** Cuántos entraron y cuántos se fueron en un mes. */
export interface MovimientoMes {
  etiqueta: string;
  altas: number;
  bajas: number;
}

/** Quién cumple años este mes. */
export interface CumpleanosDelMes {
  nombre: string;
  cargo: string;
  area: string;
  sede: string;
  dia: number;
  /** "15/09", para el Excel y para leerlo de corrido. */
  fecha: string;
  /** Los años que cumple en el periodo que se está mirando. */
  edad: number;
  es_hoy: boolean;
  /** Ya fue: sirve para saber a quién todavía se le puede saludar. */
  ya_paso: boolean;
}

export interface Dashboard {
  periodo: { mes: number; anio: number; sede_id?: string | null };
  /** Lo que hay que hacer, no lo que hay que mirar. */
  pendientes: PendienteRrhh[];
  cumpleanos: CumpleanosDelMes[];
  /** A dónde se va la plata del mes: básico, bonos, descuentos, aportes. */
  composicionNomina: DatoGrafico[];
  personalPorSede: DatoGrafico[];
  /** Cuánto lleva cada quien en el colegio, por tramos. */
  antiguedad: DatoGrafico[];
  /** Los conceptos que más pesan, sin contar los de ley. */
  topConceptos: DatoGrafico[];
  resumen: ResumenDashboard;
  remuneracionPorArea: RemuneracionArea[];
  sistemaPensiones: DatoGrafico[];
  tipoContrato: DatoGrafico[];
  tendenciaNomina: DatoGrafico[];
  /** Los doce meses del año anterior, para compararlos. */
  tendenciaAnterior: DatoGrafico[];
  movimientoPersonal: MovimientoMes[];
  /** Las edades del personal activo, por tramos. */
  edades: DatoGrafico[];
  firmaBoletas: FirmaBoletas;
  contratosPorVencer: ContratoPorVencer[];
}
