import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { AjustesService, AjustesSistema, CamposValorLegal, ToastService, ValorLegal } from '../../core/services';
import { mensajeErrorApi } from '../../core/utils';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';

/** Cómo se muestra cada monto de ley en la pantalla. */
interface CampoLegal {
  clave: keyof CamposValorLegal;
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
  imports: [CommonModule, FormsModule, PageHeaderComponent],
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
  readonly gruposLegales: { titulo: string; campos: CampoLegal[] }[] = [
    {
      titulo: 'Generales',
      campos: [
        { clave: 'uit', etiqueta: 'UIT', unidad: 'S/', ayuda: 'Para la Renta de 5ta: no paga quien gana menos de 7 UIT al año.' },
        { clave: 'asignacion_familiar', etiqueta: 'Asignación familiar', unidad: 'S/', ayuda: 'El 10% de la remuneración mínima (RMV) de ese año.' },
      ],
    },
    {
      titulo: 'ONP y EsSalud',
      campos: [
        { clave: 'onp', etiqueta: 'ONP', unidad: '%' },
        { clave: 'essalud', etiqueta: 'EsSalud', unidad: '%', ayuda: 'Lo aporta el colegio, no se le descuenta al trabajador.' },
      ],
    },
    {
      titulo: 'AFP',
      campos: [
        { clave: 'aporte_afp', etiqueta: 'Aporte al fondo', unidad: '%' },
        { clave: 'prima_seguro_afp', etiqueta: 'Prima de seguro', unidad: '%' },
        { clave: 'comision_habitat', etiqueta: 'Comisión Habitat', unidad: '%' },
        { clave: 'comision_integra', etiqueta: 'Comisión Integra', unidad: '%' },
        { clave: 'comision_prima', etiqueta: 'Comisión Prima', unidad: '%' },
        { clave: 'comision_profuturo', etiqueta: 'Comisión Profuturo', unidad: '%', ayuda: 'Las comisiones son las de "flujo": a quien está en Mixta no se le cobra.' },
      ],
    },
  ];

  valoresLegales: ValorLegal[] = [];
  anioLegal: number | null = null;
  /** Lo que se está editando del año elegido; se guarda con el botón. */
  edicionLegal: CamposValorLegal | null = null;
  guardandoLegal = false;
  nuevoAnioLegal = this.anioActual + 1;

  get hayCambiosLegales(): boolean {
    const guardado = this.valoresLegales.find((v) => v.anio === this.anioLegal);
    if (!guardado || !this.edicionLegal) return false;
    return this.gruposLegales.some((g) => g.campos.some((c) => Number(this.edicionLegal![c.clave]) !== Number(guardado[c.clave])));
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
    const { anio: _, ...campos } = fila;
    this.edicionLegal = { ...campos };
  }

  guardarValoresLegales(): void {
    if (this.anioLegal === null || !this.edicionLegal) return;

    const valores = Object.fromEntries(
      Object.entries(this.edicionLegal).map(([clave, valor]) => [clave, Number(valor)])
    ) as unknown as CamposValorLegal;

    this.guardandoLegal = true;
    this.ajustesService.actualizarAnio(this.anioLegal, valores).subscribe({
      next: (res) => {
        this.guardandoLegal = false;
        if (res.success) {
          this.toastService.success('Montos guardados', `Las planillas de ${this.anioLegal} que se armen o recalculen desde ahora usan estos montos.`);
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
          this.toastService.success('Año agregado', `${anio} se creó con los montos del año anterior. Cambia los que hayan subido (la UIT casi siempre).`);
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
