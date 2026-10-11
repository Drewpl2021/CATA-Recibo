import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { mostrarErrorDelPanel, urlParaVer } from '../../compartido/panel';
import { accionesDeEstado, PanelDeLista } from '../../compartido/panel-de-lista';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import {
  CategoriaProyectoPortal, ETAPAS_DE_PROYECTO, EstadoPortal, ImagenPortal, ProyectoPortal, SeccionProyecto,
} from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

const SLUG = /^[a-z0-9]+(-[a-z0-9]+)*$/;
const MAX_PARRAFOS = 6;
const MAX_SECCIONES = 8;
const MAX_ITEMS = 12;
const MAX_GALERIA = 12;

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
 * Proyectos › Proyectos: cada uno con su tarjeta (título, lema, resumen,
 * categoría, foto) y su página de detalle (párrafos, secciones y galería).
 * Hasta 24 publicados, en este orden.
 *
 * La dirección del detalle (/proyectos/{slug}) se arma con el título; se
 * puede corregir mientras está en borrador y queda fija al publicarlo.
 * La galería se edita aparte, con su propio botón en la fila.
 */
@Component({
  selector: 'app-panel-proyectos',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent],
  templateUrl: './panel-proyectos.component.html',
})
export class PanelProyectosComponent extends PanelDeLista<ProyectoPortal> {
  private fb = inject(FormBuilder);

  /** Las categorías para el selector (las carga el panel de categorías). */
  @Input() categorias: CategoriaProyectoPortal[] = [];

  protected readonly recurso = RECURSOS.proyectos;
  protected readonly nombre = { singular: 'proyecto', femenino: false };

  readonly etapas = ETAPAS_DE_PROYECTO;
  readonly maxParrafos = MAX_PARRAFOS;
  readonly maxSecciones = MAX_SECCIONES;
  readonly maxItems = MAX_ITEMS;
  readonly maxGaleria = MAX_GALERIA;
  readonly ver = urlParaVer;

  override readonly acciones: AccionPersonalizada<ProyectoPortal>[] = [
    { id: 'galeria', titulo: 'Galería de fotos', icono: 'camera' },
    ...accionesDeEstado<ProyectoPortal>(),
  ];

  foto: ImagenPortal | null = null;

