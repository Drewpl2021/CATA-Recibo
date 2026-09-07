import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { IdentidadFirma } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Firma y huella del empleado (tabla identidades_firma, disco privado).
 * No hereda de EntityDataService porque no es un CRUD plano: son dos
 * endpoints de subida con multipart/form-data.
 */
@Injectable({ providedIn: 'root' })
export class IdentidadFirmaService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /** RRHH/admin registra la firma y/o huella de CUALQUIER empleado. */
  subirParaEmpleado(empleadoId: string, firma?: File | null, huella?: File | null): Observable<ApiResponse<IdentidadFirma>> {
    return this.http.post<ApiResponse<IdentidadFirma>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.identidadFirmaEmpleado(empleadoId)}`,
      this.armarFormData(firma, huella)
    );
  }

  /** El propio empleado registra SU firma y/o huella. */
  subirMia(firma?: File | null, huella?: File | null): Observable<ApiResponse<IdentidadFirma>> {
    return this.http.post<ApiResponse<IdentidadFirma>>(
      `${this.apiUrl}/${END_POINTS.autoservicio.miIdentidadFirma}`,
      this.armarFormData(firma, huella)
    );
  }

  private armarFormData(firma?: File | null, huella?: File | null): FormData {
    const formData = new FormData();
    if (firma) formData.append('firma', firma);
    if (huella) formData.append('huella', huella);
    return formData;
  }
}
