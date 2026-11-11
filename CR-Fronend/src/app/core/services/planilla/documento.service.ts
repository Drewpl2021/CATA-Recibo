import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Documento, DocumentoPayload } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES, EntityDataService } from '../../utils';
import { ResultadoFirmarAqui, ResumenDeFirma } from './boleta.service';
import { environment } from '../../../../environments/environment';

/** Una constancia que pidió el personal y espera la firma del colegio. */
export interface ConstanciaPorFirmar {
  id: string;
  tipo: 'constancia_trabajo' | 'constancia_haberes';
  tipo_texto: string;
  dni: string | null;
  nombre: string;
  pedida: string | null;
  firma: 'pendiente' | 'parcial' | 'completa' | null;
}

@Injectable({ providedIn: 'root' })
export class DocumentoService extends EntityDataService<Documento> {
  constructor(http: HttpClient) {
    super(http, END_POINTS.planilla.documentos);
  }

  /** GET /documentos?empleado_id=&tipo= */
  listar(filtros?: { empleado_id?: string; tipo?: string; contrato_id?: string }): Observable<ApiResponse<Documento[]>> {
    return this.getAll(filtros);
  }

  crear(payload: DocumentoPayload) {
    return this.create<DocumentoPayload>(payload);
  }

  /**
   * POST /documentos/subir — adjunta un archivo que llega de fuera.
   *
   * Es multipart, no JSON: crear() registra un documento que ya está en
   * disco (la boleta que genera el sistema), esto sube el papel de verdad.
   */
  subir(datos: {
    empleado_id: string;
    tipo: string;
    archivo: File;
    contrato_id?: string | null;
  }): Observable<ApiResponse<Documento>> {
    const formData = new FormData();
    formData.append('empleado_id', datos.empleado_id);
    formData.append('tipo', datos.tipo);
    formData.append('archivo', datos.archivo);
    if (datos.contrato_id) formData.append('contrato_id', datos.contrato_id);

    return this.http.post<ApiResponse<Documento>>(
      `${environment.apiUrl}/${END_POINTS_ACCIONES.subirDocumento}`,
      formData
    );
  }

  /**
   * GET /documentos/{id}/ver — el PDF para leerlo en pantalla.
   *
   * Va aparte de la descarga a propósito: abrirlo se permite siempre —hay
   * que poder leer antes de firmar— y deja anotado que lo revisó, mientras
   * que bajárselo pide la firma primero.
   */
  ver(documentoId: string): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/${END_POINTS_ACCIONES.verDocumento(documentoId)}`, {
      responseType: 'blob',
    });
  }

  /** GET /documentos/{id}/descargar — devuelve el PDF ya guardado en disco. */
  descargar(documentoId: string): Observable<Blob> {
    return this.http.get(`${environment.apiUrl}/${END_POINTS_ACCIONES.descargarDocumento(documentoId)}`, {
      responseType: 'blob',
    });
  }

  // ── Las constancias que pidió el personal (RR.HH.) ──

  constanciasPorFirmar(): Observable<ApiResponse<{ filas: ConstanciaPorFirmar[]; firma: ResumenDeFirma }>> {
    return this.http.get<ApiResponse<{ filas: ConstanciaPorFirmar[]; firma: ResumenDeFirma }>>(`${environment.apiUrl}/letters/pending`);
  }

  /** «Firmar aquí»: una tanda de las constancias que esperan la firma de quien pide. La clave no se guarda. */
  firmarConstancias(clave: string, desde: string | null = null, limite = 25): Observable<ApiResponse<ResultadoFirmarAqui>> {
    return this.http.post<ApiResponse<ResultadoFirmarAqui>>(`${environment.apiUrl}/letters/sign-here`, { clave, desde, limite });
  }

  anularConstancia(id: string): Observable<ApiResponse<unknown> & { message?: string }> {
    return this.http.delete<ApiResponse<unknown> & { message?: string }>(`${environment.apiUrl}/letters/${id}`);
  }
}
