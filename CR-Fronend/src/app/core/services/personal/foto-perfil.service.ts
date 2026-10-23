import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, catchError, map, of, shareReplay } from 'rxjs';
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

  /** POST /employees/{id}/photo — RR.HH. o Admin le ponen la foto a un trabajador. */
  subirDeEmpleado(empleadoId: string, foto: File): Observable<ApiResponse<{ foto: string | null }>> {
    const formData = new FormData();
    formData.append('foto', foto);

    return this.http.post<ApiResponse<{ foto: string | null }>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.fotoDeEmpleado(empleadoId)}`,
      formData
    );
  }

  /** DELETE /employees/{id}/photo — vuelve a sus iniciales. */
  quitarDeEmpleado(empleadoId: string): Observable<ApiResponse<{ foto: string | null }>> {
    return this.http.delete<ApiResponse<{ foto: string | null }>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.fotoDeEmpleado(empleadoId)}`
    );
  }

  /** GET /users/{id}/photo — los bytes de la imagen. */
  ver(userId: number | string): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.fotoDeUsuario(userId)}`, {
      responseType: 'blob',
    });
  }

  /**
   * La foto lista para un <img>, pedida UNA vez por cuenta y versión aunque
   * salga en varias tablas o en varias filas. La versión cambia cuando se
   * pone otra foto: entonces se pide la nueva y se suelta la vieja de la
   * memoria. Si no se puede traer, null, y se ven las iniciales.
   */
  urlDe(usuarioId: number | string, version: string): Observable<string | null> {
    const clave = String(usuarioId);
    const guardada = this.urls.get(clave);
    if (guardada?.version === version) return guardada.url$;

    guardada?.url$.subscribe((url) => url && URL.revokeObjectURL(url));
    const url$ = this.ver(usuarioId).pipe(
      map((blob) => URL.createObjectURL(blob)),
      catchError(() => of(null)),
      shareReplay(1),
    );
    this.urls.set(clave, { version, url$ });
    return url$;
  }

  private readonly urls = new Map<string, { version: string; url$: Observable<string | null> }>();
}
