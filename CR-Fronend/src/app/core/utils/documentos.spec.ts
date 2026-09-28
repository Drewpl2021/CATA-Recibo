import type { Documento } from '../models';
import {
  diasHasta,
  documentoSeFirma,
  esDocumentoAnterior,
  esImagen,
  esPdf,
  estadoFirmaLegible,
  extensionDocumento,
  formatoDocumento,
  nombreArchivoDocumento,
  nombreDocumento,
  sePuedeVer,
  severidadFirma,
} from './documentos';

/** Un documento con solo lo que la lógica mira. */
const doc = (parcial: Record<string, unknown>): Documento => parcial as unknown as Documento;

describe('nombreDocumento', () => {
  it('una boleta lleva su mes y año', () => {
    expect(nombreDocumento(doc({ tipo: 'boleta', planilla: { mes: 8, anio: 2026 } }))).toBe('Boleta de Agosto 2026');
  });

  it('un contrato anterior con solo año', () => {
    expect(nombreDocumento(doc({ tipo: 'contrato_anterior', periodo_anio: 2023 }))).toBe('Contrato de 2023');
  });

  it('un documento anterior con mes y año', () => {
    expect(nombreDocumento(doc({ tipo: 'boleta_anterior', periodo_mes: 3, periodo_anio: 2024 }))).toBe('Boleta de Marzo 2024');
  });

  it('sin periodo queda el nombre del tipo', () => {
    expect(nombreDocumento(doc({ tipo: 'hoja_de_vida' }))).toBe('Hoja de vida');
  });

  it('un tipo desconocido se llama Documento', () => {
    expect(nombreDocumento(doc({ tipo: 'raro' }))).toBe('Documento');
  });

  it('null y undefined dan vacío', () => {
    expect(nombreDocumento(null)).toBe('');
    expect(nombreDocumento(undefined)).toBe('');
  });
});

describe('firma del documento', () => {
  it('la hoja de vida y los archivos anteriores no se firman', () => {
    expect(documentoSeFirma(doc({ tipo: 'hoja_de_vida' }))).toBeFalse();
    expect(documentoSeFirma(doc({ tipo: 'boleta_anterior' }))).toBeFalse();
    expect(documentoSeFirma(doc({ tipo: 'contrato_anterior' }))).toBeFalse();
  });

  it('la boleta y el contrato sí', () => {
    expect(documentoSeFirma(doc({ tipo: 'boleta' }))).toBeTrue();
    expect(documentoSeFirma(doc({ tipo: 'contrato' }))).toBeTrue();
  });

  it('el estado se lee en palabras', () => {
    expect(estadoFirmaLegible(doc({ tipo: 'boleta', estado_firma: 'firmado' }))).toBe('Firmado');
    expect(estadoFirmaLegible(doc({ tipo: 'boleta', estado_firma: 'visto' }))).toBe('Visto');
    expect(estadoFirmaLegible(doc({ tipo: 'boleta', estado_firma: 'pendiente' }))).toBe('Pendiente');
    expect(estadoFirmaLegible(doc({ tipo: 'boleta_anterior' }))).toBe('Archivo anterior');
    expect(estadoFirmaLegible(doc({ tipo: 'hoja_de_vida' }))).toBe('Guardada');
  });

  it('la severidad sigue al estado', () => {
    expect(severidadFirma(doc({ tipo: 'boleta', estado_firma: 'firmado' }))).toBe('success');
    expect(severidadFirma(doc({ tipo: 'boleta', estado_firma: 'visto' }))).toBe('info');
    expect(severidadFirma(doc({ tipo: 'boleta', estado_firma: 'pendiente' }))).toBe('warning');
    expect(severidadFirma(doc({ tipo: 'hoja_de_vida' }))).toBe('info');
  });

  it('distingue los documentos anteriores', () => {
    expect(esDocumentoAnterior(doc({ tipo: 'boleta_anterior' }))).toBeTrue();
    expect(esDocumentoAnterior(doc({ tipo: 'boleta' }))).toBeFalse();
  });
});

describe('formato del archivo', () => {
  it('reconoce PDF, imágenes y Word', () => {
    expect(esPdf(doc({ archivo: 'Hoja.PDF' }))).toBeTrue();
    expect(esImagen(doc({ archivo: 'foto.jpeg' }))).toBeTrue();
    expect(formatoDocumento(doc({ archivo: 'a.docx' }))).toBe('Word');
    expect(formatoDocumento(doc({ archivo: 'a.png' }))).toBe('imagen');
    expect(formatoDocumento(doc({ archivo: 'a.pdf' }))).toBe('PDF');
  });

  it('solo se ven sin bajar los PDF y las imágenes', () => {
    expect(sePuedeVer(doc({ archivo: 'a.pdf' }))).toBeTrue();
    expect(sePuedeVer(doc({ archivo: 'a.webp' }))).toBeTrue();
    expect(sePuedeVer(doc({ archivo: 'a.docx' }))).toBeFalse();
  });

  it('sin extensión se asume PDF', () => {
    expect(extensionDocumento(doc({ archivo: 'sin_extension' }))).toBe('pdf');
    expect(extensionDocumento(doc({}))).toBe('pdf');
  });

  it('el nombre de descarga lleva a la persona para no repetirse', () => {
    const d = doc({ tipo: 'hoja_de_vida', archivo: 'x.pdf' });

    expect(nombreArchivoDocumento(d, 'Ana Pérez')).toBe('Hoja de vida - Ana Pérez.pdf');
    expect(nombreArchivoDocumento(d)).toBe('Hoja de vida.pdf');
  });
});

describe('diasHasta', () => {
  const formato = (fecha: Date) =>
    `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;

  it('hoy son cero días', () => {
    expect(diasHasta(formato(new Date()))).toBe(0);
  });

  it('cuenta hacia adelante y hacia atrás', () => {
    const en10 = new Date(); en10.setDate(en10.getDate() + 10);
    const hace3 = new Date(); hace3.setDate(hace3.getDate() - 3);

    expect(diasHasta(formato(en10))).toBe(10);
    expect(diasHasta(formato(hace3))).toBe(-3);
  });

  it('ignora la hora si la fecha llega con ella', () => {
    expect(diasHasta(formato(new Date()) + 'T23:59:00')).toBe(0);
  });
});
