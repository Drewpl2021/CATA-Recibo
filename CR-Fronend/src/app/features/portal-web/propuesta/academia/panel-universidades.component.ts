import { Component, EventEmitter, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { CeldaTablaDirective } from '../../../../shared/components/data-table/celda-tabla.directive';
import { DataTableComponent } from '../../../../shared/components/data-table/data-table.component';
import { AccionPersonalizada, ColumnaTabla } from '../../../../shared/components/data-table/data-table.models';
import { FormModalComponent } from '../../../../shared/components/form-modal/form-modal.component';
import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { accionesDeEstado, PanelDeLista } from '../../compartido/panel-de-lista';
import { EstadoPortal, UniversidadPortal } from '../../portal-web.models';
import { RECURSOS } from '../../portal-web.service';
import { enfocarPrimerError } from '../../compartido/panel';

/**
 * Academia › Universidades: hasta 8 publicadas, en el orden del filtro del
 * portal. Cada una con sus ciclos, que se editan debajo; sin ciclos no se
 * publica.
 */
@Component({
  selector: 'app-panel-universidades',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, IconComponent, DataTableComponent, CeldaTablaDirective, FormModalComponent],
  templateUrl: './panel-universidades.component.html',
  styleUrls: ['../../compartido/pw-estilos.scss'],
})
export class PanelUniversidadesComponent extends PanelDeLista<UniversidadPortal> {
  private fb = inject(FormBuilder);

  /** Cada vez que se cargan: el panel de ciclos las usa para su selector. */
  @Output() cambiaron = new EventEmitter<UniversidadPortal[]>();
  /** Se pidió ver los ciclos de una. */
  @Output() verCiclos = new EventEmitter<UniversidadPortal>();

  protected readonly recurso = RECURSOS.universidades;
  protected readonly nombre = { singular: 'universidad', femenino: true };

  readonly accionesDeUniversidad: AccionPersonalizada<UniversidadPortal>[] = [
    { id: 'ciclos', titulo: 'Ver y editar sus ciclos', icono: 'layers' },
    ...accionesDeEstado<UniversidadPortal>(),
  ];

  // La dirección y los ciclos van bajo el nombre: así caben las acciones.
  columnas: ColumnaTabla<UniversidadPortal>[] = [
    this.columnaDeOrden(),
    { campo: 'nombre', header: 'Universidad' },
    this.columnaDeEstado(),
  ];

  form = this.fb.group({
    nombre: ['', [Validators.required, Validators.maxLength(80)]],
    estado: ['borrador' as EstadoPortal],
  });

  protected override alCargar(): void {
    this.cambiaron.emit(this.filas);
  }

  protected describir(u: UniversidadPortal): string {
    const ciclos = u.ciclos === 0 ? '' : u.ciclos === 1 ? ' con su ciclo' : ` con sus ${u.ciclos} ciclos`;
    return `la universidad «${u.nombre}»${ciclos}`;
  }

  ciclosDe(u: UniversidadPortal): string {
    return u.ciclos ? `${u.ciclos} ${u.ciclos === 1 ? 'ciclo' : 'ciclos'}` : 'sin ciclos';
  }

  nueva(): void {
    this.editando = null;
    this.form.reset({ nombre: '', estado: 'borrador' });
    this.modalVisible = true;
  }

  editar(u: UniversidadPortal): void {
    this.editando = u;
    this.form.reset({ nombre: u.nombre, estado: u.estado });
    this.modalVisible = true;
  }

  /** Ver sus ciclos, o publicar y ocultar; sin ciclos, se avisa antes de publicar. */
  override alAccionar(evento: { accion: string; fila: UniversidadPortal }): void {
    if (evento.accion === 'ciclos') {
      this.verCiclos.emit(evento.fila);
      return;
    }
    if (evento.accion === 'publicar' && evento.fila.ciclos === 0) {
      this.toastService.warning('Todavía no se puede publicar', `Agrega al menos un ciclo a «${evento.fila.nombre}» antes de publicarla.`);
      this.verCiclos.emit(evento.fila);
      return;
    }
    super.alAccionar(evento);
  }

  invalido(): boolean {
    const c = this.form.controls.nombre;
    return c.invalid && c.touched;
  }

  /** Sin ciclos no se puede publicar: al crearla, siempre en borrador. */
  get puedePublicar(): boolean {
    return !!this.editando && this.editando.ciclos > 0;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      enfocarPrimerError();
      return;
    }
    const v = this.form.getRawValue();
    this.enviar(this.puedePublicar ? v : { nombre: v.nombre });
  }
}
