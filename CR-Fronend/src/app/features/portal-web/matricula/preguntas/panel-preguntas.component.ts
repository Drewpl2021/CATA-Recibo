import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, PreguntaPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';
import { enfocarPrimerError } from '../../compartido/panel';

/**
 * Matrícula › Preguntas frecuentes: las dudas de las familias, con su
 * respuesta en texto plano. Hasta 15 publicadas, en este orden.
 */
@Component({
  selector: 'app-panel-preguntas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-preguntas.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelPreguntasComponent extends PanelDeLista<PreguntaPortal> {
  private fb = inject(FormBuilder);

  protected readonly recurso = RECURSOS.preguntas;
  protected readonly nombre = { singular: 'pregunta', femenino: true };

  // El comienzo de la respuesta va bajo la pregunta: así caben las acciones.
  columnas: ColumnaTabla<PreguntaPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'pregunta', header: 'Pregunta' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    pregunta: ['', [Validators.required, Validators.maxLength(140)]],
    respuesta: ['', [Validators.required, Validators.maxLength(600)]],
    estado: ['borrador' as EstadoPortal],
  });

  protected describir(p: PreguntaPortal): string {
    return `la pregunta «${p.pregunta}»`;
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ pregunta: '', respuesta: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(p: PreguntaPortal): void {
    this.editando = p;
    this.form.reset({ pregunta: p.pregunta, respuesta: p.respuesta, estado: p.estado });
    this.modalVisible = true;
  }

  invalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && c.touched;
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
