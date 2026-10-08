import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subscription, catchError, from, mergeMap, of } from 'rxjs';

import { BoletaService, RevisionBoletaFirmada } from '../../../core/services/planilla/boleta.service';
import { mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';

type Paso = 'elegir' | 'revisando' | 'revision' | 'guardando' | 'listo';

/** Un archivo elegido y lo que el servidor dijo de él. */
interface Fila {
  archivo: File;
  revision: RevisionBoletaFirmada | null;
  /** Al guardar: si se guardó o por qué no. */
  guardado?: 'si' | 'no';
  error?: string;
}

/**
 * «Subir boletas firmadas»: las que firmó el colegio en ReFirma, de vuelta
 * al sistema. En pasos, para que RR.HH. vea qué va a pasar antes de que pase:
 *
 *   1. elige los PDF firmados (la carpeta entera, Ctrl+A);
 *   2. el sistema revisa cada uno —de quién es, si es la boleta que emitió,
 *      quién la firmó— sin guardar nada;
 *   3. ve el resultado y guarda las que están bien;
 *   4. resumen: cuáles se entregaron, cuáles quedaron a medias.
 *
 * Se mandan de a uno (y tres a la vez): así no importa si son 50 o 300, no
 * se choca con el límite de subida, y la barra avanza de verdad.
 */
@Component({
  selector: 'app-subir-firmadas',
  standalone: true,
  imports: [CommonModule, FormModalComponent, IconComponent, SelectorArchivoComponent],
  templateUrl: './subir-firmadas.component.html',
})
export class SubirFirmadasComponent {
  private boletas = inject(BoletaService);

  @Input() visible = false;
  @Output() visibleChange = new EventEmitter<boolean>();
  @Input() mes = 1;
  @Input() anio = new Date().getFullYear();
  @Input() requeridas = 1;
  /** Se guardó al menos una: la pantalla de atrás recarga. */
  @Output() guardadas = new EventEmitter<void>();

  paso: Paso = 'elegir';
  archivos: File[] = [];
  filas: Fila[] = [];
  hechos = 0;
  private trabajo?: Subscription;

  readonly pasos: { id: Paso[]; etiqueta: string }[] = [
    { id: ['elegir'], etiqueta: 'Elegir' },
    { id: ['revisando', 'revision'], etiqueta: 'Revisar' },
    { id: ['guardando', 'listo'], etiqueta: 'Guardar' },
  ];

  get periodo(): string {
    return `${nombreMes(Number(this.mes))} ${this.anio}`;
  }

  get ocupado(): boolean {
    return this.paso === 'revisando' || this.paso === 'guardando';
  }

  get porcentaje(): number {
    return this.filas.length ? Math.round((this.hechos / this.filas.length) * 100) : 0;
  }

  get listas(): Fila[] {
    return this.filas.filter((f) => f.revision?.estado === 'ok');
  }

  get conProblema(): Fila[] {
    return this.filas.filter((f) => f.revision?.estado !== 'ok');
  }

  get seEntregan(): number {
    return this.listas.filter((f) => f.revision?.resultado === 'completa').length;
  }

  get aMedias(): number {
    return this.listas.filter((f) => f.revision?.resultado === 'parcial').length;
  }

  pasoActivo(ids: Paso[]): boolean {
    return ids.includes(this.paso);
  }

  pasoHecho(indice: number): boolean {
    const orden = this.pasos.findIndex((p) => p.id.includes(this.paso));
    return indice < orden;
  }

  /** Un rechazo dicho corto, para la etiqueta de la fila. */
  etiquetaDe(f: Fila): { texto: string; tono: 'success' | 'info' | 'danger' | 'warning' | 'secondary' } {
    const r = f.revision;
    if (f.guardado === 'si') return r?.resultado === 'completa' ? { texto: 'Entregada', tono: 'success' } : { texto: 'Guardada a medias', tono: 'info' };
    if (f.guardado === 'no') return { texto: 'No se guardó', tono: 'danger' };
    if (!r) return { texto: 'Sin revisar', tono: 'secondary' };
    if (r.estado === 'ok') {
      return r.resultado === 'completa'
        ? { texto: 'Lista para entregar', tono: 'success' }
        : { texto: `${r.firmas_validas} de ${r.firmas_requeridas} firmas`, tono: 'info' };
    }
    const cortas: Record<string, string> = {
      no_es_pdf: 'No es PDF', no_reconocido: 'No se reconoce', sin_boleta: 'Sin boleta', otro_mes: 'Otro mes',
      no_aplica: 'No va con firma digital', cambiada: 'Archivo cambiado', sin_firma: 'Sin firma',
      firma_invalida: 'Firma no válida', modificada: 'Tocada después de firmar', sin_cambios: 'Ya estaba', error: 'Error',
    };
    return { texto: cortas[r.estado] ?? 'Con problema', tono: r.estado === 'sin_cambios' ? 'secondary' : 'warning' };
  }

  firmantes(f: Fila): string {
    return (f.revision?.firmas ?? []).map((x) => x.nombre ?? 'Sin nombre').join(' · ');
  }

  // ── Los pasos ──

  revisar(): void {
    if (!this.archivos.length) return;
    this.filas = this.archivos.map((archivo) => ({ archivo, revision: null }));
    this.paso = 'revisando';
    this.hechos = 0;

    this.trabajo = from(this.filas)
      .pipe(
        mergeMap((fila) => this.boletas.revisarFirmada(fila.archivo, this.mes, this.anio).pipe(
          catchError((err) => of({ success: true, data: this.errorComoRevision(fila.archivo, err) })),
          // Se arma la fila aquí y no en subscribe: así cada respuesta cae en
          // SU archivo aunque lleguen desordenadas.
          mergeMap((res) => {
            fila.revision = res.data;
            return of(fila);
          })
        ), 3)
      )
      .subscribe({
        next: () => this.hechos++,
        complete: () => {
          // Primero lo que se puede guardar; los problemas, debajo.
          this.filas = [...this.listas, ...this.conProblema];
          this.paso = 'revision';
        },
      });
  }

  guardar(): void {
    const aGuardar = this.listas;
    if (!aGuardar.length) return;
    this.paso = 'guardando';
    this.hechos = 0;
    this.filas = aGuardar.concat(this.conProblema);
    let alguna = false;

    this.trabajo = from(aGuardar)
      .pipe(
        mergeMap((fila) => this.boletas.guardarFirmada(fila.archivo, this.mes, this.anio).pipe(
          mergeMap((res) => {
            fila.guardado = 'si';
            fila.revision = res.data;
            alguna = true;
            return of(fila);
          }),
          catchError((err) => {
            fila.guardado = 'no';
            fila.error = mensajeErrorApi(err, 'No se pudo guardar.');
            return of(fila);
          })
        ), 3)
      )
      .subscribe({
        next: () => this.hechos++,
        complete: () => {
          this.paso = 'listo';
          if (alguna) this.guardadas.emit();
        },
      });
  }

  get guardadasCompletas(): number {
    return this.filas.filter((f) => f.guardado === 'si' && f.revision?.resultado === 'completa').length;
  }

  get guardadasAMedias(): number {
    return this.filas.filter((f) => f.guardado === 'si' && f.revision?.resultado === 'parcial').length;
  }

  get noGuardadas(): Fila[] {
    return this.filas.filter((f) => f.guardado === 'no');
  }

  volverAElegir(): void {
    this.trabajo?.unsubscribe();
    this.paso = 'elegir';
    this.filas = [];
    this.hechos = 0;
  }

  cerrar(): void {
    if (this.ocupado) return;
    this.trabajo?.unsubscribe();
    this.visible = false;
    this.visibleChange.emit(false);
    // La próxima vez arranca de cero.
    this.archivos = [];
    this.volverAElegir();
  }

  private errorComoRevision(archivo: File, err: unknown): RevisionBoletaFirmada {
    return {
      archivo: archivo.name, estado: 'error', mensaje: mensajeErrorApi(err, 'No se pudo revisar este archivo.'),
      trabajador: null, dni: null, numero: null, documento_id: null, firmas: [], firmas_validas: 0,
      firmas_requeridas: this.requeridas, resultado: null,
    };
  }
}
