import { Component, EventEmitter, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { accionesDeEstado, PanelDeLista } from '../../compartido/panel-de-lista';
import { cuantos, ResumenDeSeccion } from '../../compartido/resumen-de-seccion';
import { EstadoPortal, NivelDocentePortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';

/**
 * Personal Docente › Niveles: las secciones de la página y su filtro, hasta
 * 6 publicados. Se publican aunque no tengan docentes; los docentes de un
 * nivel en borrador no se ven. Con docentes, no se eliminan.
 */
@Component({
  selector: 'app-panel-niveles-docentes',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-niveles-docentes.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelNivelesDocentesComponent extends PanelDeLista<NivelDocentePortal> {
  private fb = inject(FormBuilder);

  /** Cada vez que se cargan: el panel de docentes los usa para elegir. */
  @Output() cambiaron = new EventEmitter<NivelDocentePortal[]>();
  /** Se pidió ver los docentes de uno. */
  @Output() verDocentes = new EventEmitter<NivelDocentePortal>();

  protected readonly recurso = RECURSOS.nivelesDocentes;
  protected readonly nombre = { singular: 'nivel', femenino: false };

  readonly accionesDeNivel: AccionPersonalizada<NivelDocentePortal>[] = [
    { id: 'docentes', titulo: 'Ver y editar sus docentes', icono: 'people' },
    ...accionesDeEstado<NivelDocentePortal>(),
  ];

  // La dirección y cuántos docentes tiene van bajo el nombre: así caben las cuatro acciones.
  columnas: ColumnaTabla<NivelDocentePortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Nivel' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(40)]],
    estado: ['borrador' as EstadoPortal],
  });

  protected override alCargar(): void {
    this.cambiaron.emit(this.filas);
  }

  protected describir(n: NivelDocentePortal): string {
    return `el nivel «${n.nombre}» de Personal Docente`;
  }

  docentesDe(n: NivelDocentePortal): string {
    return n.docentes ? cuantos(n.docentes, 'docente', 'docentes') : 'sin docentes';
  }

  /**
   * Para el índice cuentan los docentes, no los niveles. Un nivel publicado
   * sin docentes sale en el portal como una sección vacía.
   */
  protected override resumir(): ResumenDeSeccion {
    const docentes = this.filas.reduce((n, f) => n + f.docentes, 0);
    const vacios = this.filas.filter((f) => f.estado === 'publicado' && !f.docentes).length;
    return {
      estado: this.filas.length
        ? `${cuantos(docentes, 'docente', 'docentes')} en ${cuantos(this.filas.length, 'nivel', 'niveles')}` + (vacios ? ` · ${vacios} vacío${vacios === 1 ? '' : 's'}` : '')
        : 'Sin niveles ni docentes',
      aviso: vacios > 0,
      pendientes: vacios ? [{ cuantos: vacios, texto: vacios === 1 ? 'nivel de Personal Docente publicado sin docentes' : 'niveles de Personal Docente publicados sin docentes' }] : [],
    };
  }

  nuevo(): void {
    this.editando = null;
    this.form.reset({ nombre: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(n: NivelDocentePortal): void {
    this.editando = n;
    this.form.reset({ nombre: n.nombre, estado: n.estado });
    this.modalVisible = true;
  }

  override alAccionar(evento: { accion: string; fila: NivelDocentePortal }): void {
    if (evento.accion === 'docentes') {
      this.verDocentes.emit(evento.fila);
      return;
    }
    super.alAccionar(evento);
  }

  /** La tabla del sistema pide confirmar; aquí antes se avisa si no se puede. */
  pedirEliminar(n: NivelDocentePortal): void {
    if (n.docentes > 0) {
      this.toastService.warning(
        'No se puede eliminar',
        `«${n.nombre}» tiene ${n.docentes} ${n.docentes === 1 ? 'docente' : 'docentes'}. Pásalos a otro nivel antes.`,
      );
      return;
    }
    this.eliminar(n);
  }

  invalido(): boolean {
    const c = this.form.controls.nombre;
    return c.invalid && c.touched;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      setTimeout(() => document.querySelector<HTMLElement>('.modal-container .is-invalid')?.focus());
      return;
    }
    this.enviar(this.form.getRawValue());
  }
}
