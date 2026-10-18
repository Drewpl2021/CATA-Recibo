import { Component, EventEmitter, Output, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { WizardComponent } from '../../../../shared/components/wizard/wizard.component';
import { PasoWizard } from '../../../../shared/components/wizard/wizard.models';
import { accionDelGrupo, EditorAccionComponent, grupoDeAccion, llenarAccion } from '../../compartido/editor-accion/editor-accion.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { urlParaVer } from '../../compartido/panel';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
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
 *
 * El modal va en tres pasos (afiches, datos y botón) con el wizard del alta
 * de personal: al crear se avanza paso a paso; al editar se puede guardar
 * desde cualquiera. La imagen de escritorio vive también en el formulario
 * (`imagen_id`) para que el wizard la valide como un campo más.
 */
@Component({
  selector: 'app-panel-banners',
  standalone: true,
  imports: [
    CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent,
    SelectorImagenComponent, EditorAccionComponent, WizardComponent, CuerpoDesplazableComponent,
  ],
  templateUrl: './panel-banners.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelBannersComponent extends PanelDeLista<BannerPortal> {
  private fb = inject(FormBuilder);

  /** Cómo está el carrusel, para el índice de Inicio. */
  @Output() resumen = new EventEmitter<ResumenDeSeccion>();
  @ViewChild('wizard') private wizard?: WizardComponent;
  @ViewChild('cuerpo') private cuerpo?: CuerpoDesplazableComponent;

  protected readonly recurso = RECURSOS.banners;
  protected readonly nombre = { singular: 'banner', femenino: false };

  readonly categorias = CATEGORIAS_DE_BANNER;

  imagen: ImagenPortal | null = null;
  imagenMovil: ImagenPortal | null = null;
  foco: PuntoFocal | null = null;
  paso = 0;

  readonly pasos: PasoWizard[] = [
    { id: 'afiches', titulo: 'Afiches', icono: 'camera', campos: ['imagen_id'] },
    { id: 'datos', titulo: 'Datos', icono: 'description', campos: ['titulo', 'categoria'] },
    { id: 'boton', titulo: 'Botón', icono: 'arrow_right', campos: ['accion'] },
  ];

  columnas: ColumnaTabla<BannerPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'titulo', header: 'Banner' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    imagen_id: ['', [Validators.required]],
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

  protected override alCargar(): void {
    const publicados = this.publicados;
    const borradores = this.filas.length - publicados;
    const sinMovil = this.publicadosSinMovil;
    const partes = [cuantos(publicados, 'publicado', 'publicados')];
    if (sinMovil) partes.push(`${sinMovil} sin celular`);
    else if (borradores) partes.push(cuantos(borradores, 'borrador', 'borradores'));

    this.resumen.emit({
      estado: this.filas.length ? partes.join(' · ') : 'Sin banners',
      aviso: sinMovil > 0,
      pendientes: sinMovil
        ? [{ cuantos: sinMovil, texto: sinMovil === 1 ? 'banner publicado sin afiche para celular' : 'banners publicados sin afiche para celular' }]
        : [],
    });
  }

  /** La miniatura de la tabla: la imagen subida, por la ruta del panel. */
  ver(url: string): string {
    return urlParaVer(url);
  }

  /** La segunda línea de la celda: categoría, fecha y botón. */
  detalle(b: BannerPortal): string {
    const fecha = b.fecha ? b.fecha.split('-').reverse().join('/') : 'sin fecha';
    return `${this.nombreDeCategoria(b.categoria)} · ${fecha} · ${b.accion ? `botón «${b.accion.etiqueta}»` : 'sin botón'}`;
  }

  private nombreDeCategoria(valor: string): string {
    return this.categorias.find((x) => x.valor === valor)?.nombre ?? valor;
  }

  alCambiarImagen(imagen: ImagenPortal | null): void {
    this.imagen = imagen;
    this.form.controls.imagen_id.setValue(imagen?.id ?? '');
    this.form.controls.imagen_id.markAsTouched();
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
    this.paso = 0;
    this.form.reset({ imagen_id: '', titulo: '', categoria: '', fecha: '', texto_en_imagen: false, lado_accion: 'derecha', estado: 'borrador' });
    llenarAccion(this.form.controls.accion, null);
    this.modalVisible = true;
  }

  editar(b: BannerPortal): void {
    this.editando = b;
    this.imagen = b.imagen;
    this.imagenMovil = b.imagen_movil;
    this.foco = b.foco_x === null || b.foco_y === null ? null : { x: b.foco_x, y: b.foco_y };
    this.paso = 0;
    this.form.reset({
      imagen_id: b.imagen?.id ?? b.imagen_id ?? '',
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

  /** Guarda desde cualquier paso; si algo falta, vuelve al primer paso con errores. */
  guardar(): void {
    const conError = this.pasos.findIndex((_, i) => !this.wizard?.validarPaso(i));
    if (conError >= 0 || this.form.invalid || !this.imagen) {
      this.form.markAllAsTouched();
      this.paso = conError >= 0 ? conError : 0;
      this.cuerpo?.irAlPrimerError();
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
