import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { mensajeErrorApi } from '../../../../core/utils';
import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { hoyEnLima, mostrarErrorDelPanel } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { DISPONIBILIDADES, DisponibilidadVacantes, EstadoPortal, MatriculaVacantesNota, VacantePortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Matrícula › Vacantes: la disponibilidad de cada nivel, con la fecha en que
 * se confirmó y una nota bajo la lista. Hasta 6 niveles publicados.
 *
 * Solo cifras reales: el número es opcional, y con «Agotadas» o «Consultar»
 * no se pide (el portal no lo mostraría).
 */
@Component({
  selector: 'app-panel-vacantes',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-vacantes.component.html',
})
export class PanelVacantesComponent extends PanelDeLista<VacantePortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.vacantes;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  readonly disponibilidades = DISPONIBILIDADES;

  columnas: ColumnaTabla<VacantePortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nivel', header: 'Nivel', ancho: '18%' },
    { campo: 'detalle', header: 'Detalle', formatear: (d) => d ?? '—' },
    {
      campo: 'disponibilidad', header: 'Se muestra', tipo: 'badge', ancho: '18%',
      formatear: (d: DisponibilidadVacantes) => this.disponibilidad(d).nombre,
      badgeSeveridad: (d: DisponibilidadVacantes) => ({ disponible: 'success', pocas: 'warning', agotadas: 'danger', consultar: 'info' } as const)[d],
    },
    { campo: 'vacantes', header: 'Número', ancho: '10%', formatear: (n) => (n ?? '—') },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nivel: ['', [Validators.required, Validators.maxLength(40)]],
    detalle: ['', [Validators.maxLength(60)]],
    disponibilidad: ['consultar' as DisponibilidadVacantes, [Validators.required]],
    vacantes: [null as number | null, [Validators.min(0), Validators.max(9999), Validators.pattern(/^\d+$/)]],
    estado: ['borrador' as EstadoPortal],
  });

  // ───────── La ficha: fecha y nota ─────────

  cargandoNota = true;
  guardandoNota = false;
  private versionNota: string | null = null;

  nota = this.fb.group({
    actualizado: [''],
    nota: ['', [Validators.maxLength(240)]],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarNota();
    // Sin número para «Agotadas» o «Consultar»: el campo se apaga y se vacía.
    this.form.controls.disponibilidad.valueChanges.subscribe((d) => this.ajustarNumero(d));
  }

  disponibilidad(valor: DisponibilidadVacantes | null | undefined) {
    return this.disponibilidades.find((d) => d.valor === valor) ?? this.disponibilidades[0];
  }

  private ajustarNumero(d: DisponibilidadVacantes | null): void {
    const numero = this.form.controls.vacantes;
    if (this.disponibilidad(d).conNumero) {
      numero.enable({ emitEvent: false });
    } else {
      numero.setValue(null, { emitEvent: false });
      numero.disable({ emitEvent: false });
    }
  }

  protected describir(v: VacantePortal): string {
    return `las vacantes de «${v.nivel}»`;
  }

  nuevo(): void {
    this.editando = null;
    this.form.reset({ nivel: '', detalle: '', disponibilidad: 'consultar', vacantes: null, estado: 'borrador' });
    this.ajustarNumero('consultar');
    this.modalVisible = true;
  }

  editar(v: VacantePortal): void {
    this.editando = v;
    this.form.reset({ nivel: v.nivel, detalle: v.detalle ?? '', disponibilidad: v.disponibilidad, vacantes: v.vacantes, estado: v.estado });
    this.ajustarNumero(v.disponibilidad);
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const numero = v.vacantes === null || (v.vacantes as unknown) === '' ? null : Number(v.vacantes);
    this.enviar({ nivel: v.nivel, detalle: v.detalle || null, disponibilidad: v.disponibilidad, vacantes: numero, estado: v.estado });
  }

  // ───────── La ficha ─────────

  cargarNota(): void {
    this.cargandoNota = true;
    this.portalWebService.getFicha<MatriculaVacantesNota>('matricula-vacantes').subscribe({
      next: (res) => {
        this.versionNota = res.data.version;
        this.nota.reset({ actualizado: res.data.contenido?.actualizado ?? '', nota: res.data.contenido?.nota ?? '' });
        this.cargandoNota = false;
      },
      error: (err) => {
        this.cargandoNota = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la nota de las vacantes.'));
      },
    });
  }

  ponerHoy(): void {
    this.nota.controls.actualizado.setValue(hoyEnLima());
    this.nota.markAsDirty();
  }

  guardarNota(): void {
    if (this.nota.invalid) {
      this.nota.markAllAsTouched();
      return;
    }

    const v = this.nota.getRawValue();
    this.guardandoNota = true;
    this.portalWebService.guardarFicha<MatriculaVacantesNota>('matricula-vacantes', { actualizado: v.actualizado || null, nota: v.nota || null }, this.versionNota).subscribe({
      next: (res) => {
        this.guardandoNota = false;
        this.versionNota = res.data.version;
        this.nota.markAsPristine();
        this.toastService.success('Guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoNota = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la nota de las vacantes.');
      },
    });
  }
}
