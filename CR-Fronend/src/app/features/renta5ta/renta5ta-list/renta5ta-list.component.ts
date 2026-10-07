import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { ProgresoService } from '../../../core/services/sistema/progreso.service';
import { CargaHistorial5ta, FilaRenta5ta, ListaRenta5ta, Renta5taService } from '../../../core/services/planilla/renta5ta.service';
import { guardarArchivo, mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';
import { SelectorArchivoComponent } from '../../../shared/components/selector-archivo/selector-archivo.component';

/**
 * Renta de 5ta: cuánto le toca retener a cada trabajador en el año, con el
 * procedimiento de SUNAT, y cuánto se le retuvo ya.
 *
 * Los meses pagados antes del sistema se cargan como historial (Excel o a
 * mano en la hoja de cada uno): sin ellos se estiman con la ficha, y la
 * pantalla lo avisa.
 */
@Component({
  selector: 'app-renta5ta-list',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, PageHeaderComponent, DataTableComponent, FormModalComponent, SelectorArchivoComponent],
  templateUrl: './renta5ta-list.component.html',
})
export class Renta5taListComponent implements OnInit {
  private servicio = inject(Renta5taService);
  private toast = inject(ToastService);
  private progreso = inject(ProgresoService);
  private router = inject(Router);

  readonly anioActual = new Date().getFullYear();
  readonly anios = [this.anioActual, this.anioActual - 1, this.anioActual - 2];
  anio = this.anioActual;

  datos: ListaRenta5ta | null = null;
  cargando = false;

  get cifras(): CifraCabecera[] {
    const r = this.datos?.resumen;
    return [
      { icono: 'people', valor: r?.trabajadores ?? 0, etiqueta: 'Trabajadores' },
      { icono: 'wallet', valor: r?.con_retencion ?? 0, etiqueta: 'Con retención', tono: 'success' },
      { icono: 'warning', valor: r?.sin_historial ?? 0, etiqueta: 'Sin historial completo', tono: 'warning' },
    ];
  }

  get nombreDelMes(): string {
    return this.datos ? nombreMes(this.datos.mes) : '';
  }

  columnas: ColumnaTabla<FilaRenta5ta>[] = [
    { campo: 'dni', header: 'DNI', ancho: '9%' },
    { campo: 'nombre', header: 'Trabajador', ancho: '22%' },
    { campo: 'impuesto_anual', header: 'Impuesto del año', tipo: 'moneda', ancho: '10%' },
    { campo: 'retenido', header: 'Ya retenido', tipo: 'moneda', ancho: '10%' },
    { campo: 'retencion_del_mes', header: 'Retención del mes', tipo: 'moneda', ancho: '10%' },
    {
      campo: 'meses_sin_dato',
      header: 'Historial',
      tipo: 'badge',
      ancho: '12%',
      formatear: (v: number[]) => (v.length ? `Faltan ${v.length} mes(es)` : 'Completo'),
      badgeSeveridad: (v: number[]) => (v.length ? 'warning' : 'success'),
    },
  ];

  acciones: AccionPersonalizada<FilaRenta5ta>[] = [
    { id: 'ver', titulo: 'Ver su hoja de retención', icono: 'eye' },
  ];

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.lista(this.anio).subscribe({
      next: (res) => {
        this.cargando = false;
        if (res.success) this.datos = res.data;
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo calcular la Renta de 5ta.'));
      },
    });
  }

  alAccionar(e: { accion: string; fila: FilaRenta5ta }): void {
    if (e.accion === 'ver') this.router.navigate(['/inicio/renta-5ta', e.fila.id], { queryParams: { anio: this.anio } });
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
