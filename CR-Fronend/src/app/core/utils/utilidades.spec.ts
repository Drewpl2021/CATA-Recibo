import { FormControl } from '@angular/forms';
import { claveConLetrasYNumeros, tieneLetrasYNumeros } from './clave-segura';
import { fechaHoraLegible, fechaLegible } from './fecha';
import { mensajeErrorApi } from './error-api';

describe('claveConLetrasYNumeros', () => {
  it('acepta una clave con letras y números', () => {
    expect(claveConLetrasYNumeros(new FormControl('Colegio2026'))).toBeNull();
  });

  it('rechaza una clave solo de letras o solo de números', () => {
    expect(claveConLetrasYNumeros(new FormControl('sololetras'))).toEqual({ claveDebil: true });
    expect(claveConLetrasYNumeros(new FormControl('12345678'))).toEqual({ claveDebil: true });
  });

  it('deja vacío a Validators.required y no lo duplica', () => {
    expect(claveConLetrasYNumeros(new FormControl(''))).toBeNull();
    expect(claveConLetrasYNumeros(new FormControl(null))).toBeNull();
  });

  it('reconoce letras con tilde y la eñe', () => {
    expect(tieneLetrasYNumeros('ñandú1')).toBeTrue();
    expect(tieneLetrasYNumeros('¡¡¡111')).toBeFalse();
  });
});

describe('fechaLegible', () => {
  it('no corre la fecha un día por la zona horaria', () => {
    // 2026-03-01 no debe convertirse en 28 de febrero.
    const texto = fechaLegible('2026-03-01');
    expect(texto).toBe('01/03/2026');
  });

  it('sale siempre dd/mm/aaaa, con ceros, sin depender del navegador', () => {
    // Antes usaba toLocaleDateString('es-PE') y el formato cambiaba de una
    // computadora a otra según su idioma y región.
    expect(fechaLegible('2026-12-01')).toBe('01/12/2026');
    expect(fechaLegible('2026-01-09')).toBe('09/01/2026');
  });

  it('con hora: dd/mm/aaaa hh:mm', () => {
    expect(fechaHoraLegible('2026-12-01T09:05:00')).toBe('01/12/2026 09:05');
    expect(fechaHoraLegible(null)).toBe('');
  });

  it('devuelve vacío para valores vacíos', () => {
    expect(fechaLegible(null)).toBe('');
    expect(fechaLegible(undefined)).toBe('');
    expect(fechaLegible('')).toBe('');
  });

  it('devuelve el texto tal cual si no es una fecha', () => {
    expect(fechaLegible('no es fecha')).toBe('no es fecha');
  });

  it('acepta fechas con hora', () => {
    expect(fechaLegible('2026-03-01T15:30:00')).not.toBe('');
  });
});

describe('mensajeErrorApi', () => {
  it('une los errores de validación de Laravel (422)', () => {
    const err = { error: { message: 'x', errors: { email: ['Correo inválido.'], dni: ['DNI repetido.'] } } };
    expect(mensajeErrorApi(err)).toBe('Correo inválido. DNI repetido.');
  });

  it('usa message cuando no hay errores por campo', () => {
    expect(mensajeErrorApi({ error: { message: 'No autorizado' } })).toBe('No autorizado');
  });

  it('lee data.message como segunda opción', () => {
    expect(mensajeErrorApi({ error: { data: { message: 'Anidado' } } })).toBe('Anidado');
  });

  it('cae al mensaje por defecto', () => {
    expect(mensajeErrorApi(null)).toBe('Ocurrió un error inesperado.');
    expect(mensajeErrorApi({}, 'Falló')).toBe('Falló');
  });
});
