import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { CargaHistorial5ta, FilaRenta5ta, ListaRenta5ta, Renta5taService } from '../../../core/services/planilla/renta5ta.service';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { MESES_OPCIONES, nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { CeldaTablaDirective } from '../../../shared/components/data-table/celda-tabla.directive';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';

type FiltroRenta5ta = 'todos' | 'pagan' | 'diferencias' | 'sin_historial';

/**
 * Renta de 5ta: cuánto le toca retener a cada trabajador en el año, con el
 * procedimiento de SUNAT, y cuánto se le retuvo ya.
 *
 * Se mira un año y un mes: el impuesto del año de cada uno y, de ese mes,
 * lo que le correspondía retener frente a lo que se le retuvo de verdad.
 *
 * Los meses pagados antes del sistema se cargan como historial (Excel o a
 * mano en la hoja de cada uno): sin ellos se estiman con la ficha, y la
 * pantalla lo avisa.
 */
@Component({
  selector: 'app-renta5ta-list',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, PageHeaderComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorArchivoComponent],
  templateUrl: './renta5ta-list.component.html',
})
export class Renta5taListComponent implements OnInit {
  private servicio = inject(Renta5taService);
  private toast = inject(ToastService);
  private progreso = inject(ProgresoService);
  private router = inject(Router);
  private ruta = inject(ActivatedRoute);

  readonly anioActual = new Date().getFullYear();
  readonly anios = [this.anioActual, this.anioActual - 1, this.anioActual - 2];
  readonly meses = MESES_OPCIONES;
  anio = this.anioActual;
  /** null hasta la primera carga: el servidor elige el mes en curso. */
  mes: number | null = null;

  datos: ListaRenta5ta | null = null;
  cargando = false;

  /**
   * Los cuatro filtros de la lista. Se aplican aquí, sobre lo que ya llegó:
   * el cálculo de todo el personal se hace una vez por año y mes.
   */
  readonly filtros: { id: FiltroRenta5ta; etiqueta: string; icono: string; ayuda: string }[] = [
    { id: 'todos', etiqueta: 'Todos', icono: 'people', ayuda: 'Todo el personal del año' },
    { id: 'pagan', etiqueta: 'Pagan 5ta', icono: 'wallet', ayuda: 'Ganan más de 7 UIT al año, o ya se les retuvo algo' },
    { id: 'diferencias', etiqueta: 'Con diferencias', icono: 'warning', ayuda: 'Algún mes se les retuvo distinto de lo que correspondía' },
    { id: 'sin_historial', etiqueta: 'Les falta historial', icono: 'clock', ayuda: 'Meses ya pasados sin planilla ni historial: se están estimando' },
  ];
  filtro: FiltroRenta5ta = 'todos';
  /**
   * Las filas que ve la tabla. Es una propiedad y no un getter a propósito:
   * un arreglo nuevo en cada ciclo haría que la tabla volviera a la página 1.
   */
  filasVisibles: FilaRenta5ta[] = [];

  get cifras(): CifraCabecera[] {
    const r = this.datos?.resumen;
    return [
      { icono: 'people', valor: r?.trabajadores ?? 0, etiqueta: 'Trabajadores' },
      { icono: 'wallet', valor: r?.pagan_5ta ?? 0, etiqueta: 'Pagan 5ta', tono: 'success' },
      { icono: 'warning', valor: r?.con_diferencias ?? 0, etiqueta: 'Con diferencias', tono: 'warning' },
    ];
  }

  get nombreDelMes(): string {
    return this.datos ? nombreMes(this.datos.mes) : '';
  }

  cuentaDe(filtro: FiltroRenta5ta): number {
    const r = this.datos?.resumen;
    if (!r) return 0;
    return { todos: r.trabajadores, pagan: r.pagan_5ta, diferencias: r.con_diferencias, sin_historial: r.sin_historial }[filtro];
  }

  columnas: ColumnaTabla<FilaRenta5ta>[] = [];

  /** Las columnas del mes llevan su nombre: «Le corresponde en Setiembre». */
  private armarColumnas(): void {
    const mes = this.nombreDelMes || 'el mes';
    this.columnas = [
      { campo: 'nombre', header: 'Trabajador', ancho: '27%' },
      { campo: 'impuesto_anual', header: `Impuesto de ${this.anio}`, ancho: '17%' },
      { campo: 'corresponde_mes', header: `Le corresponde en ${mes}`, ancho: '16%' },
      { campo: 'retenido_mes', header: `Se le retuvo en ${mes}`, ancho: '16%' },
      {
        campo: 'meses_con_diferencia',
        header: 'Situación',
        tipo: 'badge',
        ancho: '14%',
        formatear: (_v, f) => this.situacion(f).texto,
        badgeSeveridad: (_v, f) => this.situacion(f).tono,
      },
    ];
  }

  /** Lo primero que hay que mirar de cada uno, en orden de importancia. */
  situacion(f: FilaRenta5ta): { texto: string; tono: 'success' | 'warning' | 'danger' | 'secondary' } {
    if (f.meses_con_diferencia.length) return { texto: `Difiere en ${f.meses_con_diferencia.length} mes(es)`, tono: 'danger' };
    if (f.meses_sin_dato.length) return { texto: `Faltan ${f.meses_sin_dato.length} mes(es)`, tono: 'warning' };
    if (!f.paga_5ta) return { texto: 'No paga 5ta', tono: 'secondary' };
    return { texto: 'Al día', tono: 'success' };
  }

