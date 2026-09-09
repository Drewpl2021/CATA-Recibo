import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { PayrollDetalle, PayrollDetallePayload } from '../../models';
import { ApiResponse, END_POINTS, EntityDataService } from '../../utils';

@Injectable({ providedIn: 'root' })
export class PayrollDetalleService extends EntityDataService<PayrollDetalle> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.payrollDetalles);
  }

  /** GET /payroll-detalles?planilla_id= */
  listarPorPlanilla(planillaId: string): Observable<ApiResponse<PayrollDetalle[]>> {
    return this.getAll({ planilla_id: planillaId });
  }

  /**
   * GET /payroll-detalles?planilla_id=&page=&size= — una página de líneas.
   *
   * La respuesta trae además los tres totales de la planilla ENTERA
   * (sumanAlSueldo, restanDelSueldo, aportaciones), porque sumando solo las
   * líneas de la página el neto saldría mal.
   */
  paginaDePlanilla(planillaId: string, pagina: number, tamano: number) {
    return this.getPagina({ planilla_id: planillaId, page: pagina, size: tamano });
  }

  crear(payload: PayrollDetallePayload) {
    return this.create<PayrollDetallePayload>(payload);
  }
}
