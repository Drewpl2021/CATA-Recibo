import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { lineas, validarLineas } from '../../compartido/lineas';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, GrupoGradosPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Matrícula › Grados: las opciones del selector de grado del formulario
 * «Solicitar información» (en Matrícula y en Contacto), agrupadas por nivel.
 */
@Component({
  selector: 'app-panel-grados',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-grados.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelGradosComponent extends PanelDeLista<GrupoGradosPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.grados;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  // Las opciones van bajo el nivel: así caben las acciones.
  columnas: ColumnaTabla<GrupoGradosPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nivel', header: 'Nivel y sus opciones' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nivel: ['', [Validators.required, Validators.maxLength(24)]],
    opciones: ['', [validarLineas(1, 8, 24)]],
    estado: ['borrador' as EstadoPortal],
  });

  protected describir(g: GrupoGradosPortal): string {
    return `el nivel «${g.nivel}» del selector de grado`;
  }

  get cuantasOpciones(): number {
    return lineas(this.form.value.opciones).length;
  }

  nuevo(): void {
    this.editando = null;
    this.form.reset({ nivel: '', opciones: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(g: GrupoGradosPortal): void {
    this.editando = g;
    this.form.reset({ nivel: g.nivel, opciones: g.opciones.join('\n'), estado: g.estado });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
      return;
    }

    const v = this.form.getRawValue();
    this.enviar({ nivel: v.nivel, opciones: lineas(v.opciones), estado: v.estado });
  }
}
