import type { Documento } from '../models';
import { nombreMes } from '../../shared/constants';
import { fechaLegible } from './fecha';

/**
 * Cómo se nombra y se trata cada documento, en UN solo sitio.
 *
 * Lo usan Mis Documentos (el trabajador) y el expediente de RR.HH. Con los
 * nombres copiados en cada pantalla, bastaba cambiar uno para que la otra
 * siguiera diciendo otra cosa.
 */

/** Los que no se firman, igual que ExpedienteDigital::SIN_FIRMA en el backend. */
export const DOCUMENTOS_SIN_FIRMA: readonly string[] = ['hoja_de_vida'];

export const NOMBRE_TIPO_DOCUMENTO: Record<string, string> = {
  boleta: 'Boleta',
  contrato: 'Contrato firmado',
  cts: 'CTS',
  vacaciones_truncas: 'Vacaciones truncas',
  comprobante_transferencia: 'Comprobante de transferencia',
  hoja_de_vida: 'Hoja de vida',
  otro: 'Documento',
};

/** Lo que RR.HH. puede subir a mano. La boleta no: la genera el sistema. */
export const TIPOS_DOCUMENTO_SUBIBLES: readonly { value: string; label: string }[] = [
  { value: 'hoja_de_vida', label: 'Hoja de vida' },
  { value: 'contrato', label: 'Contrato firmado' },
  { value: 'cts', label: 'CTS' },
  { value: 'vacaciones_truncas', label: 'Vacaciones truncas' },
  { value: 'comprobante_transferencia', label: 'Comprobante de transferencia' },
  { value: 'otro', label: 'Otro documento' },
];

/** "Boleta de Agosto 2026", "Hoja de vida", "Contrato firmado". */
export function nombreDocumento(doc: Documento | null | undefined): string {
  if (!doc) return '';
  const tipo = NOMBRE_TIPO_DOCUMENTO[doc.tipo] ?? 'Documento';
  return doc.planilla ? `${tipo} de ${nombreMes(doc.planilla.mes)} ${doc.planilla.anio}` : tipo;
}

export function documentoSeFirma(doc: Documento): boolean {
  return !DOCUMENTOS_SIN_FIRMA.includes(doc.tipo);
}

export function estadoFirmaLegible(doc: Documento): string {
  if (!documentoSeFirma(doc)) return 'Guardada';
  if (doc.estado_firma === 'firmado') return 'Firmado';
  if (doc.estado_firma === 'visto') return 'Visto';
  return 'Pendiente';
}

export function severidadFirma(doc: Documento): 'success' | 'info' | 'warning' {
  if (!documentoSeFirma(doc)) return 'info';
  if (doc.estado_firma === 'firmado') return 'success';
  return doc.estado_firma === 'visto' ? 'info' : 'warning';
}

export function esPdf(doc: Documento): boolean {
  return (doc.archivo ?? '').toLowerCase().endsWith('.pdf');
}

export function extensionDocumento(doc: Documento): string {
  const nombre = doc.archivo ?? '';
  return nombre.includes('.') ? nombre.slice(nombre.lastIndexOf('.') + 1).toLowerCase() : 'pdf';
}

/** "PDF", "Word" o "imagen": lo que la persona reconoce, no la extensión. */
export function formatoDocumento(doc: Documento): string {
  const ext = extensionDocumento(doc);
  if (ext === 'pdf') return 'PDF';
  return ext === 'doc' || ext === 'docx' ? 'Word' : 'imagen';
}

/**
 * El nombre con que se guarda al descargar. El backend no expone la cabecera
 * Content-Disposition, así que el navegador no lo puede leer de ahí. Con la
 * persona al final, diez hojas de vida descargadas no se llaman todas igual.
 */
export function nombreArchivoDocumento(doc: Documento, persona?: string): string {
  const nombre = persona ? `${nombreDocumento(doc)} - ${persona}` : nombreDocumento(doc);
  return `${nombre}.${extensionDocumento(doc)}`;
}

/** Una fecha de calendario (inicio o fin de contrato), aunque llegue con hora. */
export function fechaDeDia(valor: string | null | undefined): string {
  return valor ? fechaLegible(String(valor).slice(0, 10)) : '';
}

/** Días desde hoy hasta una fecha de calendario; negativo si ya pasó. */
export function diasHasta(valor: string): number {
  const [anio, mes, dia] = String(valor).slice(0, 10).split('-').map(Number);
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  return Math.round((new Date(anio, mes - 1, dia).getTime() - hoy.getTime()) / 86_400_000);
}
