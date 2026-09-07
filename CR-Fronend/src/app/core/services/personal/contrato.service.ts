import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Contrato } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class ContratoService extends EntityDataService<Contrato> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.personal.contratos);
  }
}