  columnas: ColumnaTabla<ProyectoPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'titulo', header: 'Proyecto' },
    { campo: 'categoria_id', header: 'Categoría', ancho: '15%', formatear: (id) => this.categorias.find((c) => c.id === id)?.nombre ?? '—' },
    { campo: 'slug', header: 'Dirección', ancho: '18%', formatear: (s) => `/proyectos/${s}` },
    { campo: 'galeria', header: 'Fotos', ancho: '7%', formatear: (g: ImagenPortal[]) => String(g.length) },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(80)]],
    slug: ['', [Validators.maxLength(60), Validators.pattern(SLUG)]],
    lema: ['', [Validators.maxLength(100)]],
    resumen: ['', [Validators.required, Validators.maxLength(240)]],
    categoria_id: ['', [Validators.required]],
    etapa: [''],
    fecha: [''],
    niveles: ['', [validarLineas(0, 6, 40)]],
    estado: ['borrador' as EstadoPortal],
    descripcion: this.fb.array<FormControl<string | null>>([]),
    secciones: this.fb.array<FormGroup>([]),
  });

  get descripcion(): FormArray<FormControl<string | null>> {
    return this.form.controls.descripcion;
  }

  get secciones(): FormArray<FormGroup> {
    return this.form.controls.secciones;
  }

  itemsDe(i: number): FormArray<FormGroup> {
    return this.secciones.at(i).get('items') as FormArray<FormGroup>;
  }

  /** Publicado, la dirección ya no cambia. */
  get slugFijo(): boolean {
    return this.editando?.estado === 'publicado';
  }

  protected describir(p: ProyectoPortal): string {
    return `el proyecto «${p.titulo}» con su galería`;
  }

  // ───────── Armar el formulario ─────────

  private parrafo(texto = '') {
    return this.fb.control(texto, [Validators.required, Validators.maxLength(600)]);
  }

  private item(i?: { titulo: string | null; texto: string }): FormGroup {
    return this.fb.group({
      titulo: [i?.titulo ?? '', [Validators.maxLength(60)]],
      texto: [i?.texto ?? '', [Validators.required, Validators.maxLength(240)]],
    });
  }

  private seccion(s?: SeccionProyecto): FormGroup {
    return this.fb.group({
      titulo: [s?.titulo ?? '', [Validators.required, Validators.maxLength(60)]],
      tipo: [s?.tipo ?? 'lista'],
      items: this.fb.array((s?.items.length ? s.items : [undefined]).map((i) => this.item(i))),
    });
  }

  private llenar(p: ProyectoPortal | null): void {
    this.form.reset({
      titulo: p?.titulo ?? '', slug: p?.slug ?? '', lema: p?.lema ?? '', resumen: p?.resumen ?? '',
      categoria_id: p?.categoria_id ?? '', etapa: p?.etapa ?? '', fecha: p?.fecha ?? '',
      niveles: (p?.niveles ?? []).join('\n'), estado: p?.estado ?? 'borrador',
    });
    this.descripcion.clear();
    this.secciones.clear();
    (p?.descripcion.length ? p.descripcion : ['']).forEach((t) => this.descripcion.push(this.parrafo(t)));
    (p?.secciones ?? []).forEach((s) => this.secciones.push(this.seccion(s)));
    this.form.markAsPristine();
    this.foto = p?.imagen ?? null;
  }

  nuevo(): void {
    this.editando = null;
    this.llenar(null);
    this.modalVisible = true;
  }

  editar(p: ProyectoPortal): void {
    this.editando = p;
    this.llenar(p);
    this.modalVisible = true;
  }

  // ───────── Párrafos, secciones y elementos ─────────

  agregarParrafo(): void {
    if (this.descripcion.length < MAX_PARRAFOS) this.descripcion.push(this.parrafo());
  }

  quitarParrafo(i: number): void {
    if (this.descripcion.length > 1) this.descripcion.removeAt(i);
  }

  moverParrafo(i: number, d: -1 | 1): void {
    moverEn(this.descripcion, i, d);
  }

  agregarSeccion(): void {
    if (this.secciones.length < MAX_SECCIONES) this.secciones.push(this.seccion());
  }

  quitarSeccion(i: number): void {
    this.secciones.removeAt(i);
  }

  moverSeccion(i: number, d: -1 | 1): void {
    moverEn(this.secciones, i, d);
  }

  agregarItem(s: number): void {
    if (this.itemsDe(s).length < MAX_ITEMS) this.itemsDe(s).push(this.item());
  }

  quitarItem(s: number, i: number): void {
    if (this.itemsDe(s).length > 1) this.itemsDe(s).removeAt(i);
  }

  moverItem(s: number, i: number, d: -1 | 1): void {
    moverEn(this.itemsDe(s), i, d);
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
    const datos: Record<string, unknown> = {
      titulo: v.titulo,
      lema: v.lema || null,
      resumen: v.resumen,
      categoria_id: v.categoria_id,
      etapa: v.etapa || null,
      fecha: v.fecha || null,
      niveles: lineas(v.niveles),
      imagen_id: this.foto?.id ?? null,
      descripcion: v.descripcion.map((p) => (p ?? '').trim()),
      secciones: v.secciones.map((s) => ({
        titulo: s['titulo'],
        tipo: s['tipo'],
        items: (s['items'] as { titulo: string; texto: string }[]).map((i) => ({ titulo: i.titulo || null, texto: i.texto })),
      })),
      estado: v.estado,
    };
    // La dirección: al crear, vacía = se arma con el título; publicada, no se manda.
    if (!this.slugFijo && v.slug) datos['slug'] = v.slug;

    this.enviar(datos);
  }

  // ───────── Galería ─────────

  galeriaVisible = false;
  guardandoGaleria = false;
  galeriaDe: ProyectoPortal | null = null;
  fotos: ImagenPortal[] = [];
  /** Cambia para volver a crear el selector vacío después de cada foto. */
  vueltaSelector = 0;

  override alAccionar(evento: { accion: string; fila: ProyectoPortal }): void {
    if (evento.accion !== 'galeria') {
      super.alAccionar(evento);
      return;
    }
    this.galeriaDe = evento.fila;
    this.fotos = [...evento.fila.galeria];
    this.vueltaSelector++;
    this.galeriaVisible = true;
  }

  alSubirFoto(imagen: ImagenPortal | null): void {
    if (!imagen || this.fotos.length >= MAX_GALERIA || this.fotos.some((f) => f.id === imagen.id)) return;
    this.fotos = [...this.fotos, imagen];
    this.vueltaSelector++;
  }

  moverFoto(i: number, d: -1 | 1): void {
    const j = i + d;
    if (j < 0 || j >= this.fotos.length) return;
    const fotos = [...this.fotos];
    [fotos[i], fotos[j]] = [fotos[j], fotos[i]];
    this.fotos = fotos;
  }

  quitarFoto(i: number): void {
    this.fotos = this.fotos.filter((_, k) => k !== i);
  }

  cerrarGaleria(): void {
    this.galeriaVisible = false;
    this.galeriaDe = null;
  }

  guardarGaleria(): void {
    if (!this.galeriaDe) return;

    this.guardandoGaleria = true;
    this.portalWebService.guardarGaleriaDeProyecto(this.galeriaDe.id, this.fotos.map((f) => f.id)).subscribe({
      next: () => {
        this.guardandoGaleria = false;
        this.toastService.success('Galería guardada', 'El portal la mostrará en hasta un minuto.');
        this.cerrarGaleria();
        this.cargar();
      },
      error: (err: HttpErrorResponse) => {
        this.guardandoGaleria = false;
        mostrarErrorDelPanel(this.toastService, err, 'No se pudo guardar la galería.');
      },
    });
  }
}
