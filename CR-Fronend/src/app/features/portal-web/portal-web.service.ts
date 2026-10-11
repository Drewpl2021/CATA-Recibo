import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map, shareReplay } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiMessageResponse, ApiResponse } from '../../core/utils/api-response.model';
import { EstadoPortal, FichaPortal, ImagenSubida, ProyectoPortal, RedSocialPortal, ResumenPortal, UsoDeImagen } from './portal-web.models';

/** Cómo se llama cada lista en /api/web-portal. */
export const RECURSOS = {
  redes: 'social-networks',
  sedes: 'campuses',
  cifras: 'stats',
  logros: 'achievements',
  procesos: 'enrollment-processes',
  vacantes: 'vacancies',
  fechas: 'enrollment-dates',
  preguntas: 'faqs',
  grados: 'grade-options',
  banners: 'banners',
  niveles: 'home-levels',
  noticias: 'news',
  categoriasProyecto: 'project-categories',
  proyectos: 'projects',
  pilares: 'pillars',
  nivelesPropuesta: 'proposal-levels',
  universidades: 'universities',
  ciclos: 'cycles',
  plataformas: 'platforms',
  paginas: 'pages',
  nivelesDocentes: 'teacher-levels',
  docentes: 'teachers',
} as const;

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

  // ───────── Fichas (contenido de una sola vez) ─────────

  getFicha<T>(clave: string): Observable<ApiResponse<FichaPortal<T>>> {
    return this.http.get<ApiResponse<FichaPortal<T>>>(`${this.base}/sections/${clave}`);
  }

  /**
   * Guarda la ficha entera. `version` es la que se leyó: si alguien guardó
   * entre medio, responde 409. Las imágenes se mandan solo con su id.
   */
  guardarFicha<T>(clave: string, contenido: object, version: string | null): Observable<ApiResponse<FichaPortal<T>>> {
    return this.http.put<ApiResponse<FichaPortal<T>>>(`${this.base}/sections/${clave}`, { ...contenido, version });
  }

  // ───────── Listas (redes, sedes…): las mismas seis acciones ─────────

  /** `filtro` acota una tabla con varias listas: { ambito: 'logros' }. */
  getLista<T>(recurso: string, filtro: Record<string, string> = {}): Observable<ApiResponse<T[]>> {
    return this.http.get<ApiResponse<T[]>>(`${this.base}/${recurso}`, { params: filtro });
  }

  crearEnLista<T>(recurso: string, datos: object): Observable<ApiResponse<T>> {
    return this.http.post<ApiResponse<T>>(`${this.base}/${recurso}`, datos);
  }

  editarEnLista<T>(recurso: string, id: string, datos: object): Observable<ApiResponse<T>> {
    return this.http.put<ApiResponse<T>>(`${this.base}/${recurso}/${id}`, datos);
  }

  cambiarEstadoEnLista<T>(recurso: string, id: string, estado: EstadoPortal): Observable<ApiResponse<T>> {
    return this.http.patch<ApiResponse<T>>(`${this.base}/${recurso}/${id}/status`, { estado });
  }

  /** El orden nuevo de TODA la lista, con todos sus ids. */
  ordenarLista<T>(recurso: string, ids: string[], filtro: Record<string, string> = {}): Observable<ApiResponse<T[]>> {
    return this.http.post<ApiResponse<T[]>>(`${this.base}/${recurso}/order`, { ids }, { params: filtro });
  }

  eliminarDeLista(recurso: string, id: string): Observable<ApiMessageResponse> {
    return this.http.delete<ApiMessageResponse>(`${this.base}/${recurso}/${id}`);
  }

  /** La galería de un proyecto, entera y en orden (0 a 12 fotos). */
  guardarGaleriaDeProyecto(id: string, imagenes: string[]): Observable<ApiResponse<ProyectoPortal>> {
    return this.http.put<ApiResponse<ProyectoPortal>>(`${this.base}/${RECURSOS.proyectos}/${id}/gallery`, { imagenes });
  }

  // ───────── Redes sociales ─────────

  getRedes(): Observable<ApiResponse<RedSocialPortal[]>> {
    return this.getLista<RedSocialPortal>(RECURSOS.redes);
  }

  crearRed(datos: Partial<RedSocialPortal>): Observable<ApiResponse<RedSocialPortal>> {
    return this.crearEnLista<RedSocialPortal>(RECURSOS.redes, datos);
  }

  editarRed(id: string, datos: Partial<RedSocialPortal>): Observable<ApiResponse<RedSocialPortal>> {
    return this.editarEnLista<RedSocialPortal>(RECURSOS.redes, id, datos);
  }

  cambiarEstadoRed(id: string, estado: EstadoPortal): Observable<ApiResponse<RedSocialPortal>> {
    return this.cambiarEstadoEnLista<RedSocialPortal>(RECURSOS.redes, id, estado);
  }

  ordenarRedes(ids: string[]): Observable<ApiResponse<RedSocialPortal[]>> {
    return this.ordenarLista<RedSocialPortal>(RECURSOS.redes, ids);
  }

  eliminarRed(id: string): Observable<ApiMessageResponse> {
    return this.eliminarDeLista(RECURSOS.redes, id);
  }

  // ───────── Imágenes ─────────

  /** Los usos no cambian mientras la pantalla está abierta: se piden una vez. */
  private usos$?: Observable<Record<string, UsoDeImagen>>;

  getUsosDeImagen(): Observable<Record<string, UsoDeImagen>> {
    return (this.usos$ ??= this.http
      .get<ApiResponse<Record<string, UsoDeImagen>>>(`${this.base}/images/uses`)
      .pipe(map((res) => res.data), shareReplay(1)));
  }

  /**
   * POST /web-portal/images — la sube suelta; después un formulario la usa.
   * El backend la abre, la endereza, le quita los metadatos y la comprime.
   */
  subirImagen(archivo: File, uso: string, alt: string, decorativa: boolean): Observable<ApiResponse<ImagenSubida>> {
    const datos = new FormData();
    datos.append('archivo', archivo);
    datos.append('uso', uso);
    datos.append('decorativa', decorativa ? '1' : '0');
    if (!decorativa) datos.append('alt', alt);

    return this.http.post<ApiResponse<ImagenSubida>>(`${this.base}/images`, datos);
  }

  editarAltImagen(id: string, alt: string, decorativa: boolean): Observable<ApiResponse<ImagenSubida>> {
    return this.http.patch<ApiResponse<ImagenSubida>>(`${this.base}/images/${id}`, decorativa ? { decorativa: true } : { alt });
  }
}
