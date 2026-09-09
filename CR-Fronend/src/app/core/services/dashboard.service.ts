import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface DashboardData {
  periodo: { mes: number; anio: number };
  resumen: {
    empleadosActivos: number;
    altasDelMes: number;
    nominaDelMes: number;
    planillasDelMes: number;
    boletasEmitidas: number;
    contratosPorVencer: number;
  };
  remuneracionPorArea: Array<{ etiqueta: string; valor: number }>;
  sistemaPensiones: Array<{ etiqueta: string; valor: number }>;
  tipoContrato: Array<{ etiqueta: string; valor: number }>;
  tendenciaNomina: Array<{ etiqueta: string; valor: number }>;
  firmaBoletas: {
    firmadas: number;
    vistas: number;
    pendientes: number;
  };
  contratosPorVencer: Array<{
    nombre: string;
    cargo: string;
    fecha: string;
    dias: number;
    urgencia: 'urgente' | 'proximo' | 'normal';
  }>;
}

@Injectable({
  providedIn: 'root'
})
export class DashboardService {
  private apiUrl = `${environment.apiUrl}/dashboard`;

  constructor(private http: HttpClient) {}

  getDashboard(mes?: number, anio?: number): Observable<{ success: boolean; data: DashboardData }> {
    const params: any = {};
    if (mes) params.mes = mes;
    if (anio) params.anio = anio;
    return this.http.get<{ success: boolean; data: DashboardData }>(this.apiUrl, { params });
  }
}
