import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { AjustesService, AjustesSistema, CamposValorLegal, MontoLegal, ToastService, ValorLegal } from '../../core/services';
import { mensajeErrorApi } from '../../core/utils';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { FormModalComponent } from '../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../shared/components/icon/icon.component';

/** Cómo se muestra cada monto de ley en la pantalla. */
interface CampoLegal {
  clave: MontoLegal;
  etiqueta: string;
  unidad: 'S/' | '%';
  ayuda?: string;
}

/**
 * Ajustes del sistema: lo que el Administrador enciende y apaga sin tocar
 * código. Cada cambio se guarda al momento y queda en la Auditoría.
 */
@Component({
  selector: 'app-ajustes',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, FormModalComponent, IconComponent],
  templateUrl: './ajustes.component.html',
})
export class AjustesComponent implements OnInit {
  private ajustesService = inject(AjustesService);
  private toastService = inject(ToastService);

  ajustes: AjustesSistema | null = null;
  cargando = true;
  guardando = false;

  /** El año en curso: "anteriores" son los de antes de este. */
  readonly anioActual = new Date().getFullYear();

  // ────────── Montos de ley por año ──────────

  /** Agrupados como se leen en una boleta: lo general, ONP/EsSalud y AFP. */
  readonly gruposLegales: { titulo: string; descripcion?: string; campos: CampoLegal[] }[] = [
    {
      titulo: 'Generales',
      campos: [
        { clave: 'uit', etiqueta: 'UIT', unidad: 'S/', ayuda: 'No paga Renta de 5ta quien gana menos de 7 UIT al año.' },
        { clave: 'asignacion_familiar', etiqueta: 'Asignación familiar', unidad: 'S/', ayuda: '10% del sueldo mínimo (RMV).' },
      ],
    },
    {
      titulo: 'ONP y EsSalud',
      campos: [
        { clave: 'onp', etiqueta: 'ONP', unidad: '%', ayuda: 'Se le descuenta al trabajador.' },
        { clave: 'essalud', etiqueta: 'EsSalud', unidad: '%', ayuda: 'Lo paga el colegio.' },
      ],
    },
    {
      titulo: 'AFP',
      descripcion: 'Las comisiones son las de flujo. A quien está en comisión mixta no se le cobra en planilla.',
      campos: [
        { clave: 'aporte_afp', etiqueta: 'Aporte al fondo', unidad: '%' },
        { clave: 'prima_seguro_afp', etiqueta: 'Prima de seguro', unidad: '%' },
        { clave: 'comision_habitat', etiqueta: 'Comisión Habitat', unidad: '%' },
        { clave: 'comision_integra', etiqueta: 'Comisión Integra', unidad: '%' },
        { clave: 'comision_prima', etiqueta: 'Comisión Prima', unidad: '%' },
        { clave: 'comision_profuturo', etiqueta: 'Comisión Profuturo', unidad: '%' },
      ],
    },
  ];

  /** El modal de "Agregar año". */
  modalAnioVisible = false;

  abrirAgregarAnio(): void {
    const anios = this.valoresLegales.map((v) => v.anio);
    this.nuevoAnioLegal = Math.max(this.anioActual, ...anios) + 1;
    this.modalAnioVisible = true;
  }

  valoresLegales: ValorLegal[] = [];
  anioLegal: number | null = null;
  /** Lo que se está editando del año elegido; se guarda con el botón. */
  edicionLegal: CamposValorLegal | null = null;
  guardandoLegal = false;
  nuevoAnioLegal = this.anioActual + 1;

  get hayCambiosLegales(): boolean {
    const guardado = this.valoresLegales.find((v) => v.anio === this.anioLegal);
    if (!guardado || !this.edicionLegal) return false;
    const nombreCambio = (this.edicionLegal.nombre_anio ?? '').trim() !== (guardado.nombre_anio ?? '').trim();
    return nombreCambio
      || this.gruposLegales.some((g) => g.campos.some((c) => Number(this.edicionLegal![c.clave]) !== Number(guardado[c.clave])));
  }

