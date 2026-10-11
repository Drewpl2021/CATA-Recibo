import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { ToastService } from '../../../../core/services';
import { mensajeErrorApi } from '../../../../core/utils';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { mostrarErrorDelPanel } from '../../compartido/panel';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { ImagenPortal, QuienesSomosPortal } from '../../portal-web.models';
import { PortalWebService } from '../../portal-web.service';

const MAX_PARRAFOS = 6;
const MAX_SIMBOLOS = 8;

/** Mueve un elemento de una lista del formulario un lugar arriba (-1) o abajo (+1). */
function moverEn(lista: FormArray, i: number, direccion: -1 | 1): void {
  const j = i + direccion;
  if (j < 0 || j >= lista.length) return;
  const control = lista.at(i);
  lista.removeAt(i);
  lista.insert(j, control);
  lista.markAsDirty();
}

/**
 * Inicio › Quiénes somos: el título, de 1 a 6 párrafos, una foto vertical 4:5
 * y el significado de los símbolos del emblema (hasta 8; sin ninguno, el
 * bloque no se muestra). Es una ficha: se guarda entera.
 */
@Component({
  selector: 'app-panel-quienes-somos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, SelectorImagenComponent],
  templateUrl: './panel-quienes-somos.component.html',
})
export class PanelQuienesSomosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private portalWebService = inject(PortalWebService);
  private toastService = inject(ToastService);

  readonly maxParrafos = MAX_PARRAFOS;
  readonly maxSimbolos = MAX_SIMBOLOS;

  cargando = true;
  guardando = false;
  private version: string | null = null;
  foto: ImagenPortal | null = null;
  fotoCambiada = false;

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(80)]],
    parrafos: this.fb.array<FormControl<string | null>>([]),
    simbolos: this.fb.array<FormGroup>([]),
  });

  get parrafos(): FormArray<FormControl<string | null>> {
    return this.form.controls.parrafos;
  }

  get simbolos(): FormArray<FormGroup> {
    return this.form.controls.simbolos;
  }

  get conCambios(): boolean {
    return this.form.dirty || this.fotoCambiada;
  }

  ngOnInit(): void {
    this.cargar();
  }

  private parrafo(texto = '') {
    return this.fb.control(texto, [Validators.required, Validators.maxLength(600)]);
  }

  private simbolo(s?: { nombre: string; significado: string }): FormGroup {
    return this.fb.group({
      nombre: [s?.nombre ?? '', [Validators.required, Validators.maxLength(40)]],
      significado: [s?.significado ?? '', [Validators.required, Validators.maxLength(160)]],
    });
  }

  cargar(): void {
    this.cargando = true;
    this.portalWebService.getFicha<QuienesSomosPortal>('quienes-somos').subscribe({
      next: (res) => {
        const c = res.data.contenido;
        this.version = res.data.version;
        this.form.reset({ titulo: c?.titulo ?? '' });
        this.parrafos.clear();
        this.simbolos.clear();
        (c?.parrafos?.length ? c.parrafos : ['']).forEach((p) => this.parrafos.push(this.parrafo(p)));
        (c?.simbolos ?? []).forEach((s) => this.simbolos.push(this.simbolo(s)));
        this.form.markAsPristine();
        this.foto = c?.foto ? res.data.imagenes[c.foto.imagenId] ?? null : null;
        this.fotoCambiada = false;
        this.cargando = false;
      },
      error: (err) => {
        this.cargando = false;
        this.toastService.error('Error', mensajeErrorApi(err, 'No se pudo cargar Quiénes somos.'));
      },
    });
  }

  alCambiarFoto(imagen: ImagenPortal | null): void {
    this.foto = imagen;
    this.fotoCambiada = true;
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

  agregarSimbolo(): void {
    if (this.simbolos.length < MAX_SIMBOLOS) {
      this.simbolos.push(this.simbolo());
      this.simbolos.markAsDirty();
    }
  }

  quitarSimbolo(i: number): void {
    this.simbolos.removeAt(i);
    this.simbolos.markAsDirty();
  }

  moverSimbolo(i: number, direccion: -1 | 1): void {
    moverEn(this.simbolos, i, direccion);
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
    const datos = {
      titulo: v.titulo,
      parrafos: v.parrafos.map((p) => (p ?? '').trim()),
      foto: this.foto?.id ?? null,
      simbolos: v.simbolos.map((s) => ({ nombre: s['nombre'], significado: s['significado'] })),
    };

    this.guardando = true;
    this.portalWebService.guardarFicha<QuienesSomosPortal>('quienes-somos', datos, this.version).subscribe({
      next: (res) => {
        this.guardando = false;
        this.version = res.data.version;
        this.form.markAsPristine();
        this.fotoCambiada = false;
        this.toastService.success('Quiénes somos guardado', 'El portal lo mostrará en hasta un minuto.');
      },
      error: (err: HttpErrorResponse) => {
        this.guardando = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar Quiénes somos.');
      },
    });
  }
}
