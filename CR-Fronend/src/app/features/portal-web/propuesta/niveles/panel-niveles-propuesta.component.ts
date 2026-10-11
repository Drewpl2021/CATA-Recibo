import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { moverEn } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { EstadoPortal, ImagenPortal, NivelPropuestaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const MAX_PARRAFOS = 6;
const MAX_TALLERES = 12;

/**
 * Propuesta Educativa › Niveles: hasta 6, cada uno con su descripción, sus
 * rasgos, sus talleres y una foto 3:2. Son una lista propia: los niveles de
 * Inicio se editan en Inicio.
 */
@Component({
  selector: 'app-panel-niveles-propuesta',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent],
  templateUrl: './panel-niveles-propuesta.component.html',
})
export class PanelNivelesPropuestaComponent extends PanelDeLista<NivelPropuestaPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.nivelesPropuesta;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  readonly maxParrafos = MAX_PARRAFOS;
  readonly maxTalleres = MAX_TALLERES;

  foto: ImagenPortal | null = null;

  columnas: ColumnaTabla<NivelPropuestaPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Nivel', ancho: '16%' },
    { campo: 'rango', header: 'Rango', formatear: (r) => r ?? '—' },
    { campo: 'rasgos', header: 'Rasgos', ancho: '10%', formatear: (r: string[]) => String(r.length) },
    { campo: 'talleres', header: 'Talleres', ancho: '10%', formatear: (t: unknown[]) => String(t.length) },
    { campo: 'imagen_id', header: 'Foto', ancho: '8%', formatear: (f) => (f ? 'Sí' : 'No') },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    rango: ['', [Validators.maxLength(40)]],
    estado: ['borrador' as EstadoPortal],
    descripcion: this.fb.array<FormControl<string | null>>([]),
    rasgos: ['', [validarLineas(0, 8, 50)]],
    talleres: this.fb.array<FormGroup>([]),
  });

  get descripcion(): FormArray<FormControl<string | null>> {
    return this.form.controls.descripcion;
  }

  get talleres(): FormArray<FormGroup> {
    return this.form.controls.talleres;
  }

  protected describir(n: NivelPropuestaPortal): string {
    return `el nivel «${n.nombre}» de Propuesta Educativa`;
  }

  private parrafo(texto = '') {
    return this.fb.control(texto, [Validators.required, Validators.maxLength(600)]);
  }

  private taller(t?: { titulo: string; texto: string | null }): FormGroup {
    return this.fb.group({
      titulo: [t?.titulo ?? '', [Validators.required, Validators.maxLength(50)]],
      texto: [t?.texto ?? '', [Validators.maxLength(200)]],
    });
  }

  private llenar(n: NivelPropuestaPortal | null): void {
    this.form.reset({
      nombre: n?.nombre ?? '', rango: n?.rango ?? '', estado: n?.estado ?? 'borrador', rasgos: (n?.rasgos ?? []).join('\n'),
    });
    this.descripcion.clear();
    this.talleres.clear();
    (n?.descripcion ?? []).forEach((p) => this.descripcion.push(this.parrafo(p)));
    (n?.talleres ?? []).forEach((t) => this.talleres.push(this.taller(t)));
    this.foto = n?.imagen ?? null;
  }

  nuevo(): void {
    this.editando = null;
    this.llenar(null);
    this.modalVisible = true;
  }

  editar(n: NivelPropuestaPortal): void {
    this.editando = n;
    this.llenar(n);
    this.modalVisible = true;
  }

  agregarParrafo(): void {
    if (this.descripcion.length < MAX_PARRAFOS) this.descripcion.push(this.parrafo());
  }

  quitarParrafo(i: number): void {
    this.descripcion.removeAt(i);
  }

  moverParrafo(i: number, direccion: -1 | 1): void {
    moverEn(this.descripcion, i, direccion);
  }

  agregarTaller(): void {
    if (this.talleres.length < MAX_TALLERES) this.talleres.push(this.taller());
  }

  quitarTaller(i: number): void {
    this.talleres.removeAt(i);
  }

  moverTaller(i: number, direccion: -1 | 1): void {
    moverEn(this.talleres, i, direccion);
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
    this.enviar({
      nombre: v.nombre,
      rango: v.rango || null,
      estado: v.estado,
      descripcion: v.descripcion.map((p) => (p ?? '').trim()),
      rasgos: lineas(v.rasgos),
      talleres: v.talleres.map((t) => ({ titulo: t['titulo'], texto: (t['texto'] ?? '').trim() || null })),
      imagen_id: this.foto?.id ?? null,
    });
  }
}