  private cargarValoresLegales(elegir?: number): void {
    this.ajustesService.valoresLegales().subscribe({
      next: (res) => {
        if (!res.success) return;
        this.valoresLegales = res.data;
        const anios = res.data.map((v) => v.anio);
        // El año en curso de entrada: es el que se usa casi siempre.
        this.elegirAnioLegal(elegir ?? (anios.includes(this.anioActual) ? this.anioActual : anios[0]));
        this.nuevoAnioLegal = Math.max(this.anioActual, ...anios) + 1;
      },
      error: (err) => this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los montos de ley.')),
    });
  }

  elegirAnioLegal(anio: number | null): void {
    const fila = this.valoresLegales.find((v) => v.anio === Number(anio));
    this.anioLegal = fila?.anio ?? null;
    if (!fila) {
      this.edicionLegal = null;
      return;
    }
    const { anio: _, nombre_anio, ...montos } = fila;
    // Los montos con dos decimales, como se escriben en la planilla: "1.60", "113.00".
    this.edicionLegal = {
      nombre_anio: nombre_anio ?? '',
      ...Object.fromEntries(Object.entries(montos).map(([clave, valor]) => [clave, Number(valor).toFixed(2)])),
    } as unknown as CamposValorLegal;
  }

  guardarValoresLegales(): void {
    if (this.anioLegal === null || !this.edicionLegal) return;

    const { nombre_anio, ...montos } = this.edicionLegal;
    const valores = {
      // Vacío se guarda como "sin nombre": la boleta no lleva esa línea.
      nombre_anio: (nombre_anio ?? '').trim() || null,
      ...Object.fromEntries(Object.entries(montos).map(([clave, valor]) => [clave, Number(valor)])),
    } as unknown as CamposValorLegal;

    this.guardandoLegal = true;
    this.ajustesService.actualizarAnio(this.anioLegal, valores).subscribe({
      next: (res) => {
        this.guardandoLegal = false;
        if (res.success) {
          this.toastService.success('Cambios guardados', `Las planillas y boletas de ${this.anioLegal} que se armen o recalculen desde ahora usan estos datos.`);
          this.cargarValoresLegales(this.anioLegal!);
        }
      },
      error: (err) => {
        this.guardandoLegal = false;
        this.toastService.error('No se guardó', mensajeErrorApi(err, 'Revisa los montos: ninguno puede quedar vacío.'));
      },
    });
  }

  agregarAnioLegal(): void {
    const anio = Number(this.nuevoAnioLegal);
    if (!anio) return;

    this.guardandoLegal = true;
    this.ajustesService.agregarAnio(anio).subscribe({
      next: (res) => {
        this.guardandoLegal = false;
        if (res.success) {
          this.modalAnioVisible = false;
          this.toastService.success('Año agregado', `${anio} se creó con los montos del año anterior. Escribe su nombre oficial y cambia los montos que hayan subido (la UIT casi siempre).`);
          this.cargarValoresLegales(anio);
        }
      },
      error: (err) => {
        this.guardandoLegal = false;
        this.toastService.error('No se agregó', mensajeErrorApi(err, 'No se pudo agregar ese año.'));
      },
    });
  }

  ngOnInit(): void {
    this.cargarValoresLegales();

    this.ajustesService.obtener().subscribe({
      next: (res) => {
        if (res.success) this.ajustes = res.data;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudieron cargar los ajustes.'));
      },
    });
  }

  cambiarAniosAnteriores(activo: boolean): void {
    if (!this.ajustes) return;

    const antes = this.ajustes.permitir_anios_anteriores;
    // Se ve al instante; si el servidor lo rechaza, se devuelve como estaba.
    this.ajustes = { ...this.ajustes, permitir_anios_anteriores: activo };
    this.guardando = true;

    this.ajustesService.guardar({ permitir_anios_anteriores: activo }).subscribe({
      next: (res) => {
        this.guardando = false;
        if (res.success) {
          this.ajustes = res.data;
          this.toastService.success(
            'Ajuste guardado',
            activo
              ? 'RR.HH. ya puede armar planillas y boletas de años anteriores.'
              : `Desde ahora solo se arman planillas y boletas de ${this.anioActual} en adelante.`
          );
        }
      },
      error: (err) => {
        this.guardando = false;
        this.ajustes = { ...this.ajustes!, permitir_anios_anteriores: antes };
        this.toastService.error('No se guardó', mensajeErrorApi(err, 'No se pudo guardar el ajuste.'));
      },
    });
  }
}
