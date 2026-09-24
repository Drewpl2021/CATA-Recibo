import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';
import { Empleado, EmpleadoPayload, MiPerfil } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';

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

  /**
   * GET /my-profile — la ficha de quien está dentro, para "Mi perfil".
   *
   * No se usa getById(): esa ruta es solo de RR.HH. y Administración, así
   * que al trabajador le contestaba 403 y su perfil salía con guiones. Acá
   * el id sale de su sesión, no de la dirección.
   */
  miPerfil(): Observable<ApiResponse<MiPerfil>> {
    return this.http.get<ApiResponse<MiPerfil>>(
      `${environment.apiUrl}/${END_POINTS.autoservicio.miPerfil}`
    );
  }

  /**
   * GET /empleados/exportar — la ficha completa de cada trabajador en Excel.
   *
   * Lleva el mismo buscador que la tabla: lo que se ve es lo que baja. Vuelve
   * como blob porque es un archivo, no el { success, data } del resto.
   */
  exportar(filtros: Record<string, string | number | undefined> = {}): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/${END_POINTS_ACCIONES.exportarEmpleados}`, {
      params: this.construirParams(filtros),
      responseType: 'blob',
    });
  }
}
