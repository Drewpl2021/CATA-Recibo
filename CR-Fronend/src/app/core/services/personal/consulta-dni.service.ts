import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PersonaPorDni } from '../../models';
import { ApiResponse, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/**
 * Quién es la persona detrás de un DNI.
 *
 * Lo usa el alta de un trabajador para traer sus nombres del padrón en vez
 * de tipearlos: el nombre que se escribe ahí es el que sale impreso en todas
 * sus boletas, y una errata se arrastra el año entero.
 */
@Injectable({ providedIn: 'root' })
export class ConsultaDniService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  buscar(dni: string): Observable<ApiResponse<PersonaPorDni>> {
    return this.http.get<ApiResponse<PersonaPorDni>>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.consultaDni(dni)}`
    );
  }
}
