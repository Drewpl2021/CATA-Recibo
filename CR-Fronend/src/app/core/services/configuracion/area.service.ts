import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Area } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

/**
 * Antes este archivo tenía 40 líneas repitiendo getAll/getById/create/
 * update/delete a mano, más las interfaces embebidas. Ahora los modelos
 * viven en core/models y el CRUD lo hereda de EntityDataService.
 */
@Injectable({ providedIn: 'root' })
export class AreaService extends EntityDataService<Area> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.areas);
  }
}
