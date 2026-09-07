import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Empleado, EmpleadoPayload } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class EmpleadoService extends EntityDataService<Empleado> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.personal.empleados);
  }

  /**
   * OJO: EmpleadoController@store exige tambien "email" y "rol_id" (crea el
   * usuario junto con el empleado). El formulario actual todavia no los pide
   * — por eso el tipo es Partial; queda pendiente al reconstruir esa pantalla.
   */
  crear(payload: Partial<EmpleadoPayload>) {
    return this.create<Partial<EmpleadoPayload>>(payload);
  }

  actualizar(id: string, payload: Partial<EmpleadoPayload>) {
    return this.update<Partial<EmpleadoPayload>>(id, payload);
  }
}
