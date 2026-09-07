import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Sede } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class SedeService extends EntityDataService<Sede> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.sedes);
  }
}
