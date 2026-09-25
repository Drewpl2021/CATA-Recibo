import { Opcion } from './models';

/** Documento.tipo — DocumentoController (backend) */
export const TIPO_DOCUMENTO_OPCIONES: readonly Opcion[] = [
  { label: 'Boleta', value: 'boleta' },
  { label: 'Contrato', value: 'contrato' },
  { label: 'CTS', value: 'cts' },
  { label: 'Vacaciones truncas', value: 'vacaciones_truncas' },
  { label: 'Comprobante de transferencia', value: 'comprobante_transferencia' },
  { label: 'Hoja de vida', value: 'hoja_de_vida' },
  { label: 'Foto', value: 'foto' },
  { label: 'Copia del DNI', value: 'dni' },
  { label: 'Certificado de estudios', value: 'certificado' },
  { label: 'Otro', value: 'otro' },
];

/**
 * Lo que sube el propio trabajador desde Mis Documentos.
 *
 * Son SUS papeles. Lo que emite el colegio —boleta, contrato, CTS— no está
 * en esta lista: dejarle subir su propia boleta sería dejarle escribirla.
 */
export const TIPO_DOCUMENTO_PROPIO: readonly Opcion[] = [
  { label: 'Hoja de vida', value: 'hoja_de_vida' },
  { label: 'Foto', value: 'foto' },
  { label: 'Copia del DNI', value: 'dni' },
  { label: 'Certificado de estudios', value: 'certificado' },
  { label: 'Otro', value: 'otro' },
];

/** Documento.estado_firma / estado_firma_empleador */
export const ESTADO_FIRMA_OPCIONES: readonly Opcion[] = [
  { label: 'Pendiente', value: 'pendiente' },
  { label: 'Visto', value: 'visto' },
  { label: 'Firmado', value: 'firmado' },
];

/** Severidad para .status-badge (ver DataTableComponent) según estado_firma */
export const ESTADO_FIRMA_SEVERIDAD: Record<string, 'success' | 'warning' | 'info' | 'secondary'> = {
  pendiente: 'warning',
  visto: 'info',
  firmado: 'success',
};
