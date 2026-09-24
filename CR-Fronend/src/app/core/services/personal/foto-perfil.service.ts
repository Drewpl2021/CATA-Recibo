import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * La foto de perfil de la cuenta.
 *
 * No hereda de EntityDataService porque no es un CRUD plano: son una subida
 * multipart y una descarga de bytes.
 *
 * Lo importante: la imagen vive en el DISCO PRIVADO, así que no se puede
 * pintar con `<img src="…/photo">` a secas — esa petición no lleva el token y
 * el backend la rechazaría. Se pide como blob (el interceptor sí le pone la
 * cabecera) y la pantalla la convierte en una URL de memoria con
 * URL.createObjectURL.
 */
@Injectable({ providedIn: 'root' })
export class FotoPerfilService {
  private readonly apiUrl = environment.apiUrl;
  private http = inject(HttpClient);

  /** POST /my-photo — cada quien sube la suya. */
  subirMia(foto: File): Observable<ApiResponse<{ foto: string | null }>> {
    const formData = new FormData();
    formData.append('foto', foto);

    return this.http.post<ApiResponse<{ foto: string | null }>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.miFoto}`,
      formData
    );
  }

  /** DELETE /my-photo — vuelve a las iniciales. */
  quitarMia(): Observable<ApiResponse<{ foto: string | null }>> {
    return this.http.delete<ApiResponse<{ foto: string | null }>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.miFoto}`
    );
  }

  /** GET /users/{id}/photo — los bytes de la imagen. */
  ver(userId: number | string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.fotoDeUsuario(userId)}`, {
      responseType: 'blob',
    });
  }
}
