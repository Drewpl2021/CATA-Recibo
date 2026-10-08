import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { FirmaDelColegio, GeneracionMasivaBoletas, Planilla } from '../../models';
import { ApiResponse, END_POINTS, END_POINTS_ACCIONES } from '../../utils';
import { environment } from '../../../../environments/environment';

/** Generación y descarga de boletas en PDF (no es un CRUD, por eso no hereda). */
@Injectable({ providedIn: 'root' })
export class BoletaService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /** GET /mi-planilla — planillas del empleado autenticado. */
  getMiPlanilla(filtros?: { mes?: number; anio?: number }): Observable<ApiResponse<Planilla[]>> {
    const params: Record<string, string> = {};
    if (filtros?.mes) params['mes'] = String(filtros.mes);
    if (filtros?.anio) params['anio'] = String(filtros.anio);
    return this.http.get<ApiResponse<Planilla[]>>(`${this.apiUrl}/${END_POINTS.autoservicio.miPlanilla}`, { params });
  }

  /** GET /mis-boletas/{mes}/{anio} — PDF propio del empleado. */
  /** Con `soloVer`, la abre para leerla: no hace falta haberla firmado. */
  descargarMiBoleta(mes: number, anio: number | string, soloVer = false): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.miBoleta(mes, anio)}`, {
      responseType: 'blob',
      params: soloVer ? { ver: '1' } : {},
    });
  }

  /** GET /boleta/{empleado_id}/{mes}/{anio} — PDF de cualquier empleado (RRHH/Admin). */
  generarBoletaEmpleado(empleadoId: string, mes: number, anio: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/${END_POINTS_ACCIONES.boletaIndividual(empleadoId, mes, anio)}`, {
      responseType: 'blob',
    });
  }

  /**
   * Las boletas YA EMITIDAS del mes en un .zip, con el buscador y los filtros
   * de la pantalla (las que no se emitieron no van), bajado por el NAVEGADOR
   * con un enlace firmado que vence en 10 minutos.
   *
   * Bajarlo por dentro de la página (como `descargarEmitidasEnZip`) se
   * cortaba a la mitad en computadoras con un gestor de descargas o el escudo
   * web de un antivirus: agarran el .zip al vuelo y la página recibía un
   * pedazo. Un enlace normal lo maneja la barra de descargas, como cualquier
   * archivo. Devuelve cuántas boletas trae.
   */
  bajarEmitidasEnZip(filtros: Record<string, string | number | boolean | undefined>): Observable<number> {
    return this.http
      .get<ApiResponse<{ url: string; cantidad: number }>>(`${this.apiUrl}/${END_POINTS_ACCIONES.boletasEnZip}/link`, {
        params: this.soloConValor(filtros),
      })
      .pipe(
        map((res) => {
          // El servidor lo firma relativo (/api/...): se arma con la misma
          // base de la API para que valga en desarrollo y en el colegio.
          const enlace = document.createElement('a');
          enlace.href = `${this.apiUrl}${res.data.url.replace(/^\/api/, '')}`;
          enlace.rel = 'noopener';
          enlace.click();
          return res.data.cantidad;
        })
      );
  }

  private soloConValor(filtros: Record<string, string | number | boolean | undefined>): Record<string, string> {
    const params: Record<string, string> = {};
    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor !== undefined && valor !== null && valor !== '') params[clave] = String(valor);
    });
    return params;
  }

  // ── Firma digital del colegio (ReFirma) ──

  /** Cómo va la firma del mes: por firmar, a medias, entregadas, con conformidad. */
  resumenFirmaDigital(mes: number, anio: number): Observable<ApiResponse<ResumenFirmaDigital>> {
    return this.http.get<ApiResponse<ResumenFirmaDigital>>(`${this.apiUrl}/payslips/signed/summary`, {
      params: { mes: String(mes), anio: String(anio) },
    });
  }

  /** Revisa UN PDF firmado: dice qué pasaría al guardarlo, sin guardar nada. */
  revisarFirmada(archivo: File, mes: number, anio: number): Observable<ApiResponse<RevisionBoletaFirmada>> {
    return this.http.post<ApiResponse<RevisionBoletaFirmada>>(`${this.apiUrl}/payslips/signed/check`, this.formFirmada(archivo, mes, anio));
  }

  /** Lo vuelve a revisar y lo guarda; si queda completa, se le entrega al trabajador. */
  guardarFirmada(archivo: File, mes: number, anio: number): Observable<ApiResponse<RevisionBoletaFirmada>> {
    return this.http.post<ApiResponse<RevisionBoletaFirmada>>(`${this.apiUrl}/payslips/signed`, this.formFirmada(archivo, mes, anio));
  }

  /** Anula una boleta con firma digital para corregirla y volver a emitirla. */
  anular(documentoId: string): Observable<ApiResponse<unknown> & { message?: string }> {
    return this.http.post<ApiResponse<unknown> & { message?: string }>(`${this.apiUrl}/payslips/${documentoId}/void`, {});
  }

  /** La constancia de entrega del mes, en Excel. */
  constanciaDeEntrega(mes: number, anio: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/payslips/delivery-record`, {
      params: { mes: String(mes), anio: String(anio) },
      responseType: 'blob',
    });
  }

  private formFirmada(archivo: File, mes: number, anio: number): FormData {
    const datos = new FormData();
    datos.append('archivo', archivo, archivo.name);
    datos.append('mes', String(mes));
    datos.append('anio', String(anio));
    return datos;
  }

  /** POST /boletas/generar-masivo */
  /**
   * Emite las boletas que falten.
   *
   * Con `corridaId` se emiten solo las de esa planilla (el mes sale de ella);
   * sin él, las del mes entero. Antes no había forma de decir "de esta
   * planilla", y desde la Planilla TIC se emitían las de todo el colegio.
   */
  generarMasivo(mes: number, anio: number, corridaId?: string | null): Observable<GeneracionMasivaBoletas> {
    return this.http.post<GeneracionMasivaBoletas>(
      `${this.apiUrl}/${END_POINTS_ACCIONES.boletasMasivo}`,
      corridaId ? { corrida_id: corridaId } : { mes, anio }
    );
  }
}

export interface ResumenFirmaDigital {
  activa: boolean;
  requeridas: number;
  emitidas: number;
  por_firmar: number;
  a_medias: number;
  entregadas: number;
  conformidad: number;
  sin_firma_digital: number;
}

/** Lo que el servidor dice de un PDF firmado que se sube. */
export interface RevisionBoletaFirmada {
  archivo: string;
  /** 'ok' o el motivo del rechazo (no_reconocido, cambiada, sin_firma, ...). */
  estado: string;
  mensaje: string | null;
  trabajador: string | null;
  dni: string | null;
  numero: string | null;
  documento_id: string | null;
  firmas: (FirmaDelColegio & { valida?: boolean })[];
  firmas_validas: number;
  firmas_requeridas: number;
  /** Cómo quedaría: completa (se entrega) o parcial (falta otra firma). */
  resultado: 'completa' | 'parcial' | null;
}
