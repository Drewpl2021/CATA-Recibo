import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, Subscription, defer, finalize, interval, switchMap, catchError, of } from 'rxjs';
import { environment } from '../../../../environments/environment';

/** Lo que enseña el modal mientras corre un proceso largo. */
export interface EstadoProgreso {
  titulo: string;
  /** "Guardando los trabajadores". Vacío mientras el servidor no empieza. */
  etapa: string;
  hechos: number;
  total: number;
  /** 0 a 100, o null si todavía no se sabe cuántos son. */
  porcentaje: number | null;
  /** Segundos que faltan, estimados; null si aún no hay con qué estimar. */
  restante: number | null;
  /** Segundos desde que se pidió. */
  transcurrido: number;
}

/**
 * La barra de avance de los procesos largos: importar, generar planillas,
 * emitir boletas, bajar el .zip…
 *
 * Antes el botón decía "Aplicando…" y nada más: con 94 trabajadores no se
 * sabía si iba por la mitad o si se había colgado. Ahora se envuelve la
 * petición con `seguir()`: se abre un modal, la petición sale con un id en
 * la cabecera X-Progreso (lo pone el interceptor), el servidor va anotando
 * cuántos lleva, y aquí se le pregunta cada segundo para mover la barra y
 * calcular cuánto falta.
 */
@Injectable({ providedIn: 'root' })
export class ProgresoService {
  private http = inject(HttpClient);
  private readonly estadoSubject = new BehaviorSubject<EstadoProgreso | null>(null);
  readonly estado$ = this.estadoSubject.asObservable();

  /** El id del proceso en curso; el interceptor lo pone en la cabecera. */
  idActivo: string | null = null;

  /**
   * Corre `peticion` con el modal de avance abierto. Devuelve la misma
   * respuesta que la petición: la pantalla sigue tratando el resultado y
   * los errores como siempre.
   */
  seguir<T>(titulo: string, peticion: Observable<T>): Observable<T> {
    return defer(() => {
      const id = this.nuevoId();
      const inicio = Date.now();
      this.idActivo = id;
      this.estadoSubject.next({ titulo, etapa: '', hechos: 0, total: 0, porcentaje: null, restante: null, transcurrido: 0 });

      const consulta: Subscription = interval(1000)
        .pipe(
          switchMap(() =>
            this.http
              .get<{ data: { etapa: string; total: number; hechos: number; transcurrido: number } | null }>(
                `${environment.apiUrl}/progress/${id}`
              )
              .pipe(catchError(() => of({ data: null })))
          )
        )
        .subscribe((res) => {
          const d = res.data;
          const transcurrido = (Date.now() - inicio) / 1000;
          if (!d) {
            this.publicar({ titulo, etapa: '', hechos: 0, total: 0, porcentaje: null, restante: null, transcurrido });
            return;
          }
          const porcentaje = d.total > 0 ? Math.round((d.hechos / d.total) * 100) : null;
          // Al ritmo que lleva esta etapa, lo que falta de ella.
          const restante = d.hechos > 0 && d.total > d.hechos ? (d.transcurrido / d.hechos) * (d.total - d.hechos) : null;
          this.publicar({ titulo, etapa: d.etapa, hechos: d.hechos, total: d.total, porcentaje, restante, transcurrido });
        });

      return peticion.pipe(
        finalize(() => {
          consulta.unsubscribe();
          if (this.idActivo === id) this.idActivo = null;
          this.estadoSubject.next(null);
        })
      );
    });
  }

  /** Solo si el proceso sigue abierto: una respuesta que llega tarde no reabre el modal. */
  private publicar(estado: EstadoProgreso): void {
    if (this.estadoSubject.value) this.estadoSubject.next(estado);
  }

  private nuevoId(): string {
    try {
      return crypto.randomUUID();
    } catch {
      // Sin crypto.randomUUID (http sin certificado en algunos navegadores).
      return `p-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    }
  }
}
