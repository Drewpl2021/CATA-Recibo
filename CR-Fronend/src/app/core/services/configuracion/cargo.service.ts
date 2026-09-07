import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Cargo } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class CargoService extends EntityDataService<Cargo> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.cargos);
  }
}
