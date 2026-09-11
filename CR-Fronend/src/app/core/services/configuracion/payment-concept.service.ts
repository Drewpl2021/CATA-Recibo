import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AplicacionConceptoGrupo, PaymentConcept, TipoConcepto } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService, Pagina } from '../../utils';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class PaymentConceptService extends EntityDataService<PaymentConcept> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.configuracion.paymentConcepts);
  }

  /**
   * GET /payment-concepts?tipo=&page=&size=&search=
   *
   * Filtro, corte y conteo los hace la base de datos, no el navegador: el
   * catálogo crece con los años y traérselo entero para mostrar diez filas
   * es justo lo que no escala.
   */
  listar(filtros: {
    tipo?: TipoConcepto | '';
    page?: number;
    size?: number;
    search?: string;
  }): Observable<ApiResponse<Pagina<PaymentConcept>>> {
    return this.getPagina(filtros);
  }

  /**
   * POST /payment-concepts/{id}/aplicar-a-grupo
   *
   * Agrega este concepto a la planilla que YA tengan esos empleados de ese
   * mes; a quien no la tenga se le omite. El monto sale del catálogo, igual
   * para todo el grupo: si hace falta uno distinto por persona, se agrega a
   * mano en el detalle de su planilla.
   */
  /**
   * Aplica el concepto a un grupo. Tres formas de decir a quiénes:
   *
   *   { mes, anio, empleado_ids }   la lista suelta, fuera de una planilla
   *   { corrida_id }                a todos los de esa planilla — el mes
   *                                 sale de ella, no hay que repetirlo
   *   { corrida_id, empleado_ids }  a unos pocos DENTRO de esa planilla; el
   *                                 backend cruza las dos cosas
   */
  aplicarAGrupo(
    conceptoId: string,
    datos: AplicarConceptoDestino & { calculo?: string; valor?: number }
  ): Observable<ApiResponse<AplicacionConceptoGrupo>> {
    return this.http.post<ApiResponse<AplicacionConceptoGrupo>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.aplicarConceptoGrupo(conceptoId)}`,
      datos
    );
  }
}

/**
 * A quiénes alcanza la aplicación de un concepto.
 *
 * `corrida_id` y `empleado_ids` no se excluyen: juntos significan "a estas
 * personas, dentro de esta planilla", que es como se aplica un Subsidio de
 * Maternidad a las tres madres de la planilla de docentes.
 */
export interface AplicarConceptoDestino {
  corrida_id?: string;
  mes?: number;
  anio?: number;
  empleado_ids?: string[];
}
