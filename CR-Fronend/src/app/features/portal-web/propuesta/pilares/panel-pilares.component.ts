import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { mensajeErrorApi } from '../../../../core/utils';
import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { mostrarErrorDelPanel, moverEn } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, IntroduccionPilares, PilarPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const MAX_PARRAFOS = 6;

/**
 * Propuesta Educativa › Pilares: la introducción (un título y de 1 a 6
 * párrafos, o ninguna) y hasta 8 pilares, cada uno con su texto y, si los
 * tiene, sus aspectos («Espiritual», «Mental»…).
 */
@Component({
  selector: 'app-panel-pilares',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-pilares.component.html',
})
export class PanelPilaresComponent extends PanelDeLista<PilarPortal> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.pilares;
  protected readonly nombre = { singular: 'pilar', femenino: false };

  readonly maxParrafos = MAX_PARRAFOS;

  columnas: ColumnaTabla<PilarPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'titulo', header: 'Pilar', ancho: '24%' },
    { campo: 'texto', header: 'Texto' },
    { campo: 'puntos', header: 'Aspectos', ancho: '18%', formatear: (p: string[]) => (p.length ? p.join(', ') : '—') },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    texto: ['', [Validators.required, Validators.maxLength(400)]],
    puntos: ['', [validarLineas(0, 8, 30)]],
    estado: ['borrador' as EstadoPortal],
  });

  // ───────── La introducción ─────────

  cargandoIntro = true;
  guardandoIntro = false;
  private versionIntro: string | null = null;

  intro = this.fb.group({
    conIntroduccion: [true],
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    parrafos: this.fb.array<FormControl<string | null>>([]),
  });

  get parrafos(): FormArray<FormControl<string | null>> {
    return this.intro.controls.parrafos;
  }

  override ngOnInit(): void {
    super.ngOnInit();
    this.cargarIntro();
    // Sin introducción, sus campos no se validan.
    this.intro.controls.conIntroduccion.valueChanges.subscribe((con) => this.habilitarIntro(!!con));
  }

  protected describir(p: PilarPortal): string {
    return `el pilar «${p.titulo}»`;
  }

  nuevo(): void {
    this.editando = null;
    this.form.reset({ titulo: '', texto: '', puntos: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(p: PilarPortal): void {
    this.editando = p;
    this.form.reset({ titulo: p.titulo, texto: p.texto, puntos: p.puntos.join('\n'), estado: p.estado });
    this.modalVisible = true;
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({ titulo: v.titulo, texto: v.texto, puntos: lineas(v.puntos), estado: v.estado });
  }

  // ───────── La introducción ─────────

  private parrafo(texto = '') {
    return this.fb.control(texto, [Validators.required, Validators.maxLength(600)]);
  }

  private habilitarIntro(con: boolean): void {
    const opciones = { emitEvent: false };
    if (con) {
      this.intro.controls.titulo.enable(opciones);
      this.parrafos.enable(opciones);
    } else {
      this.intro.controls.titulo.disable(opciones);
      this.parrafos.disable(opciones);
    }
  }

  cargarIntro(): void {
    this.cargandoIntro = true;
    this.portalWebService.getFicha<IntroduccionPilares>('propuesta-pilares').subscribe({
      next: (res) => {
        const i = res.data.contenido?.introduccion ?? null;
        this.versionIntro = res.data.version;
        this.intro.reset({ conIntroduccion: !!i, titulo: i?.titulo ?? '' });
        this.parrafos.clear();
        (i?.parrafos?.length ? i.parrafos : ['']).forEach((p) => this.parrafos.push(this.parrafo(p)));
        this.habilitarIntro(!!i);
        this.intro.markAsPristine();
        this.cargandoIntro = false;
      },
      error: (err) => {
        this.cargandoIntro = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar la introducción de los pilares.'));
      },
    });
  }

  agregarParrafo(): void {
    if (this.parrafos.length < MAX_PARRAFOS) {
      this.parrafos.push(this.parrafo());
      this.parrafos.markAsDirty();
    }
  }

  quitarParrafo(i: number): void {
    if (this.parrafos.length > 1) {
      this.parrafos.removeAt(i);
      this.parrafos.markAsDirty();
    }
  }

  moverParrafo(i: number, direccion: -1 | 1): void {
    moverEn(this.parrafos, i, direccion);
  }

  guardarIntro(): void {
    if (this.intro.invalid) {
      this.intro.markAllAsTouched();
      return;
    }

    const v = this.intro.getRawValue();
    const introduccion = v.conIntroduccion
      ? { titulo: v.titulo, parrafos: v.parrafos.map((p) => (p ?? '').trim()) }
      : null;

    this.guardandoIntro = true;
    this.portalWebService.guardarFicha<IntroduccionPilares>('propuesta-pilares', { introduccion }, this.versionIntro).subscribe({
      next: (res) => {
        this.guardandoIntro = false;
        this.versionIntro = res.data.version;
        this.intro.markAsPristine();
        this.toastService.success('Introducción guardada', introduccion ? 'El portal la mostrará en hasta un minuto.' : 'El portal dejará de mostrarla en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoIntro = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la introducción.');
      },
    });
  }
}
