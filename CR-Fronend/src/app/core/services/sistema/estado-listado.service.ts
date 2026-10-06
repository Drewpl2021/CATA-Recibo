import { Injectable } from '@angular/core';

/**
 * Lo que tenía puesto una lista (búsqueda, página, filtros) para devolvérselo
 * al volver.
 *
 * Se reportó así: en Empleados buscabas "gatica", entrabas a su ficha y al
 * volver la lista mostraba a todos otra vez. Cada pantalla de lista se
 * destruye al salir y nace de cero al volver; ahora, al cargar, deja aquí lo
 * que tiene puesto, y al nacer lo recupera.
 *
 * Vive en memoria y en sessionStorage (solo esta pestaña, se borra al
 * cerrarla): sobrevive a un F5 pero no se comparte entre personas ni se queda
 * para siempre. Si el navegador no deja usar sessionStorage, queda solo en
 * memoria.
 */
@Injectable({ providedIn: 'root' })
export class EstadoListadoService {
  private readonly prefijo = 'cata-lista:';
  private readonly enMemoria = new Map<string, unknown>();

  guardar<T extends object>(clave: string, estado: T): void {
    this.enMemoria.set(clave, estado);
    try {
      sessionStorage.setItem(this.prefijo + clave, JSON.stringify(estado));
    } catch {
      // Sin sessionStorage (modo privado, bloqueado): basta con la memoria.
    }
  }

  leer<T extends object>(clave: string): Partial<T> | null {
    if (this.enMemoria.has(clave)) return this.enMemoria.get(clave) as Partial<T>;
    try {
      const guardado = sessionStorage.getItem(this.prefijo + clave);
      return guardado ? (JSON.parse(guardado) as Partial<T>) : null;
    } catch {
      return null;
    }
  }

  /** Al cerrar sesión: que el siguiente que entre en esta computadora no herede nada. */
  olvidarTodo(): void {
    this.enMemoria.clear();
    try {
      Object.keys(sessionStorage)
        .filter((k) => k.startsWith(this.prefijo))
        .forEach((k) => sessionStorage.removeItem(k));
    } catch {
      // nada que borrar
    }
  }
}
