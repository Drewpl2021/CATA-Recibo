import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { CuerpoDesplazableComponent } from '../../compartido/cuerpo-desplazable/cuerpo-desplazable.component';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { SelectorImagenComponent } from '../../compartido/selector-imagen/selector-imagen.component';
import { EstadoPortal, ImagenPortal, NoticiaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';
import { hoyEnLima } from '../../compartido/panel';

const HTTPS = /^https:\/\/\S+$/;

/** Un enlace va completo (texto y dirección https) o no va. */
function enlaceCompleto(grupo: AbstractControl): ValidationErrors | null {
  const { enlaceEtiqueta, enlaceUrl } = grupo.value as { enlaceEtiqueta: string; enlaceUrl: string };
  if (!enlaceEtiqueta && !enlaceUrl) return null;
  if (!enlaceUrl || !HTTPS.test(enlaceUrl)) return { enlaceUrl: true };
  if (!enlaceEtiqueta) return { enlaceEtiqueta: true };
  return null;
}

/**
 * Inicio › Noticias y comunicados: lo que publica el colegio. Van siempre por
 * fecha, de la más reciente a la más antigua (no se ordenan a mano). Inicio
 * muestra las últimas; el resto, por páginas.
 *
 * La dirección (slug) de cada una se arma con su título al crearla y no
 * cambia: será la de su futura página propia.
 */
@Component({
  selector: 'app-panel-noticias',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent, SelectorImagenComponent, CuerpoDesplazableComponent],
  templateUrl: './panel-noticias.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelNoticiasComponent extends PanelDeLista<NoticiaPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.noticias;
  protected readonly nombre = { singular: 'noticia', femenino: true };

  foto: ImagenPortal | null = null;

  columnas: ColumnaTabla<NoticiaPortal>[] = [
    {
      campo: 'tipo', header: 'Tipo', tipo: 'badge', ancho: '12%',
      formatear: (t) => (t === 'comunicado' ? 'Comunicado' : 'Noticia'),
      badgeSeveridad: (t) => (t === 'comunicado' ? 'warning' : 'info'),
    },
    { campo: 'titulo', header: 'Título' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group(
    {
      tipo: ['noticia' as 'noticia' | 'comunicado'],
      titulo: ['', [Validators.required, Validators.maxLength(120)]],
      resumen: ['', [Validators.maxLength(280)]],
      fecha: ['', [Validators.required]],
      enlaceEtiqueta: ['', [Validators.maxLength(40)]],
      enlaceUrl: [''],
      enlaceExterno: [true],
      estado: ['borrador' as EstadoPortal],
    },
    { validators: enlaceCompleto },
  );

  protected describir(n: NoticiaPortal): string {
    return `${n.tipo === 'comunicado' ? 'el comunicado' : 'la noticia'} «${n.titulo}»`;
  }

  protected override resumir(): ResumenDeSeccion {
    const borradores = this.filas.length - this.publicados;
    return {
      estado: this.filas.length
        ? cuantos(this.publicados, 'publicada', 'publicadas') + (borradores ? ` · ${cuantos(borradores, 'borrador', 'borradores')}` : '')
        : 'Sin noticias',
      aviso: false,
      pendientes: borradores
        ? [{ cuantos: borradores, texto: borradores === 1 ? 'noticia en borrador' : 'noticias en borrador' }]
        : [],
    };
  }

  /** La segunda línea de la celda: fecha y lo que trae. */
  detalle(n: NoticiaPortal): string {
    const partes = [n.fecha.split('-').reverse().join('/')];
    if (n.imagen_id) partes.push('con foto');
    if (n.enlace) partes.push('con enlace');
    return partes.join(' · ');
  }

  nueva(): void {
    this.editando = null;
    this.foto = null;
    this.form.reset({ tipo: 'noticia', titulo: '', resumen: '', fecha: hoyEnLima(), enlaceEtiqueta: '', enlaceUrl: '', enlaceExterno: true, estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(n: NoticiaPortal): void {
    this.editando = n;
    this.foto = n.imagen;
    this.form.reset({
      tipo: n.tipo, titulo: n.titulo, resumen: n.resumen ?? '', fecha: n.fecha,
      enlaceEtiqueta: n.enlace?.etiqueta ?? '', enlaceUrl: n.enlace?.url ?? '', enlaceExterno: n.enlace?.externo ?? true,
      estado: n.estado,
    });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  errorDeEnlace(clave: 'enlaceUrl' | 'enlaceEtiqueta'): boolean {
    return this.form.hasError(clave) && (this.form.controls.enlaceUrl.touched || this.form.controls.enlaceEtiqueta.touched);
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      tipo: v.tipo,
      titulo: v.titulo,
      resumen: v.resumen || null,
      fecha: v.fecha,
      imagen_id: this.foto?.id ?? null,
      enlace: v.enlaceUrl ? { etiqueta: v.enlaceEtiqueta, url: v.enlaceUrl, externo: !!v.enlaceExterno } : null,
      estado: v.estado,
    });
  }
}
