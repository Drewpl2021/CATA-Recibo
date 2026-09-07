import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ModuloPadreAdmin } from '../../models';
import { END_POINTS, EntityDataService } from '../../utils';

/**
 * Administración de los GRUPOS del menú (solo Admin).
 *
 * Un módulo padre no lleva ruta ni roles: es solo el encabezado que agrupa
 * ítems en la barra lateral. Quién lo ve se decide en los módulos que
 * cuelgan de él — si a un usuario no le toca ninguno, el grupo entero no
 * le aparece (así lo resuelve GET /mis-modulos).
 */
@Injectable({ providedIn: 'root' })
export class ModuloPadreService extends EntityDataService<ModuloPadreAdmin> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.admin.modulosPadre);
  }
}
