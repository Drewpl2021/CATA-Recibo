import { identificarDocumento } from './identificar-documento';

describe('identificarDocumento', () => {
  const BOLETA = `
    Boleta de Pago de Remuneraciones
    DNI: 42558107
    Del 01/03/2026 al 31/03/2026
    Total ingresos 3000.00  Total descuentos 390.00  Neto a pagar 2610.00`;

  it('reconoce una boleta, su DNI y su mes', () => {
    const r = identificarDocumento(BOLETA, 'archivo.pdf');

    expect(r.tipo).toBe('boleta_anterior');
    expect(r.dnis).toEqual(['42558107']);
    expect(r.mes).toBe(3);
    expect(r.anio).toBe(2026);
  });

  it('un contrato menciona la boleta pero sigue siendo un contrato', () => {
    const texto = 'CONTRATO de trabajo. Se le entregará su boleta de pago cada mes.';
    expect(identificarDocumento(texto, 'x.pdf').tipo).toBe('contrato_anterior');
  });

  it('un contrato trae dos DNI y no se repiten', () => {
    const texto = 'Contrato. DNI: 11112222 (empleador). D.N.I. N° 33334444 (trabajador). DNI: 11112222';
    expect(identificarDocumento(texto, 'x.pdf').dnis).toEqual(['11112222', '33334444']);
  });

  it('completa con ceros un DNI de siete cifras', () => {
    expect(identificarDocumento('Boleta. DNI: 1234567', 'x.pdf').dnis).toEqual(['01234567']);
  });

  it('no toma un RUC pegado a más cifras por un DNI', () => {
    expect(identificarDocumento('DNI: 20123456789', 'x.pdf').dnis).toEqual([]);
  });

  it('cuando el texto no dice nada, saca todo del nombre del archivo', () => {
    const r = identificarDocumento('', '42558107 boleta 2024-03.pdf');

    expect(r.tipo).toBe('boleta_anterior');
    expect(r.dniNombre).toBe('42558107');
    expect(r.mes).toBe(3);
    expect(r.anio).toBe(2024);
  });

  it('entiende el mes escrito en el nombre', () => {
    const r = identificarDocumento('', 'boleta_marzo_2024.pdf');

    expect(r.mes).toBe(3);
    expect(r.anio).toBe(2024);
  });

  it('acepta "setiembre" además de "septiembre"', () => {
    expect(identificarDocumento('', 'boleta setiembre 2025.pdf').mes).toBe(9);
    expect(identificarDocumento('', 'boleta septiembre 2025.pdf').mes).toBe(9);
  });

  it('la boleta de diciembre firmada en enero es del año anterior', () => {
    const texto = 'Boleta de pago. Total ingresos 1. Mes de Diciembre. 15 de enero de 2026';
    const r = identificarDocumento(texto, 'x.pdf');

    expect(r.mes).toBe(12);
    expect(r.anio).toBe(2025);
  });

  it('en un contrato cuenta el año en que empezó, no el mes', () => {
    const r = identificarDocumento('Contrato. Del 01/03/2023 al 31/12/2023', 'x.pdf');

    expect(r.tipo).toBe('contrato_anterior');
    expect(r.mes).toBeNull();
    expect(r.anio).toBe(2023);
  });

  it('descarta meses y años imposibles', () => {
    const r = identificarDocumento('', 'boleta 13-2024.pdf');
    expect(r.mes).toBeNull();
  });

  it('ignora tildes y mayúsculas', () => {
    const r = identificarDocumento('BOLETA DE PAGO — TOTAL INGRESOS. MES DE MARZO DEL 2026', 'x.pdf');
    expect(r.mes).toBe(3);
    expect(r.anio).toBe(2026);
  });

  it('devuelve todo vacío si no reconoce nada', () => {
    const r = identificarDocumento('lorem ipsum', 'foto.png');

    expect(r.tipo).toBeNull();
    expect(r.dnis).toEqual([]);
    expect(r.mes).toBeNull();
    expect(r.anio).toBeNull();
  });
});
