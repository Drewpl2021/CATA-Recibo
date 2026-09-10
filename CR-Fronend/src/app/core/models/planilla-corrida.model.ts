import { Periodo } from './configuracion.model';

/**
 * Una corrida de planilla: "Planilla TIC — Septiembre 2026".
 *
 * Es la agrupación con nombre que contiene las filas de los trabajadores.
 * `personas` y `masa_salarial` no se guardan en la base: los cuenta el
 * backend sobre las planillas de la corrida cada vez que responde.
 */
export interface PlanillaCorrida {
  id: string;
  nombre: string;
  mes: number;
  anio: number;
  periodo_id?: string | null;
  estado: 'abierta' | 'cerrada';
  observaciones?: string | null;
  periodo?: Periodo | null;
  /** Cuántos trabajadores tiene dentro. */
  personas?: number;
  /** Lo que suma pagar a esa gente. Llega como número o string decimal. */
  masa_salarial?: number | string;
  created_at?: string;
}

/** Lo que se manda al crear una corrida. */
export interface PlanillaCorridaPayload {
  nombre: string;
  /** Un mes suelto. Al crear se manda `meses`; esto queda para editar. */
  mes?: number;
  anio?: number;
  /** Varios meses de golpe, como 'AAAA-MM'. Sale una planilla por cada uno. */
  meses?: string[];
  periodo_id?: string | null;
  observaciones?: string | null;
  /** Con `generar`, además de crearla le arma dentro la planilla del grupo. */
  generar?: boolean;
  empleado_ids?: string[];
  area_id?: string | null;
  cargo_id?: string | null;
  sede_id?: string | null;
}

/** Lo que devuelve una generación: el resumen y el detalle fila por fila. */
export interface ResultadoGeneracion {
  corrida?: PlanillaCorrida;
  resumen: { generadas: number; omitidas: number; evaluados: number };
  detalle: {
    empleado: string;
    empleado_id: string;
    estado: 'generada' | 'omitida';
    motivo?: string;
    planilla_id?: string;
  }[];
  aviso?: string;
}

/**
 * Lo que devuelve crear la planilla de varios meses: una por mes.
 *
 * Los meses que ya tenían una planilla con ese nombre no se rehacen ni
 * hacen fallar a los demás: vuelven aquí en `mesesOmitidos` para poder
 * decirle a RR.HH. cuáles se saltaron y por qué.
 */
export interface ResultadoVariosMeses {
  corridas: PlanillaCorrida[];
  resumen: {
    planillas: number;
    mesesOmitidos: number;
    generadas: number;
    omitidas: number;
  };
  /** Qué salió en cada mes: no es lo mismo marzo que septiembre. */
  porMes: { mes: number; anio: number; generadas: number; omitidas: number; evaluados: number }[];
  /** Trabajador por trabajador. Solo viene cuando se pidió un mes solo. */
  detalle: ResultadoGeneracion['detalle'];
  mesesOmitidos: { mes: number; anio: number; motivo: string }[];
}
