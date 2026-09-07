import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { Notificacion } from '../../models';
import { ApiResponse, EntityDataService, Pagina } from '../../utils';

/** Una página de avisos trae además cuántos quedan sin leer. */
export interface PaginaNotificaciones extends Pagina<Notificacion> {
  noLeidas: number;
}

/**
 * Los avisos del trabajador que ha iniciado sesión.
 *
 * El número de no leídas va en un BehaviorSubject para que el globito de la
 * campana se entere solo: lo actualizan tanto la carga de la lista como el
 * marcar una como leída, sin que el layout tenga que volver a preguntar.
 */
@Injectable({ providedIn: 'root' })
export class NotificacionService extends EntityDataService<Notificacion> {
  private noLeidasSubject = new BehaviorSubject<number>(0);
  /** Cuántos avisos sin leer hay ahora mismo. */
  public noLeidas$ = this.noLeidasSubject.asObservable();

  constructor(http: HttpClient) {
    super(http, 'mis-notificaciones');
  }

  /** GET /mis-notificaciones?page=&size= — del más nuevo al más viejo. */
  listar(page = 0, size = 5): Observable<ApiResponse<PaginaNotificaciones>> {
    return (this.getPagina({ page, size }) as Observable<ApiResponse<PaginaNotificaciones>>).pipe(
      tap((res) => {
        if (res.success) this.noLeidasSubject.next(res.data.noLeidas ?? 0);
      })
    );
  }

  /** PATCH /mis-notificaciones/{id}/leida */
  marcarLeida(id: string): Observable<ApiResponse<Notificacion>> {
    return this.http
      .patch<ApiResponse<Notificacion>>(`${this.baseUrl}/${id}/leida`, {})
      .pipe(tap(() => this.noLeidasSubject.next(Math.max(0, this.noLeidasSubject.value - 1))));
  }

  /** POST /mis-notificaciones/marcar-todas */
  marcarTodas(): Observable<ApiResponse<{ marcadas: number }>> {
    return this.http
      .post<ApiResponse<{ marcadas: number }>>(`${this.baseUrl}/marcar-todas`, {})
      .pipe(tap(() => this.noLeidasSubject.next(0)));
  }
}
