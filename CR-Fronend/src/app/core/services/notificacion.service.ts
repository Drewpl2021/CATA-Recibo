import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

export interface NotificacionItem {
  id: string;
  user_id: number;
  tipo: string;
  titulo: string;
  mensaje: string;
  documento_id?: string | null;
  leida_at?: string | null;
  created_at: string;
  documento?: any;
}

export interface NotificacionesData {
  content?: NotificacionItem[];
  totalElements?: number;
  currentPage?: number;
  totalPages?: number;
  noLeidas?: number;
}

export interface NotificacionesResponse {
  success: boolean;
  data: NotificacionesData | NotificacionItem[];
}

@Injectable({
  providedIn: 'root'
})
export class NotificacionService {
  private apiUrl = environment.apiUrl + '/mis-notificaciones';

  private notificacionesSubject = new BehaviorSubject<NotificacionItem[]>([]);
  public notificaciones$ = this.notificacionesSubject.asObservable();

  private noLeidasSubject = new BehaviorSubject<number>(0);
  public noLeidas$ = this.noLeidasSubject.asObservable();

  constructor(private http: HttpClient) {}

  getNotificaciones(page: number = 0, size: number = 15): Observable<NotificacionesResponse> {
    return this.http.get<NotificacionesResponse>(`${this.apiUrl}?page=${page}&size=${size}`).pipe(
      tap((res) => {
        if (res.success && res.data) {
          if (Array.isArray(res.data)) {
            this.notificacionesSubject.next(res.data);
            const count = res.data.filter(n => !n.leida_at).length;
            this.noLeidasSubject.next(count);
          } else {
            const items = res.data.content || [];
            this.notificacionesSubject.next(items);
            this.noLeidasSubject.next(res.data.noLeidas ?? items.filter(n => !n.leida_at).length);
          }
        }
      })
    );
  }

  marcarLeida(id: string): Observable<{ success: boolean; data: NotificacionItem }> {
    return this.http.patch<{ success: boolean; data: NotificacionItem }>(`${this.apiUrl}/${id}/leida`, {}).pipe(
      tap((res) => {
        if (res.success) {
          const current = this.notificacionesSubject.value.map(n => {
            if (n.id === id) {
              return { ...n, leida_at: res.data.leida_at || new Date().toISOString() };
            }
            return n;
          });
          this.notificacionesSubject.next(current);
          const count = current.filter(n => !n.leida_at).length;
          this.noLeidasSubject.next(count);
        }
      })
    );
  }

  marcarTodas(): Observable<{ success: boolean; data: { marcadas: number } }> {
    return this.http.post<{ success: boolean; data: { marcadas: number } }>(`${this.apiUrl}/marcar-todas`, {}).pipe(
      tap((res) => {
        if (res.success) {
          const current = this.notificacionesSubject.value.map(n => ({
            ...n,
            leida_at: n.leida_at || new Date().toISOString()
          }));
          this.notificacionesSubject.next(current);
          this.noLeidasSubject.next(0);
        }
      })
    );
  }
}
