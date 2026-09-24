import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { ApiResponse, END_POINTS } from '../../utils';
import { PrimerosPasos } from '../../models';
import { environment } from '../../../../environments/environment';

/**
 * La guía de primeros pasos.
 *
 * No hereda de EntityDataService porque no es un CRUD: son dos llamadas
 * sueltas —qué le toca hacer y "ya la vi"—.
 */
@Injectable({ providedIn: 'root' })
export class PrimerosPasosService {
  private readonly apiUrl = environment.apiUrl;
  private http = inject(HttpClient);

  /**
   * Cuántas veces la han pedido desde el menú del usuario.
   *
   * El panel vive en la pantalla de inicio, así que el menú no puede
   * "abrirlo": lo que hace es llevar al inicio y avisar por acá para que
   * vuelva a salir aunque ya lo hubieran ocultado.
   */
  private readonly pedido = new BehaviorSubject<number>(0);

  readonly pedido$ = this.pedido.asObservable();

  mostrar(): void {
    this.pedido.next(this.pedido.value + 1);
  }

  /** GET /my-first-steps — sus pasos, con lo que ya está hecho. */
  ver(): Observable<ApiResponse<PrimerosPasos>> {
    return this.http.get<ApiResponse<PrimerosPasos>>(
      `${this.apiUrl}/${END_POINTS.autoservicio.primerosPasos}`
    );
  }

  /** POST /my-first-steps/seen — deja de abrirse sola al entrar. */
  marcarVista(): Observable<ApiResponse<{ vista: boolean }>> {
    return this.http.post<ApiResponse<{ vista: boolean }>>(
      `${this.apiUrl}/${END_POINTS.autoservicio.primerosPasosVistos}`,
      {}
    );
  }
}
