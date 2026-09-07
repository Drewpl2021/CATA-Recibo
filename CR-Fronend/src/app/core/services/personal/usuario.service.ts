import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Usuario } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

/** Los usuarios no se crean por aquí: se crean solos al dar de alta un Empleado. */
@Injectable({ providedIn: 'root' })
export class UsuarioService extends EntityDataService<Usuario> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.personal.users);
  }
}
