import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { ApiResponse, END_POINTS } from '../../utils';
import { PrimerosPasos } from '../../models';
import { environment } from '../../../../environments/environment';

/**
 * La guía de primeros pasos.
 *
 * Guarda el último estado que llegó porque lo miran dos sitios a la vez: el
 * globito del foquito, en la barra de arriba, y el panel que se abre al
 * pulsarlo. Con dos consultas distintas podían decir cosas distintas.
 */
@Injectable({ providedIn: 'root' })
export class PrimerosPasosService {
  private readonly apiUrl = environment.apiUrl;
  private http = inject(HttpClient);

  private readonly estado = new BehaviorSubject<PrimerosPasos | null>(null);

  /** null mientras no se sabe todavía. */
  readonly estado$ = this.estado.asObservable();

  get actual(): PrimerosPasos | null {
    return this.estado.value;
  }

  /** GET /my-first-steps — sus pasos, con lo que ya está hecho. */
  cargar(): void {
    this.http
      .get<ApiResponse<PrimerosPasos>>(`${this.apiUrl}/${END_POINTS.autoservicio.primerosPasos}`)
      .subscribe({
        next: (res) => {
          if (res.success) this.estado.next(res.data);
        },
        // Si falla, el foquito se queda sin globito y la app sigue igual.
        error: () => undefined,
      });
  }

  /** POST /my-first-steps/seen — deja de insistir con el globito. */
  marcarVista(): Observable<ApiResponse<{ vista: boolean }>> {
    const actual = this.estado.value;
    if (actual) {
      this.estado.next({ ...actual, vista: true });
    }

    return this.http.post<ApiResponse<{ vista: boolean }>>(
      `${this.apiUrl}/${END_POINTS.autoservicio.primerosPasosVistos}`,
      {}
    );
  }
}
