import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Usuario } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Lo que devuelve el backend al reponer una contraseña. */
export interface PasswordRestablecida {
  message: string;
  password_temporal: string;
  /** true si la temporal es el DNI del empleado. */
  es_dni: boolean;
}

/** Los usuarios no se crean por aquí: se crean solos al dar de alta un Empleado. */
@Injectable({ providedIn: 'root' })
export class UsuarioService extends EntityDataService<Usuario> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.personal.users);
  }

  /**
   * Le repone la contraseña a alguien que se quedó fuera.
   *
   * La temporal viaja en la respuesta y se muestra UNA vez: no queda guardada
   * en ningún lado ni se puede volver a consultar.
   */
  restablecerPassword(id: string | number): Observable<ApiResponse<PasswordRestablecida>> {
    return this.http.post<ApiResponse<PasswordRestablecida>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.restablecerPasswordUsuario(String(id))}`,
      {}
    );
  }
}
