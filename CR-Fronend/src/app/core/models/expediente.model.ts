import { Contrato, Empleado } from './empleado.model';
import { Documento } from './documento.model';

/** Un trabajador en la lista de Documentos del personal (ExpedienteController@index). */
export interface FilaExpediente
  extends Pick<Empleado, 'id' | 'dni' | 'nombre' | 'apellido' | 'estado' | 'area' | 'cargo' | 'sede'> {
  contrato_vigente: Contrato | null;
  documentos_count: number;
  boletas_por_firmar: number;
  /** Cuándo se subió su hoja de vida vigente; null si no tiene. */
  hoja_de_vida_fecha: string | null;
}

export type FiltroExpedientes = '' | 'sin_hoja_de_vida' | 'boletas_por_firmar' | 'contrato_por_vencer' | 'de_baja';

/** Cuántos caen en cada chip, sobre todo el personal. */
export interface ResumenExpedientes {
  trabajadores: number;
  sin_hoja_de_vida: number;
  boletas_por_firmar: number;
  contrato_por_vencer: number;
  de_baja: number;
  dias_por_vencer: number;
}

export interface ContratoDelExpediente extends Contrato {
  /** Los documentos subidos a este contrato: el contrato firmado, una adenda… */
  documentos: Documento[];
}

/** Todo lo que hay a nombre de un trabajador (ExpedienteController@show). */
export interface Expediente {
  empleado: Empleado;
  hoja_de_vida: Documento | null;
  /** Las que se reemplazaron: siguen guardadas, pero ya no son la vigente. */
  hojas_anteriores: Documento[];
  /** Lo que trae el trabajador: hoja de vida, foto, DNI, certificados. */
  personales: Documento[];
  /** Meses con planilla armada a los que todavía no se les emitió boleta. */
  boletas_sin_emitir: { id: string; mes: number; anio: number; total: number }[];
  contratos: ContratoDelExpediente[];
  /** Contratos de antes del sistema: archivos sueltos, sin contrato registrado. */
  contratos_anteriores: Documento[];
  boletas: Documento[];
  /** CTS, comprobantes y lo que se subió sin contrato. */
  otros: Documento[];
  dias_por_vencer: number;
}
