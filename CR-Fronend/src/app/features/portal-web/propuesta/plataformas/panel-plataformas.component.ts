import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormArray, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { moverEn } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, PlataformaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const MAX_FUNCIONES = 8;
const HTTPS = /^https:\/\/\S+$/;

/** Con enlace, su texto y una dirección https. */
function validarEnlace(grupo: AbstractControl): ValidationErrors | null {
  const v = grupo.value;
  if (!v.conEnlace) return null;
  const errores: ValidationErrors = {};
  const etiqueta = (v.etiqueta ?? '').trim();
  if (!etiqueta || etiqueta.length > 40) errores['etiquetaMala'] = true;
  if (!HTTPS.test((v.url ?? '').trim())) errores['urlMala'] = true;
  return Object.keys(errores).length ? errores : null;
}

/**
 * Propuesta Educativa › Plataformas digitales: hasta 8, cada una con lo que
 * ofrece a quién, su enlace y un aviso si la descripción falta confirmar.
 *
 * El portal solo enlaza a cada plataforma: nunca pide usuario ni contraseña.
 */
@Component({
  selector: 'app-panel-plataformas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-plataformas.component.html',
})
export class PanelPlataformasComponent extends PanelDeLista<PlataformaPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.plataformas;
  protected readonly nombre = { singular: 'plataforma', femenino: true };

  readonly maxFunciones = MAX_FUNCIONES;

  columnas: ColumnaTabla<PlataformaPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Plataforma', ancho: '20%' },
    { campo: 'funciones', header: 'Para quién', formatear: (f: { titulo: string }[]) => (f.length ? f.map((x) => x.titulo).join(', ') : '—') },
    { campo: 'enlace', header: 'Enlace', ancho: '10%', formatear: (e) => (e ? 'Sí' : 'No') },
    { campo: 'aclaracion', header: 'Aviso', ancho: '18%', formatear: (a) => a ?? '—' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    estado: ['borrador' as EstadoPortal],
    descripcion: ['', [Validators.maxLength(300)]],
    aclaracion: ['', [Validators.maxLength(60)]],
    funciones: this.fb.array<FormGroup>([]),
    enlace: this.fb.group(
      { conEnlace: [false], etiqueta: [''], url: [''], externo: [true] },
      { validators: validarEnlace },
    ),
  });

  get funciones(): FormArray<FormGroup> {
    return this.form.controls.funciones;
  }

  get enlace(): FormGroup {
    return this.form.controls.enlace;
  }

  protected describir(p: PlataformaPortal): string {
    return `la plataforma «${p.nombre}»`;
  }

  private funcion(f?: { titulo: string; texto: string }): FormGroup {
    return this.fb.group({
      titulo: [f?.titulo ?? '', [Validators.required, Validators.maxLength(40)]],
      texto: [f?.texto ?? '', [Validators.required, Validators.maxLength(200)]],
    });
  }

  private llenar(p: PlataformaPortal | null): void {
    this.form.reset({
      nombre: p?.nombre ?? '', estado: p?.estado ?? 'borrador', descripcion: p?.descripcion ?? '', aclaracion: p?.aclaracion ?? '',
      enlace: { conEnlace: !!p?.enlace, etiqueta: p?.enlace?.etiqueta ?? '', url: p?.enlace?.url ?? '', externo: p?.enlace?.externo ?? true },
    });
    this.funciones.clear();
    (p?.funciones ?? []).forEach((f) => this.funciones.push(this.funcion(f)));
  }

  nueva(): void {
    this.editando = null;
    this.llenar(null);
    this.modalVisible = true;
  }

  editar(p: PlataformaPortal): void {
    this.editando = p;
    this.llenar(p);
    this.modalVisible = true;
  }

  agregarFuncion(): void {
    if (this.funciones.length < MAX_FUNCIONES) this.funciones.push(this.funcion());
  }

  quitarFuncion(i: number): void {
    this.funciones.removeAt(i);
  }

  moverFuncion(i: number, direccion: -1 | 1): void {
    moverEn(this.funciones, i, direccion);
  }

  invalido(control: { invalid: boolean; touched: boolean } | null | undefined): boolean {
    return !!control && control.invalid && control.touched;
  }

  errorDelEnlace(clave: string): boolean {
    return this.enlace.hasError(clave) && this.enlace.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const e = v.enlace;
    this.enviar({
      nombre: v.nombre,
      estado: v.estado,
      descripcion: (v.descripcion ?? '').trim() || null,
      aclaracion: (v.aclaracion ?? '').trim() || null,
      funciones: v.funciones.map((f) => ({ titulo: f['titulo'], texto: f['texto'] })),
      enlace: e.conEnlace ? { etiqueta: (e.etiqueta ?? '').trim(), url: (e.url ?? '').trim(), externo: !!e.externo } : null,
    });
  }
}
