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

  /**
   * Los bytes de la firma registrada; 404 si todavía no hay.
   *
   * Como la foto de perfil: vive en el disco privado, así que no se puede
   * pintar con <img src="…"> a secas —esa petición no lleva el token—. Se
   * pide como blob y la pantalla la vuelve una URL de memoria.
   */
  verMia(): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS.autoservicio.miFirmaImagen}`, { responseType: 'blob' });
  }

  /** La firma de cualquier trabajador (RR.HH. y Administración). */
  verDeEmpleado(empleadoId: string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.firmaImagenDeEmpleado(empleadoId)}`, { responseType: 'blob' });
  }

  private armarFormData(firma?: File | null, huella?: File | null): FormData {
    const formData = new FormData();
    if (firma) formData.append('firma', firma);
    if (huella) formData.append('huella', huella);
    return formData;
  }
}
