import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ModuloPadre } from '../../models';
import { ApiResponse, END_POINTS } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Menú dinámico del sidebar, según los módulos asignados al rol del usuario. */
@Injectable({ providedIn: 'root' })
export class MisModulosService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  getMisModulos(): Observable<ApiResponse<ModuloPadre[]>> {
    return this.http.get<ApiResponse<ModuloPadre[]>>(`${this.apiUrl}/${END_POINTS.autoservicio.misModulos}`);
  }
}
