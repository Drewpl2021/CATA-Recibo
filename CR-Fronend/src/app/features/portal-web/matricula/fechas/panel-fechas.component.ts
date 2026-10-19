import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { mensajeErrorApi } from '../../../../core/utils';
import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { enfocarPrimerError, hoyEnLima, mostrarErrorDelPanel } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { EstadoPortal, FechaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';


const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** «2 de noviembre de 2026», desde AAAA-MM-DD (sin pasar por la zona horaria del navegador). */
export function fechaLarga(iso: string, conAnio = true): string {
  const [a, m, d] = iso.split('-').map(Number);
  return `${d} de ${MESES[m - 1]}${conAnio ? ` de ${a}` : ''}`;
}

/** El periodo como lo escribe el portal: «Del 2 de noviembre al 18 de diciembre de 2026». */
export function periodo(f: { inicio: string; fin: string | null }): string {
  if (!f.fin || f.fin === f.inicio) return fechaLarga(f.inicio);
  const mismoAnio = f.inicio.slice(0, 4) === f.fin.slice(0, 4);
  return `Del ${fechaLarga(f.inicio, !mismoAnio)} al ${fechaLarga(f.fin)}`;
}

/** «Hasta» no puede ser antes de «desde». */
function finDespuesDeInicio(grupo: AbstractControl): ValidationErrors | null {
  const { inicio, fin } = grupo.value as { inicio: string; fin: string };
  return inicio && fin && fin < inicio ? { finAntes: true } : null;
}

/**
 * Matrícula › Cronograma: las fechas del proceso y un aviso sobre ellas.
 *
 * Van siempre en orden cronológico (el portal las ordena por su inicio), así
 * que no se suben ni se bajan a mano. El portal marca «En curso» o «Terminó»
 * según la fecha de hoy en Lima. El aviso se lee sobre la tabla y se cambia
 * en su modal.
 */
@Component({
  selector: 'app-panel-fechas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-fechas.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelFechasComponent extends PanelDeLista<FechaPortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.fechas;
  protected readonly nombre = { singular: 'fecha', femenino: true };

  readonly periodo = periodo;

  // Cuándo va bajo qué pasa: así caben las acciones.
  columnas: ColumnaTabla<FechaPortal>[] = [
    { campo: 'titulo', header: 'Qué pasa y cuándo' },
    {
      campo: 'fin', header: 'Hoy', tipo: 'badge', ancho: '11%',
      formatear: (_v, f) => this.momento(f),
      badgeSeveridad: (_v, f) => ({ 'En curso': 'success', 'Terminó': 'secondary', 'Próxima': 'info' } as const)[this.momento(f)],
    },
    this.columnaDeEstado(),
  ];

  form = this.fb.group(
    {
      titulo: ['', [Validators.required, Validators.maxLength(80)]],
      inicio: ['', [Validators.required]],
      fin: [''],
      detalle: ['', [Validators.maxLength(160)]],
      estado: ['borrador' as EstadoPortal],
    },
    { validators: finDespuesDeInicio },
  );

  // ───────── La ficha: el aviso ─────────

  cargandoNota = true;
  guardandoNota = false;
  private versionNota: string | null = null;
  modalNota = false;
  /** Lo guardado: lo que se lee sobre la tabla. */
  notaGuardada: string | null = null;
  nota = this.fb.group({ nota: ['', [Validators.maxLength(200)]] });

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarNota();
  }

  /** Como lo marcará el portal hoy. */
  momento(f: FechaPortal): 'En curso' | 'Terminó' | 'Próxima' {
    const hoy = hoyEnLima();
    if ((f.fin ?? f.inicio) < hoy) return 'Terminó';
    return f.inicio <= hoy ? 'En curso' : 'Próxima';
  }

  get vistaDelPeriodo(): string | null {
    const v = this.form.getRawValue();
    return v.inicio && !this.form.hasError('finAntes') ? periodo({ inicio: v.inicio, fin: v.fin || null }) : null;
  }

  protected describir(f: FechaPortal): string {
    return `la fecha «${f.titulo}»`;
  }

  /** Si todas las publicadas ya terminaron, el cronograma quedó atrás: toca el del próximo proceso. */
  protected override resumir(): ResumenDeSeccion | null {
    const r = super.resumir();
    const publicadas = this.filas.filter((f) => f.estado === 'publicado');
    if (r && publicadas.length && publicadas.every((f) => this.momento(f) === 'Terminó')) {
      r.estado += ' · todas terminaron';
      r.aviso = true;
      r.pendientes = [{ cuantos: 1, texto: 'cronograma con todas sus fechas terminadas' }];
    }
    return r;
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ titulo: '', inicio: '', fin: '', detalle: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(f: FechaPortal): void {
    this.editando = f;
    this.form.reset({ titulo: f.titulo, inicio: f.inicio, fin: f.fin ?? '', detalle: f.detalle ?? '', estado: f.estado });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({ titulo: v.titulo, inicio: v.inicio, fin: v.fin || null, detalle: v.detalle || null, estado: v.estado });
  }

  // ───────── La ficha ─────────

  cargarNota(): void {
    this.cargandoNota = true;
    this.portalWebService.getFicha<{ nota: string | null }>('matricula-fechas').subscribe({
      next: (res) => {
        this.versionNota = res.data.version;
        this.notaGuardada = res.data.contenido?.nota ?? null;
        this.cargandoNota = false;
      },
      error: (err) => {
        this.cargandoNota = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar el aviso del cronograma.'));
      },
    });
  }

  /** El modal empieza con lo guardado: lo que se canceló antes no queda a medias. */
  abrirNota(): void {
    this.nota.reset({ nota: this.notaGuardada ?? '' });
    this.modalNota = true;
  }

  guardarNota(): void {
    if (this.nota.invalid) {
      this.nota.markAllAsTouched();
      enfocarPrimerError();
      return;
    }

    const nota = this.nota.getRawValue().nota || null;
    this.guardandoNota = true;
    this.portalWebService.guardarFicha('matricula-fechas', { nota }, this.versionNota).subscribe({
      next: (res) => {
        this.guardandoNota = false;
        this.versionNota = res.data.version;
        this.notaGuardada = nota;
        this.modalNota = false;
        this.toastService.success('Aviso guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoNota = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar el aviso del cronograma.');
      },
    });
  }
}
