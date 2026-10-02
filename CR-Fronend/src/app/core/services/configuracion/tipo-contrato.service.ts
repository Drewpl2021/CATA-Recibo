import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TipoContrato } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class TipoContratoService extends EntityDataService<TipoContrato> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.tiposContrato);
  }
}
