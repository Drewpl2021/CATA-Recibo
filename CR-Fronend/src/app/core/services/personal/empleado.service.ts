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

  /**
   * La lista para llenar un desplegable o el selector de empleados.
   *
   * Trae lo justo —id, nombre, apellido, DNI y los tres ids con los que se
   * filtra por área, cargo y sede— en vez de la ficha entera con todas sus
   * relaciones. Medido con 150 empleados: 297 KB la completa contra 44 KB
   * esta, y la diferencia crece con cada campo que se le agregue a la ficha.
   *
   * Para una tabla que MUESTRA el área o el cargo del trabajador hace falta
   * getAll(), que sí trae las relaciones.
   */
  paraSelector() {
    return this.getAll({ formato: 'selector' });
  }
}
