import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Rol } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

/** RRHH solo puede leer roles; crear/editar/borrar es exclusivo de Admin (backend). */
@Injectable({ providedIn: 'root' })
export class RolService extends EntityDataService<Rol> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.roles);
  }
}
