import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/utils/api-response.model';
import { ResumenPortal } from './portal-web.models';

/**
 * Las llamadas del panel «Portal Web» a /api/web-portal.
 *
 * Vive con el módulo y no en core/services ni en END_POINTS: todo el panel
 * se suma al sistema sin tocar lo que ya estaba.
 */
@Injectable({ providedIn: 'root' })
export class PortalWebService {
  private http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/web-portal`;

  /** GET /web-portal/summary — cuántos registros tiene cada lista y qué fichas están cargadas. */
  getResumen(): Observable<ApiResponse<ResumenPortal>> {
    return this.http.get<ApiResponse<ResumenPortal>>(`${this.base}/summary`);
  }
}
