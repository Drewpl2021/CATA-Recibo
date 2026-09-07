import { Injectable } from '@angular/core';
import { Subject, Observable } from 'rxjs';

export interface ToastMessage {
  type: 'success' | 'error' | 'info' | 'warning';
  title: string;
  message: string;
  duration?: number;
}

/**
 * Servicio reciclable de notificaciones (sin dependencias de terceros) —
 * un solo <app-toast> montado en AppComponent escucha este Subject y se
 * muestra desde cualquier parte de la app con .success()/.error()/etc.
 */
@Injectable({
  providedIn: 'root'
})
export class ToastService {
  private toastSubject = new Subject<ToastMessage | null>();
  public toastState$: Observable<ToastMessage | null> = this.toastSubject.asObservable();

  /** Hasta cuándo se descartan los avisos de error (marca de tiempo). */
  private silencioHasta = 0;

  /**
   * Descarta los errores que lleguen durante los próximos milisegundos.
   *
   * Se usa al caer la sesión: una pantalla puede tener varias peticiones en
   * vuelo y todas responden 401 a la vez, así que cada una intentaría avisar
   * con su propio "no se pudieron cargar los datos". Con esto solo se ve el
   * mensaje que de verdad explica lo que pasó.
   */
  silenciarErrores(ms = 4000): void {
    this.silencioHasta = Date.now() + ms;
  }

  show(type: 'success' | 'error' | 'info' | 'warning', title: string, message: string, duration: number = 3500) {
    this.toastSubject.next({ type, title, message, duration });

    if (duration > 0) {
      setTimeout(() => {
        this.clear();
      }, duration);
    }
  }

  success(title: string, message: string = '') {
    this.show('success', title, message);
  }

  /**
   * @param forzar muestra el aviso aunque haya un silencio activo — lo usa
   *               el propio mensaje de sesión caducada.
   */
  error(title: string, message: string = '', forzar = false) {
    if (!forzar && Date.now() < this.silencioHasta) return;
    this.show('error', title, message, 5000);
  }

  info(title: string, message: string = '') {
    this.show('info', title, message);
  }

  warning(title: string, message: string = '') {
    this.show('warning', title, message, 4000);
  }

  clear() {
    this.toastSubject.next(null);
  }
}