  /** De dónde sale lo que le corresponde en el mes elegido. */
  textoFuente(f: FilaRenta5ta): string {
    return {
      planilla: 'Según su planilla',
      historial: 'Según el historial',
      proyectado: 'Estimado: sin dato del mes',
      no_trabaja: 'No trabajó este mes',
    }[f.fuente_mes];
  }

  mesesTexto(meses: number[]): string {
    return meses.map((m) => nombreMes(m).slice(0, 3)).join(', ');
  }

  acciones: AccionPersonalizada<FilaRenta5ta>[] = [
    { id: 'ver', titulo: 'Ver su hoja de retención, mes por mes', icono: 'eye' },
  ];

  ngOnInit(): void {
    // Al volver de la hoja de alguien, la lista sigue en el año y mes que se miraban.
    const params = this.ruta.snapshot.queryParamMap;
    const anio = Number(params.get('anio'));
    const mes = Number(params.get('mes'));
    if (this.anios.includes(anio)) this.anio = anio;
    if (mes >= 1 && mes <= 12) this.mes = mes;
    this.cargar();
  }

  cambiarAnio(): void {
    // Otro año: que el servidor elija el mes (diciembre si ya pasó).
    this.mes = null;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.lista(this.anio, this.mes).subscribe({
      next: (res) => {
        this.cargando = false;
        if (!res.success) return;
        this.datos = res.data;
        this.mes = res.data.mes;
        this.armarColumnas();
        this.aplicarFiltro();
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo calcular la Renta de 5ta.'));
      },
    });
  }

  filtrar(filtro: FiltroRenta5ta): void {
    this.filtro = filtro;
    this.aplicarFiltro();
  }

  private aplicarFiltro(): void {
    const filas = this.datos?.filas ?? [];
    this.filasVisibles = {
      todos: () => filas,
      pagan: () => filas.filter((f) => f.paga_5ta),
      diferencias: () => filas.filter((f) => f.meses_con_diferencia.length > 0),
      sin_historial: () => filas.filter((f) => f.meses_sin_dato.length > 0),
    }[this.filtro]();
  }

  get mensajeVacio(): string {
    return {
      todos: 'No hay trabajadores en este año.',
      pagan: 'Nadie paga 5ta en este año.',
      diferencias: 'A nadie se le retuvo distinto de lo que correspondía.',
      sin_historial: 'Todos tienen sus meses completos: no falta historial.',
    }[this.filtro];
  }

  alAccionar(e: { accion: string; fila: FilaRenta5ta }): void {
    if (e.accion === 'ver') {
      this.router.navigate(['/inicio/renta-5ta', e.fila.id], { queryParams: { anio: this.anio, mes: this.mes } });
    }
  }

  // ── El historial (meses pagados antes del sistema) ──
  modalHistorial = false;
  archivo: File[] = [];
  subiendo = false;
  resultado: CargaHistorial5ta | null = null;

  descargarModelo(): void {
    this.servicio.modeloHistorial(this.anio).subscribe({
      next: (blob) => guardarArchivo(blob, `Historial 5ta ${this.anio}.xlsx`),
      error: () => this.toast.error('No se descargó', 'No se pudo armar el modelo del historial.'),
    });
  }

  abrirHistorial(): void {
    this.archivo = [];
    this.resultado = null;
    this.modalHistorial = true;
  }

  subirHistorial(): void {
    const archivo = this.archivo[0];
    if (!archivo) {
      this.toast.error('Falta el archivo', 'Elige el Excel del historial.');
      return;
    }
    this.subiendo = true;
    this.progreso.seguir('Cargando el historial de 5ta', this.servicio.cargarHistorial(this.anio, archivo)).subscribe({
      next: (res) => {
        this.subiendo = false;
        if (!res.success) return;
        this.resultado = res.data;
        this.archivo = [];
        this.toast.success('Historial cargado',
          `${res.data.meses_guardados} mes(es) de ${res.data.trabajadores} trabajador(es); se recalcularon ${res.data.recalculadas} planilla(s).`);
        this.cargar();
      },
      error: (err) => {
        this.subiendo = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'Revisa que sea el Excel del historial.'));
      },
    });
  }

  // ── Recalcular y exportar ──
  recalculando = false;
  exportando = false;

  recalcular(): void {
    this.recalculando = true;
    this.progreso.seguir('Recalculando la Renta de 5ta', this.servicio.recalcular(this.anio)).subscribe({
      next: (res) => {
        this.recalculando = false;
        if (!res.success) return;
        const { recalculadas, saltadas } = res.data;
        this.toast.success('5ta recalculada',
          `${recalculadas} planilla(s) de ${this.anio}` + (saltadas ? ` · ${saltadas} no se tocaron (planilla cerrada o boleta firmada)` : '') + '.');
        this.cargar();
      },
      error: (err) => {
        this.recalculando = false;
        this.toast.error('No se recalculó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }

  exportar(): void {
    this.exportando = true;
    this.progreso.seguir('Armando el Excel de la 5ta', this.servicio.exportar(this.anio)).subscribe({
      next: (blob) => {
        this.exportando = false;
        guardarArchivo(blob, `Renta de 5ta ${this.anio}.xlsx`);
      },
      error: () => {
        this.exportando = false;
        this.toast.error('No se descargó', 'No se pudo armar el Excel.');
      },
    });
  }
}
