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
   * El aviso de una operación masiva, con el tono que le toca al resultado.
   *
   * Estaba mal en las cuatro pantallas que hacen algo en lote: todas cantaban
   * "Proceso completado" en verde aunque no hubieran hecho absolutamente nada.
   * Con 0 generadas y 4 omitidas, RR.HH. veía el visto verde, se iba, y las
   * boletas no existían. Un proceso que no hizo nada es un error, no un éxito.
   *
   * Y va acá, en el servicio, para que las cuatro digan lo mismo: cuando cada
   * pantalla armaba su propio mensaje, una escribía "<br/>" y salía literal en
   * pantalla, porque el toast pinta texto, no HTML.
   */
  resultadoMasivo(opciones: {
    /** Cuántas salieron bien. */
    hechas: number;
    /** Cuántas se saltaron. */
    omitidas: number;
    /** Título cuando salió todo: "Boletas generadas". */
    exito: string;
    /** Título cuando no se hizo ninguna: "No se generó ninguna boleta". */
    nada: string;
    /** Qué se contó, en plural: "boleta(s)". */
    cosas: string;
    /** Por qué se saltaron las otras: "no tienen planilla de ese mes". */
    motivo: string;
  }): void {
    const { hechas, omitidas, exito, nada, cosas, motivo } = opciones;

    if (hechas === 0 && omitidas === 0) {
      this.warning('No había nada que hacer', 'Ningún registro entraba en este proceso.');
      return;
    }

    if (hechas === 0) {
      this.error(nada, `Las ${omitidas} ${cosas} se saltaron porque ${motivo}.`);
      return;
    }

    if (omitidas > 0) {
      this.warning(
        'Se hizo solo una parte',
        `${hechas} de ${hechas + omitidas} ${cosas}. Las otras ${omitidas} se saltaron porque ${motivo}.`
      );
      return;
    }

    this.success(exito, `${hechas} ${cosas}, sin ninguna pendiente.`);
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
