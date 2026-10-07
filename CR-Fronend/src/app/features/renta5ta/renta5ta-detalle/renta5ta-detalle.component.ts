import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { ToastService } from '../../../core/services';
import { HojaRenta5ta, MesRenta5ta, Renta5taService } from '../../../core/services/planilla/renta5ta.service';
import { mensajeErrorApi } from '../../../core/utils';
import { nombreMes } from '../../../shared/constants';
import { FormModalComponent } from '../../../shared/components/form-modal/form-modal.component';
import { CifraCabecera, PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconComponent } from '../../../shared/components/icon/icon.component';

/** Una fila de la hoja: qué dice y de dónde sale el número de cada mes. */
interface FilaHoja {
  etiqueta: string;
  valor: (m: MesRenta5ta) => number | string | null;
  /** Total del año en la última columna (solo lo que se suma). */
  total?: boolean;
  destacada?: boolean;
  ayuda?: string;
}

/**
 * La hoja de retención de 5ta de un trabajador, mes por mes: lo mismo que
 * hacía la hoja «RETENCION» de la PLAME, con sus datos reales del año.
 *
 * Los meses que se pagaron antes del sistema se pueden escribir aquí (lo que
 * cobró y lo que se le retuvo): al guardar se recalculan las planillas de
 * los meses siguientes.
 */
@Component({
  selector: 'app-renta5ta-detalle',
  standalone: true,
  imports: [CommonModule, FormsModule, IconComponent, PageHeaderComponent, FormModalComponent],
  templateUrl: './renta5ta-detalle.component.html',
})
export class Renta5taDetalleComponent implements OnInit {
  private servicio = inject(Renta5taService);
  private toast = inject(ToastService);
  private ruta = inject(ActivatedRoute);
  private router = inject(Router);

  empleadoId = '';
  anio = new Date().getFullYear();
  /** El mes que se miraba en la lista (o el actual): su columna va resaltada. */
  mesResaltado = new Date().getMonth() + 1;
  hoja: HojaRenta5ta | null = null;
  cargando = false;

  readonly meses = Array.from({ length: 12 }, (_, i) => i + 1);
  nombreMes = nombreMes;

  readonly fuentes: Record<string, string> = {
    planilla: 'Sistema', historial: 'Historial', proyectado: 'Estimado', no_trabaja: '—',
  };

  readonly filas: FilaHoja[] = [
    { etiqueta: 'Remuneración del mes', valor: (m) => m.remuneracion_mes, ayuda: 'Sueldo, asignación familiar y bonificación por cargo de ese mes' },
    { etiqueta: 'Nº de meses que faltan', valor: (m) => m.meses_que_faltan, ayuda: 'Incluido el mes: con esto se proyecta el resto del año' },
    { etiqueta: 'Remuneración proyectada', valor: (m) => m.remuneracion_proyectada },
    { etiqueta: 'Gratificaciones (con 9%)', valor: (m) => m.gratificaciones, ayuda: 'Las de julio y diciembre que todavía no cobró' },
    { etiqueta: 'Remuneraciones anteriores', valor: (m) => m.remuneraciones_anteriores, ayuda: 'Lo que ya cobró en el año, de sus planillas o del historial' },
    { etiqueta: 'Renta bruta anual', valor: (m) => m.renta_bruta, destacada: true },
    { etiqueta: '(−) 7 UIT', valor: (m) => m.deduccion },
    { etiqueta: 'Renta neta', valor: (m) => m.renta_neta },
    { etiqueta: 'Impuesto del año', valor: (m) => m.impuesto_anual, destacada: true, ayuda: 'Tramos: 8% hasta 5 UIT, 14% hasta 20, 17% hasta 35, 20% hasta 45, 30% más' },
    { etiqueta: '(−) Ya retenido antes', valor: (m) => m.retenido_antes },
    { etiqueta: 'Divisor', valor: (m) => (m.mes === 12 ? 'Ajuste' : m.divisor), ayuda: 'Enero-marzo 12, abril 9, mayo-julio 8, agosto 5, setiembre-noviembre 4; diciembre regulariza' },
    { etiqueta: 'Retención ordinaria', valor: (m) => m.retencion_ordinaria },
    { etiqueta: 'Retención adicional', valor: (m) => m.retencion_adicional, ayuda: 'Por un ingreso extraordinario del mes (un bono): se retiene entero ese mes' },
    { etiqueta: 'Retención que corresponde', valor: (m) => m.retencion, total: true, destacada: true },
    { etiqueta: 'Retención real', valor: (m) => m.retencion_real, total: true, ayuda: 'Lo que dice su planilla del sistema o el historial cargado' },
  ];

