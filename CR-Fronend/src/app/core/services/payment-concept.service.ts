import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface PaymentConcept {
  id: string;
  nombre: string;
  tipo: 'ingreso' | 'descuento' | 'aportacion';
  descripcion?: string;
  activo: boolean;
}

@Injectable({ providedIn: 'root' })
export class PaymentConceptService {
  private apiUrl = environment.apiUrl + '/payment-concepts';

  constructor(private http: HttpClient) {}

  getConceptos(): Observable<{ success: boolean; data: PaymentConcept[] }> {
    return this.http.get<{ success: boolean; data: PaymentConcept[] }>(this.apiUrl);
  }

  getConcepto(id: string): Observable<{ success: boolean; data: PaymentConcept }> {
    return this.http.get<{ success: boolean; data: PaymentConcept }>(`${this.apiUrl}/${id}`);
  }

  crearConcepto(data: Partial<PaymentConcept>): Observable<{ success: boolean; data: PaymentConcept }> {
    return this.http.post<{ success: boolean; data: PaymentConcept }>(this.apiUrl, data);
  }

  actualizarConcepto(id: string, data: Partial<PaymentConcept>): Observable<{ success: boolean; data: PaymentConcept }> {
    return this.http.put<{ success: boolean; data: PaymentConcept }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarConcepto(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
