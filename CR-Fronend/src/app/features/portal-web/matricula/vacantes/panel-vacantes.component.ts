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
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { DISPONIBILIDADES, DisponibilidadVacantes, EstadoPortal, MatriculaVacantesNota, VacantePortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';
import { fechaLarga } from '../fechas/panel-fechas.component';

/**
 * Matrícula › Vacantes: la disponibilidad de cada nivel, con la fecha en que
 * se confirmó y una nota bajo la lista. Hasta 6 niveles publicados.
 *
 * Solo cifras reales: el número es opcional, y con «Agotadas» o «Consultar»
 * no se pide (el portal no lo mostraría). La fecha y la nota se leen sobre
 * la tabla y se cambian en su modal.
 */
@Component({
  selector: 'app-panel-vacantes',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-vacantes.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelVacantesComponent extends PanelDeLista<VacantePortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.vacantes;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  readonly disponibilidades = DISPONIBILIDADES;
  readonly fechaLarga = fechaLarga;

  // El detalle y el número van bajo el nivel: así caben las acciones.
  columnas: ColumnaTabla<VacantePortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nivel', header: 'Nivel' },
    {
      campo: 'disponibilidad', header: 'Se muestra', tipo: 'badge', ancho: '18%',
      formatear: (d: DisponibilidadVacantes) => this.disponibilidad(d).nombre,
      badgeSeveridad: (d: DisponibilidadVacantes) => ({ disponible: 'success', pocas: 'warning', agotadas: 'danger', consultar: 'info' } as const)[d],
    },
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
  modalNota = false;
  /** Lo guardado: lo que se lee sobre la tabla. */
  notaGuardada: MatriculaVacantesNota = { actualizado: null, nota: null };

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

  /** «1.er a 6.º grado · 25 vacantes», bajo el nivel en la tabla. */
  detalle(v: VacantePortal): string {
    const partes = [v.detalle, v.vacantes !== null ? `${v.vacantes} ${v.vacantes === 1 ? 'vacante' : 'vacantes'}` : null];
    return partes.filter(Boolean).join(' · ');
  }

  /** Con niveles publicados y sin fecha, el portal no dice desde cuándo valen las cifras. */
  protected override resumir(): ResumenDeSeccion | null {
    const r = super.resumir();
    if (!r || this.cargandoNota) return null;
    if (this.publicados && !this.notaGuardada.actualizado) {
      r.estado += ' · sin fecha';
      r.aviso = true;
      r.pendientes = [{ cuantos: 1, texto: 'fecha de actualización de las vacantes por poner' }];
    }
    return r;
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
      setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
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
        this.notaGuardada = { actualizado: res.data.contenido?.actualizado ?? null, nota: res.data.contenido?.nota ?? null };
        this.cargandoNota = false;
        this.avisarResumen();
      },
      error: (err) => {
        this.cargandoNota = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la nota de las vacantes.'));
      },
    });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrirNota(): void {
    this.nota.reset({ actualizado: this.notaGuardada.actualizado ?? '', nota: this.notaGuardada.nota ?? '' });
    this.modalNota = true;
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
    const datos = { actualizado: v.actualizado || null, nota: v.nota || null };
    this.guardandoNota = true;
    this.portalWebService.guardarFicha<MatriculaVacantesNota>('matricula-vacantes', datos, this.versionNota).subscribe({
      next: (res) => {
        this.guardandoNota = false;
        this.versionNota = res.data.version;
        this.notaGuardada = datos;
        this.modalNota = false;
        this.avisarResumen();
        this.toastService.success('Guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoNota = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la nota de las vacantes.');
      },
    });
  }
}
