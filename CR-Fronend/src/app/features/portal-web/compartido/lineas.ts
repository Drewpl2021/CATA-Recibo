import { AbstractControl, ValidationErrors } from '@angular/forms';

/**
 * Las listas cortas de texto que se escriben «una por línea» en un textarea
 * (las opciones de grado, los requisitos de un proceso): más rápido de
 * escribir y de pegar que un campo por elemento.
 */

/** Las líneas no vacías de un texto, sin espacios sobrantes. */
export function lineas(texto: string | null | undefined): string[] {
  return (texto ?? '').split('\n').map((l) => l.trim()).filter(Boolean);
}

/** Cuántas puede haber, cuánto puede medir cada una y, si se pide, sin repetir. */
export function validarLineas(min: number, max: number, largo: number, sinRepetir = true) {
  return (control: AbstractControl): ValidationErrors | null => {
    const todas = lineas(control.value);
    if (todas.length < min) return { lineasMin: min };
    if (todas.length > max) return { lineasMax: max };
    if (todas.some((l) => l.length > largo)) return { lineaLarga: largo };
    if (sinRepetir && new Set(todas.map((l) => l.toLocaleLowerCase('es'))).size !== todas.length) return { lineaRepetida: true };
    return null;
  };
}
