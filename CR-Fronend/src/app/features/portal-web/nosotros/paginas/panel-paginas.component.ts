import { Component, OnInit, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { accionesDeEstado, PanelDeLista } from '../../compartido/panel-de-lista';
import { ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { EditorBloquesComponent } from '../bloques/editor-bloques.component';
import { EstadoPortal, NavegacionPortal, PAGINAS_DE_NOSOTROS, PaginaNosotros } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/** a-z, 0-9 y guiones: la dirección /nosotros/{slug}. */
const SLUG = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/** «Institución» → «institucion»: el id con que el menú guarda un grupo. */
const comoId = (texto: string) =>
  texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

/**
 * Nosotros › Subpáginas: el título y la frase de cada una, la frase de su
 * tarjeta en el índice y su lugar en el menú (contrato 2.13: el grupo, el
 * nombre y el orden). Su contenido (los bloques) se edita en su propio
 * modal, con el botón de documento de la fila.
 *
 * El portal abre solo las que están en un grupo del menú y publicadas. Los
 * grupos se crean en Menú y títulos. La dirección puede ser cualquiera; las
 * 16 de cata.edu.pe se sugieren y, al elegir una, se proponen su título, su
 * grupo y su nombre.
 */
@Component({
  selector: 'app-panel-paginas',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, EditorBloquesComponent,
    CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-paginas.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelPaginasComponent extends PanelDeLista<PaginaNosotros> implements OnInit {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.paginas;
  protected readonly nombre = { singular: 'página', femenino: true };
  /** Al editar se vuelve a pedir la lista: van agrupadas por su grupo del menú. */
  protected override readonly recargarAlEditar = true;

  readonly accionesDePagina: AccionPersonalizada<PaginaNosotros>[] = [
    { id: 'contenido', titulo: 'Editar el contenido (sus bloques)', icono: 'description' },
    ...accionesDeEstado<PaginaNosotros>(),
  ];

  /** Los grupos del menú (ficha `navegacion`), para elegir en cuál va. */
  grupos: { id: string; titulo: string }[] = [];

  /** La página cuyo contenido se edita; null = el editor está cerrado. */
  paginaEnEdicion: PaginaNosotros | null = null;

  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  // El grupo, el nombre en el menú, la dirección y los bloques van bajo el título: así caben las cuatro acciones.
  columnas: ColumnaTabla<PaginaNosotros>[] = [
    this.columnaDeOrden(),
    { campo: 'titulo', header: 'Subpágina' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    slug: ['', [Validators.required, Validators.maxLength(60), Validators.pattern(SLUG)]],
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(160)]],
    resumen: ['', [Validators.maxLength(140)]],
    grupo: [''],
    nombreEnMenu: ['', [Validators.maxLength(40)]],
    estado: ['borrador' as EstadoPortal],
  });

  override ngOnInit(): void {
    super.ngOnInit();
    this.portalWebService.getFicha<NavegacionPortal>('navegacion').subscribe({
      next: (res) => (this.grupos = (res.data.contenido?.grupos ?? []).filter((g): g is { id: string; titulo: string } => !!g.id)),
    });
  }

  /** Las 16 de cata.edu.pe que todavía no tienen página, como sugerencia. */
  get libres(): typeof PAGINAS_DE_NOSOTROS {
    const usadas = new Set(this.filas.map((p) => p.slug));
    return PAGINAS_DE_NOSOTROS.filter((p) => !usadas.has(p.slug));
  }

  /** Con grupo, el nombre en el menú es obligatorio. */
  get faltaNombre(): boolean {
    const v = this.form.getRawValue();
    return !!v.grupo && !(v.nombreEnMenu ?? '').trim();
  }

  protected describir(p: PaginaNosotros): string {
    return `la página «${p.nombre ?? p.titulo}» (su dirección dejará de abrir)`;
  }

  /**
   * Las publicadas que el portal no abre (fuera del menú) o que abren vacías
   * (sin bloques) suben a Pendientes.
   */
  protected override resumir(): ResumenDeSeccion | null {
    const r = super.resumir();
    if (!r) return r;
    const publicadas = this.filas.filter((p) => p.estado === 'publicado');
    const fuera = publicadas.filter((p) => !p.grupo).length;
    const vacias = publicadas.filter((p) => !p.bloques).length;
    if (fuera) r.pendientes.push({ cuantos: fuera, texto: fuera === 1 ? 'subpágina publicada fuera del menú: no abre' : 'subpáginas publicadas fuera del menú: no abren' });
    if (vacias) r.pendientes.push({ cuantos: vacias, texto: vacias === 1 ? 'subpágina publicada sin contenido' : 'subpáginas publicadas sin contenido' });
    if (fuera || vacias) {
      r.aviso = true;
      r.estado = `${r.estado.split(' · ')[0]} · ${fuera + vacias} por revisar`;
    }
    return r;
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ slug: '', titulo: '', bajada: '', resumen: '', grupo: '', nombreEnMenu: '', estado: 'borrador' });
    this.form.controls.slug.enable();
    this.modalVisible = true;
  }

  editar(p: PaginaNosotros): void {
    this.editando = p;
    this.form.reset({
      slug: p.slug, titulo: p.titulo, bajada: p.bajada ?? '', resumen: p.resumen ?? '',
      grupo: p.grupo ?? '', nombreEnMenu: p.nombre ?? '', estado: p.estado,
    });
    this.form.controls.slug.disable();
    this.modalVisible = true;
  }

  override alAccionar(evento: { accion: string; fila: PaginaNosotros }): void {
    if (evento.accion === 'contenido') {
      this.paginaEnEdicion = evento.fila;
      return;
    }
    super.alAccionar(evento);
  }

  /** Al cerrar el editor: si se guardó, la tabla vuelve a contar los bloques. */
  alCerrarEditor(guardo: boolean): void {
    this.paginaEnEdicion = null;
    if (guardo) this.cargar();
  }

  /** Si la dirección es una de las 16 de siempre, propone su título, su grupo y su nombre. */
  alEscribirSlug(): void {
    const elegida = PAGINAS_DE_NOSOTROS.find((p) => p.slug === this.form.controls.slug.value);
    if (!elegida) return;
    const c = this.form.controls;
    if (!c.titulo.value) c.titulo.setValue(elegida.nombre);
    if (!c.nombreEnMenu.value) c.nombreEnMenu.setValue(elegida.nombre);
    if (!c.grupo.value) c.grupo.setValue(this.grupos.find((g) => g.id === comoId(elegida.grupo))?.id ?? '');
  }

  /** Al elegir un grupo sin nombre en el menú, el título sirve de nombre. */
  alElegirGrupo(): void {
    const c = this.form.controls;
    if (c.grupo.value && !c.nombreEnMenu.value) c.nombreEnMenu.setValue((c.titulo.value ?? '').slice(0, 40));
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid || this.faltaNombre) {
      this.form.markAllAsTouched();
      this.cuerpo?.irAlPrimerError();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      ...(this.editando ? {} : { slug: v.slug }),
      titulo: v.titulo,
      bajada: (v.bajada ?? '').trim() || null,
      resumen: (v.resumen ?? '').trim() || null,
      grupo: v.grupo || null,
      ...(v.grupo ? { nombre: (v.nombreEnMenu ?? '').trim() } : {}),
      estado: v.estado,
    });
  }
}
