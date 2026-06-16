import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface PayrollDetalle {
  id: string;
  planilla_id: string;
  concepto_id: string;
  monto: number;
  tipo: 'ingreso' | 'descuento' | 'aportacion';
  descripcion?: string;
}

@Injectable({ providedIn: 'root' })
export class PayrollDetalleService {
  private apiUrl = environment.apiUrl + '/payroll-detalles';

  constructor(private http: HttpClient) {}

  getDetalles(): Observable<{ success: boolean; data: PayrollDetalle[] }> {
    return this.http.get<{ success: boolean; data: PayrollDetalle[] }>(this.apiUrl);
  }

  getDetalle(id: string): Observable<{ success: boolean; data: PayrollDetalle }> {
    return this.http.get<{ success: boolean; data: PayrollDetalle }>(`${this.apiUrl}/${id}`);
  }

  crearDetalle(data: Partial<PayrollDetalle>): Observable<{ success: boolean; data: PayrollDetalle }> {
    return this.http.post<{ success: boolean; data: PayrollDetalle }>(this.apiUrl, data);
  }

  actualizarDetalle(id: string, data: Partial<PayrollDetalle>): Observable<{ success: boolean; data: PayrollDetalle }> {
    return this.http.put<{ success: boolean; data: PayrollDetalle }>(`${this.apiUrl}/${id}`, data);
  }

  eliminarDetalle(id: string): Observable<any> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}
