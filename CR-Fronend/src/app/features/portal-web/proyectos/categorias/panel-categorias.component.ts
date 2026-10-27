import { Component, EventEmitter, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { FilaDelPortal, PanelDeLista } from '../../compartido/panel-de-lista';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { CategoriaProyectoPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';
import { enfocarPrimerError } from '../../compartido/panel';

const TOPE = 8;

/**
 * Proyectos › Categorías: las del filtro de la página, hasta 8, en su orden.
 *
 * No tienen borrador: el portal manda todas y oculta las que no tienen
 * proyectos (y el filtro entero si queda una sola). Una categoría con
 * proyectos no se elimina; primero se pasan a otra.
 */
@Component({
  selector: 'app-panel-categorias',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-categorias.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelCategoriasComponent extends PanelDeLista<CategoriaProyectoPortal & FilaDelPortal> {
  private fb = inject(FormBuilder);

  /** Cada vez que se cargan: el panel de proyectos las usa para su selector. */
  @Output() cambiaron = new EventEmitter<CategoriaProyectoPortal[]>();

  protected readonly recurso = RECURSOS.categoriasProyecto;
  protected readonly nombre = { singular: 'categoría', femenino: true };

  readonly tope = TOPE;

  // La dirección y cuántos proyectos tiene van bajo el nombre.
  columnas: ColumnaTabla<CategoriaProyectoPortal>[] = [
    { campo: 'orden', header: 'Orden', ancho: '120px' },
    { campo: 'nombre', header: 'Categoría' },
  ];

  form = this.fb.group({ nombre: ['', [Validators.required, Validators.maxLength(40)]] });

  protected override alCargar(): void {
    this.cambiaron.emit(this.filas);
  }

  protected describir(c: CategoriaProyectoPortal): string {
    return `la categoría «${c.nombre}»`;
  }

  proyectosDe(c: CategoriaProyectoPortal): string {
    return c.proyectos ? cuantos(c.proyectos, 'proyecto', 'proyectos') : 'sin proyectos: no se ve';
  }

  /** No tienen borrador: se cuentan todas. Sin ninguna, no se puede agregar un proyecto. */
  protected override resumir(): ResumenDeSeccion {
    if (!this.filas.length) {
      return { estado: 'Sin categorías', aviso: true, pendientes: [{ cuantos: 1, texto: 'categoría por crear antes de agregar proyectos' }] };
    }
    const vacias = this.filas.filter((c) => !c.proyectos).length;
    return {
      estado: cuantos(this.filas.length, 'categoría', 'categorías') + (vacias ? ` · ${vacias} sin proyectos` : ''),
      aviso: false,
      pendientes: [],
    };
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ nombre: '' });
    this.modalVisible = true;
  }

  editar(c: CategoriaProyectoPortal & FilaDelPortal): void {
    this.editando = c;
    this.form.reset({ nombre: c.nombre });
    this.modalVisible = true;
  }

  /** La tabla del sistema pide confirmar; aquí antes se avisa si no se puede. */
  pedirEliminar(c: CategoriaProyectoPortal & FilaDelPortal): void {
    if (c.proyectos > 0) {
      this.toastService.warning(
        'No se puede eliminar',
        `«${c.nombre}» tiene ${c.proyectos} ${c.proyectos === 1 ? 'proyecto' : 'proyectos'}. Pásalos a otra categoría antes.`,
      );
      return;
    }
    this.eliminar(c);
  }

  invalido(): boolean {
    const c = this.form.controls.nombre;
    return c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }
    this.enviar(this.form.getRawValue());
  }
}
