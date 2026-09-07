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

export interface Dashboard {
  periodo: { mes: number; anio: number };
  resumen: ResumenDashboard;
  remuneracionPorArea: DatoGrafico[];
  sistemaPensiones: DatoGrafico[];
  tipoContrato: DatoGrafico[];
  tendenciaNomina: DatoGrafico[];
  firmaBoletas: FirmaBoletas;
  contratosPorVencer: ContratoPorVencer[];
}
