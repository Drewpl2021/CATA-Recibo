import { Injectable } from '@angular/core';
import { Subject, Observable } from 'rxjs';

export interface ConfirmOptions {
  titulo: string;
  mensaje: string;
  aceptarTexto?: string;
  cancelarTexto?: string;
  variante?: 'danger' | 'default';
}

export interface ConfirmRequest {
  options: ConfirmOptions;
  resolve: (aceptado: boolean) => void;
}

/**
 * Confirmación reciclable (sin dependencias de terceros) — un solo
 * <app-confirm-dialog> montado en AppComponent escucha este Subject.
 * Reemplaza el booleano "mostrarConfirmacion" que cada pantalla armaba
 * por su cuenta (ej. empleado-form).
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private requestSubject = new Subject<ConfirmRequest | null>();
  public request$: Observable<ConfirmRequest | null> = this.requestSubject.asObservable();

  confirmar(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
      this.requestSubject.next({
        options,
        resolve: (aceptado) => {
          resolve(aceptado);
          this.requestSubject.next(null);
        },
      });
    });
  }

  confirmarEliminar(entidad: string, onAceptar: () => void): void {
    this.confirmar({
      titulo: `Eliminar ${entidad}`,
      mensaje: `¿Seguro que quieres eliminar ${entidad}? Esta acción no se puede deshacer.`,
      aceptarTexto: 'Sí, eliminar',
      variante: 'danger',
    }).then((aceptado) => {
      if (aceptado) onAceptar();
    });
  }
}
