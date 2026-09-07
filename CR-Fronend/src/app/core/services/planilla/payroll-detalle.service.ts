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

  crear(payload: PayrollDetallePayload) {
    return this.create<PayrollDetallePayload>(payload);
  }
}