  get cifras(): CifraCabecera[] {
    const r = this.hoja?.resumen;
    return [
      { icono: 'wallet', valor: r?.impuesto_anual ?? 0, etiqueta: 'Impuesto del año' },
      { icono: 'check', valor: r?.retenido ?? 0, etiqueta: 'Ya retenido', tono: 'success' },
      { icono: 'clock', valor: r?.por_retener ?? 0, etiqueta: 'Por retener', tono: 'warning' },
    ];
  }

  ngOnInit(): void {
    this.empleadoId = this.ruta.snapshot.paramMap.get('id') ?? '';
    const anio = Number(this.ruta.snapshot.queryParamMap.get('anio'));
    if (anio) this.anio = anio;
    const mes = Number(this.ruta.snapshot.queryParamMap.get('mes'));
    if (mes >= 1 && mes <= 12) this.mesResaltado = mes;
    else if (this.anio !== new Date().getFullYear()) this.mesResaltado = 0;
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.servicio.hoja(this.empleadoId, this.anio).subscribe({
      next: (res) => {
        this.cargando = false;
        if (res.success) this.hoja = res.data;
      },
      error: (err) => {
        this.cargando = false;
        this.toast.error('No se cargó', mensajeErrorApi(err, 'No se pudo armar su hoja de 5ta.'));
      },
    });
  }

  volver(): void {
    this.router.navigate(['/inicio/renta-5ta'], { queryParams: { anio: this.anio, mes: this.mesResaltado || null } });
  }

  mesDe(n: number): MesRenta5ta | undefined {
    return this.hoja?.meses[n - 1];
  }

  /** El número de la celda, ya listo para pintar. */
  celda(fila: FilaHoja, n: number): string {
    const m = this.mesDe(n);
    if (!m || !m.trabaja) return '';
    const v = fila.valor(m);
    if (v === null || v === undefined) return '—';
    if (typeof v === 'string') return v;
    if (fila.etiqueta.startsWith('Nº') || fila.etiqueta === 'Divisor') return String(v);
    return v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  totalDe(fila: FilaHoja): string {
    if (!fila.total || !this.hoja) return '';
    const suma = this.hoja.meses.filter((m) => m.trabaja).reduce((s, m) => s + (Number(fila.valor(m)) || 0), 0);
    return suma.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /** La retención real no coincide con la que corresponde (más de un céntimo). */
  difiere(n: number): boolean {
    const m = this.mesDe(n);
    return !!m && m.retencion_real !== null && Math.abs(m.retencion_real - m.retencion) > 0.01;
  }

  // ── Escribir un mes pagado antes del sistema ──
  modalMes = false;
  guardando = false;
  editando: { mes: number; remuneracion: number | null; retencion: number | null } = { mes: 1, remuneracion: null, retencion: null };

  /** Solo los meses que no tienen planilla en el sistema. */
  sePuedeEscribir(n: number): boolean {
    const m = this.mesDe(n);
    return !!m && m.trabaja && m.fuente !== 'planilla';
  }

  editarMes(n: number): void {
    const m = this.mesDe(n);
    const esHistorial = m?.fuente === 'historial';
    this.editando = {
      mes: n,
      remuneracion: esHistorial ? m!.remuneracion_mes : null,
      retencion: esHistorial ? m!.retencion_real : null,
    };
    this.modalMes = true;
  }

  guardarMes(borrar = false): void {
    const { mes, remuneracion, retencion } = this.editando;
    if (!borrar && (remuneracion === null || retencion === null)) {
      this.toast.error('Faltan datos', 'Escribe lo que cobró y lo que se le retuvo ese mes (0 si no se le retuvo nada).');
      return;
    }
    this.guardando = true;
    this.servicio.guardarMes(this.empleadoId, this.anio, mes, borrar ? null : remuneracion, borrar ? null : retencion).subscribe({
      next: (res) => {
        this.guardando = false;
        if (!res.success) return;
        this.hoja = { ...res.data, empleado: this.hoja?.empleado };
        this.modalMes = false;
        this.toast.success(borrar ? 'Mes borrado' : 'Mes guardado',
          `${nombreMes(mes)}: ` + (res.data.recalculadas ? `se recalcularon ${res.data.recalculadas} planilla(s) siguientes.` : 'listo.'));
      },
      error: (err) => {
        this.guardando = false;
        this.toast.error('No se guardó', mensajeErrorApi(err, 'Inténtalo de nuevo.'));
      },
    });
  }
}
