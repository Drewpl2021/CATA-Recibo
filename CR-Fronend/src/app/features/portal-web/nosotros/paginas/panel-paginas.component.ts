import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, PAGINAS_DE_NOSOTROS, PaginaNosotros } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Nosotros › Subpáginas: el título y la frase de cada una, y la frase de su
 * tarjeta en el índice. Sus bloques (el contenido) se editan aparte.
 *
 * Solo las 16 direcciones que el portal sabe abrir; el lugar de cada una en
 * el menú lo pone el portal, por eso aquí no se ordenan.
 */
@Component({
  selector: 'app-panel-paginas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, FormModalComponent],
  templateUrl: './panel-paginas.component.html',
})
export class PanelPaginasComponent extends PanelDeLista<PaginaNosotros> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.paginas;
  protected readonly nombre = { singular: 'página', femenino: true };

  columnas: ColumnaTabla<PaginaNosotros>[] = [
    { campo: 'grupo', header: 'Grupo', ancho: '12%', formatear: (g) => g ?? '—' },
    { campo: 'nombre', header: 'En el menú', ancho: '18%', formatear: (n, p: PaginaNosotros) => n ?? p.slug },
    { campo: 'titulo', header: 'Título' },
    { campo: 'resumen', header: 'Tarjeta del índice', ancho: '14%', formatear: (r) => (r ? 'Con frase' : 'Solo el nombre') },
    { campo: 'bloques', header: 'Bloques', ancho: '9%', formatear: (n: number) => (n ? String(n) : 'Ninguno') },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    slug: ['', [Validators.required]],
    titulo: ['', [Validators.required, Validators.maxLength(60)]],
    bajada: ['', [Validators.maxLength(160)]],
    resumen: ['', [Validators.maxLength(140)]],
    estado: ['borrador' as EstadoPortal],
  });

  /** Las direcciones que todavía no tienen página. */
  get libres(): typeof PAGINAS_DE_NOSOTROS {
    const usadas = new Set(this.filas.map((p) => p.slug));
    return PAGINAS_DE_NOSOTROS.filter((p) => !usadas.has(p.slug));
  }

  protected describir(p: PaginaNosotros): string {
    return `la página «${p.nombre ?? p.titulo}» (su dirección dejará de abrir)`;
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ slug: '', titulo: '', bajada: '', resumen: '', estado: 'borrador' });
    this.form.controls.slug.enable();
    this.modalVisible = true;
  }

  editar(p: PaginaNosotros): void {
    this.editando = p;
    this.form.reset({ slug: p.slug, titulo: p.titulo, bajada: p.bajada ?? '', resumen: p.resumen ?? '', estado: p.estado });
    this.form.controls.slug.disable();
    this.modalVisible = true;
  }

  /** Al elegir la dirección de una nueva, su nombre sirve de título. */
  alElegirSlug(): void {
    const elegida = PAGINAS_DE_NOSOTROS.find((p) => p.slug === this.form.controls.slug.value);
    if (elegida && !this.form.controls.titulo.value) this.form.controls.titulo.setValue(elegida.nombre);
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({
      ...(this.editando ? {} : { slug: v.slug }),
      titulo: v.titulo,
      bajada: (v.bajada ?? '').trim() || null,
      resumen: (v.resumen ?? '').trim() || null,
      estado: v.estado,
    });
  }
}
