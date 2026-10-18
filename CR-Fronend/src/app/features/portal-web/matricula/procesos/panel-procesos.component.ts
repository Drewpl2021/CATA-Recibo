import { Component, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { WizardComponent } from '../../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../../shared/components/wizard/wizard.models';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, ListaMatricula, PasoMatricula, ProcesoPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const MAX_PASOS = 8;
const MAX_LISTAS = 4;

/**
 * Matrícula › Procesos: uno por tipo de estudiante (nuevos, regulares…), con
 * sus pasos en orden y sus listas de requisitos y documentos. Hasta 4
 * publicados; con dos o más, el portal los muestra en pestañas.
 *
 * Los pasos y las listas se editan dentro del proceso y se guardan con él.
 * El portal numera los pasos: en el título no va «1er paso». El modal va en
 * tres pasos: el proceso, sus pasos y sus listas.
 */
@Component({
  selector: 'app-panel-procesos',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    WizardComponent, CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-procesos.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelProcesosComponent extends PanelDeLista<ProcesoPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.procesos;
  protected readonly nombre = { singular: 'proceso', femenino: false };

  readonly maxPasos = MAX_PASOS;
  readonly maxListas = MAX_LISTAS;

  readonly pasosWizard: PasoWizard[] = [
    { id: 'proceso', titulo: 'El proceso', icono: 'clipboard_check', campos: ['nombre', 'resumen'] },
    { id: 'pasos', titulo: 'Pasos', icono: 'layers', campos: ['pasos'] },
    { id: 'listas', titulo: 'Requisitos', icono: 'description', campos: ['listas'] },
  ];

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;
  pasoActual = 0;

  // Los pasos y las listas van bajo el nombre: así caben las acciones.
  columnas: ColumnaTabla<ProcesoPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Para quién' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    resumen: ['', [Validators.maxLength(160)]],
    estado: ['borrador' as EstadoPortal],
    pasos: this.fb.array<FormGroup>([]),
    listas: this.fb.array<FormGroup>([]),
  });

  get pasos(): FormArray<FormGroup> {
    return this.form.controls.pasos;
  }

  get listas(): FormArray<FormGroup> {
    return this.form.controls.listas;
  }

  protected describir(p: ProcesoPortal): string {
    return `el proceso «${p.nombre}» con sus pasos y listas`;
  }

  /** «3 pasos · Requisitos, Documentos», bajo el nombre en la tabla. */
  detalle(p: ProcesoPortal): string {
    const partes = [];
    if (p.pasos.length) partes.push(p.pasos.length === 1 ? '1 paso' : `${p.pasos.length} pasos`);
    partes.push(p.listas.length ? p.listas.map((l: ListaMatricula) => l.titulo).join(', ') : 'sin listas');
    return partes.join(' · ');
  }

  private paso(p?: PasoMatricula): FormGroup {
    return this.fb.group({
      titulo: [p?.titulo ?? '', [Validators.required, Validators.maxLength(60)]],
      detalle: [p?.detalle ?? '', [Validators.maxLength(240)]],
    });
  }

  private lista(l?: ListaMatricula): FormGroup {
    return this.fb.group({
      titulo: [l?.titulo ?? '', [Validators.required, Validators.maxLength(60)]],
      items: [(l?.items ?? []).join('\n'), [validarLineas(1, 15, 240, false)]],
    });
  }

  private llenar(p: ProcesoPortal | null): void {
    this.form.reset({ nombre: p?.nombre ?? '', resumen: p?.resumen ?? '', estado: p?.estado ?? 'borrador' });
    this.pasos.clear();
    this.listas.clear();
    (p?.pasos ?? []).forEach((x) => this.pasos.push(this.paso(x)));
    (p?.listas ?? []).forEach((x) => this.listas.push(this.lista(x)));
  }

  nuevo(): void {
    this.editando = null;
    this.llenar(null);
    this.pasoActual = 0;
    this.modalVisible = true;
  }

  editar(p: ProcesoPortal): void {
    this.editando = p;
    this.llenar(p);
    this.pasoActual = 0;
    this.modalVisible = true;
  }

  /**
   * El wizard marca como tocada la lista entera, no cada paso: aquí se marcan
   * sus campos, para que se vea cuál falta antes de seguir.
   */
  siguiente(): void {
    this.pasosWizard[this.pasoActual].campos?.forEach((c) => this.form.get(c)?.markAllAsTouched());
    const antes = this.pasoActual;
    this.wizard?.siguiente();
    if (this.pasoActual === antes) this.cuerpo?.irAlPrimerError();
  }

  // ───────── Pasos y listas dentro del modal ─────────

  agregarPaso(): void {
    if (this.pasos.length < MAX_PASOS) this.pasos.push(this.paso());
  }

  quitarPaso(i: number): void {
    this.pasos.removeAt(i);
    this.form.markAsDirty();
  }

  moverPaso(i: number, direccion: -1 | 1): void {
    const j = i + direccion;
    if (j < 0 || j >= this.pasos.length) return;
    const paso = this.pasos.at(i);
    this.pasos.removeAt(i);
    this.pasos.insert(j, paso);
    this.form.markAsDirty();
  }

  agregarLista(): void {
    if (this.listas.length < MAX_LISTAS) this.listas.push(this.lista());
  }

  quitarLista(i: number): void {
    this.listas.removeAt(i);
    this.form.markAsDirty();
  }

  cuantosItems(i: number): number {
    return lineas(this.listas.at(i).value['items']).length;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  /** Si algo falta, el wizard vuelve al primer paso con error. */
  guardar(): void {
    const conError = this.pasosWizard.findIndex((_, i) => !this.wizard?.validarPaso(i));
    if (conError >= 0 || this.form.invalid) {
      this.form.markAllAsTouched();
      this.pasoActual = Math.max(conError, 0);
      this.cuerpo?.irAlPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      nombre: v.nombre,
      resumen: v.resumen || null,
      estado: v.estado,
      pasos: v.pasos.map((p) => ({ titulo: p['titulo'], detalle: p['detalle'] || null })),
      listas: v.listas.map((l) => ({ titulo: l['titulo'], items: lineas(l['items']) })),
    });
  }
}
