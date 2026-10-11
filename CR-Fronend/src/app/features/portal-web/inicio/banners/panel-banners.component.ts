import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { accionDelGrupo, EditorAccionComponent, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { BannerPortal, CATEGORIAS_DE_BANNER, EstadoPortal, ImagenPortal, PuntoFocal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Inicio › Carrusel: los afiches de la portada, hasta 8 publicados, del más
 * reciente al más antiguo. Lo nuevo entra arriba.
 *
 * La imagen de escritorio es obligatoria (1900:700) y lleva su punto focal:
 * el que queda a la vista si el portal tiene que recortarla. La de celular
 * (4:5) es opcional, pero el portal solo la usa si TODOS los banners la traen.
 */
@Component({
  selector: 'app-panel-banners',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    SelectorImagenComponent, EditorAccionComponent,
  ],
  templateUrl: './panel-banners.component.html',
})
export class PanelBannersComponent extends PanelDeLista<BannerPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.banners;
  protected readonly nombre = { singular: 'banner', femenino: false };

  readonly categorias = CATEGORIAS_DE_BANNER;

  imagen: ImagenPortal | null = null;
  imagenMovil: ImagenPortal | null = null;
  foco: PuntoFocal | null = null;
  intentoGuardar = false;

  columnas: ColumnaTabla<BannerPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'titulo', header: 'Banner' },
    { campo: 'categoria', header: 'Categoría', ancho: '11%', formatear: (c) => this.categorias.find((x) => x.valor === c)?.nombre ?? c },
    { campo: 'fecha', header: 'Fecha', ancho: '10%', formatear: (f: string | null) => (f ? f.split('-').reverse().join('/') : '—') },
    { campo: 'imagen_movil_id', header: 'Celular', ancho: '9%', formatear: (m) => (m ? 'Sí' : 'No') },
    { campo: 'accion', header: 'Botón', ancho: '15%', formatear: (a) => a?.etiqueta ?? 'Sin botón' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    titulo: ['', [Validators.required, Validators.maxLength(80)]],
    categoria: ['', [Validators.required]],
    fecha: [''],
    texto_en_imagen: [false],
    lado_accion: ['derecha' as 'izquierda' | 'derecha'],
    estado: ['borrador' as EstadoPortal],
    accion: grupoDeAccion(this.fb),
  });

  protected describir(b: BannerPortal): string {
    return `el banner «${b.titulo}»`;
  }

  /** Los publicados sin imagen de celular: con uno solo, el portal no usa ninguna. */
  get publicadosSinMovil(): number {
    return this.filas.filter((b) => b.estado === 'publicado' && !b.imagen_movil_id).length;
  }

  nuevo(): void {
    this.editando = null;
    this.imagen = null;
    this.imagenMovil = null;
    this.foco = null;
    this.intentoGuardar = false;
    this.form.reset({ titulo: '', categoria: '', fecha: '', texto_en_imagen: false, lado_accion: 'derecha', estado: 'borrador' });
    llenarAccion(this.form.controls.accion, null);
    this.modalVisible = true;
  }

  editar(b: BannerPortal): void {
    this.editando = b;
    this.imagen = b.imagen;
    this.imagenMovil = b.imagen_movil;
    this.foco = b.foco_x === null || b.foco_y === null ? null : { x: b.foco_x, y: b.foco_y };
    this.intentoGuardar = false;
    this.form.reset({
      titulo: b.titulo, categoria: b.categoria, fecha: b.fecha ?? '', texto_en_imagen: b.texto_en_imagen,
      lado_accion: b.lado_accion, estado: b.estado,
    });
    llenarAccion(this.form.controls.accion, b.accion);
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    this.intentoGuardar = true;
    if (this.form.invalid || !this.imagen) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      titulo: v.titulo,
      categoria: v.categoria,
      fecha: v.fecha || null,
      imagen_id: this.imagen.id,
      imagen_movil_id: this.imagenMovil?.id ?? null,
      foco_x: this.foco ? Math.round(this.foco.x) : null,
      foco_y: this.foco ? Math.round(this.foco.y) : null,
      texto_en_imagen: !!v.texto_en_imagen,
      lado_accion: v.lado_accion,
      accion: accionDelGrupo(this.form.controls.accion),
      estado: v.estado,
    });
  }
}
