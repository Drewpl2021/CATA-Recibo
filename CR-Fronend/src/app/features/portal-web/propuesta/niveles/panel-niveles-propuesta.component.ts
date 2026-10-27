import { Component, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { WizardComponent } from '../../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../../shared/components/wizard/wizard.models';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { moverEn, urlParaVer } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { EstadoPortal, ImagenPortal, NivelPropuestaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const MAX_PARRAFOS = 6;
const MAX_TALLERES = 12;

/**
 * Propuesta Educativa › Niveles: hasta 6, cada uno con su descripción, sus
 * rasgos, sus talleres y una foto 3:2. Son una lista propia: los niveles de
 * Inicio se editan en Inicio. El modal va en tres pasos: el nivel con su
 * foto, la descripción y los rasgos, y los talleres.
 */
@Component({
  selector: 'app-panel-niveles-propuesta',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent,
    WizardComponent, CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-niveles-propuesta.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelNivelesPropuestaComponent extends PanelDeLista<NivelPropuestaPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.nivelesPropuesta;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  readonly maxParrafos = MAX_PARRAFOS;
  readonly maxTalleres = MAX_TALLERES;

  foto: ImagenPortal | null = null;
  readonly ver = urlParaVer;

  readonly pasosWizard: PasoWizard[] = [
    { id: 'nivel', titulo: 'El nivel', icono: 'view_module', campos: ['nombre', 'rango'] },
    { id: 'descripcion', titulo: 'Descripción', icono: 'description', campos: ['descripcion', 'rasgos'] },
    { id: 'talleres', titulo: 'Talleres', icono: 'layers', campos: ['talleres'] },
  ];

  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;
  pasoActual = 0;

  // El rango, los rasgos y los talleres van bajo el nombre: así caben las acciones.
  columnas: ColumnaTabla<NivelPropuestaPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Nivel' },
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

  /** «1.° a 6.° grado · 4 rasgos · 3 talleres», bajo el nombre en la tabla. */
  detalle(n: NivelPropuestaPortal): string {
    const partes = [n.rango, n.rasgos.length ? `${n.rasgos.length} ${n.rasgos.length === 1 ? 'rasgo' : 'rasgos'}` : null,
      n.talleres.length ? `${n.talleres.length} ${n.talleres.length === 1 ? 'taller' : 'talleres'}` : 'sin talleres'];
    return partes.filter(Boolean).join(' · ');
  }

  /** Los publicados sin foto: su bloque se ve sin imagen en el portal. */
  protected override resumir(): ResumenDeSeccion | null {
    const r = super.resumir();
    const sinFoto = this.filas.filter((n) => n.estado === 'publicado' && !n.imagen_id).length;
    if (r && sinFoto) {
      r.estado += ` · ${sinFoto} sin foto`;
      r.aviso = true;
      r.pendientes = [{ cuantos: sinFoto, texto: sinFoto === 1 ? 'nivel de Propuesta publicado sin foto' : 'niveles de Propuesta publicados sin foto' }];
    }
    return r;
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
    this.pasoActual = 0;
    this.modalVisible = true;
  }

  editar(n: NivelPropuestaPortal): void {
    this.editando = n;
    this.llenar(n);
    this.pasoActual = 0;
    this.modalVisible = true;
  }

  /**
   * El wizard marca como tocada la lista entera, no cada párrafo: aquí se
   * marcan sus campos, para que se vea cuál falta antes de seguir.
   */
  siguiente(): void {
    this.pasosWizard[this.pasoActual].campos?.forEach((c) => this.form.get(c)?.markAllAsTouched());
    const antes = this.pasoActual;
    this.wizard?.siguiente();
    if (this.pasoActual === antes) this.cuerpo?.irAlPrimerError();
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
      rango: v.rango || null,
      estado: v.estado,
      descripcion: v.descripcion.map((p) => (p ?? '').trim()),
      rasgos: lineas(v.rasgos),
      talleres: v.talleres.map((t) => ({ titulo: t['titulo'], texto: (t['texto'] ?? '').trim() || null })),
      imagen_id: this.foto?.id ?? null,
    });
  }
}
