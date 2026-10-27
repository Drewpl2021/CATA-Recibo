import { Component, ElementRef, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { IconComponent } from '../../../../shared/components/icon/icon.component';
import { PendienteConSeccion, SeccionDelIndice } from '../pantalla-con-indice';
import { ResumenDeSeccion } from '../resumen-de-seccion';

/**
 * «Pendientes» (el patrón del dashboard) y el índice de Ajustes, con la
 * sección elegida a la derecha. Cada sección la pone la pantalla, dentro, en
 * un contenedor con id `pw-seccion-<id>` y [hidden] si no es la elegida:
 *
 *   <pw-indice-de-secciones nombre="General" [secciones]="secciones" [resumenes]="resumenes"
 *     [pendientes]="pendientes" [actual]="actual" (actualChange)="elegir($event)">
 *     <div id="pw-seccion-redes" [hidden]="actual !== 'redes'"> … </div>
 *   </pw-indice-de-secciones>
 *
 * Un pendiente abre su sección y lleva el foco a su título (el primer h2),
 * para que el lector de pantalla diga dónde quedó.
 */
@Component({
  selector: 'pw-indice-de-secciones',
  standalone: true,
  imports: [CommonModule, IconComponent],
  styleUrls: ['../pw-estilos.scss'],
  template: `
    <section class="pendientes" *ngIf="pendientes.length" [attr.aria-labelledby]="id + '-pendientes'">
      <h2 class="pendientes__titulo" [id]="id + '-pendientes'">Pendientes de {{ nombre }}</h2>
      <div class="pendientes__lista pendientes__lista--fila">
        <button type="button" class="pendiente" *ngFor="let p of pendientes; trackBy: porPendiente" (click)="irA(p.seccion)">
          <strong class="pendiente__cuantos">{{ p.cuantos }}</strong>
          <span class="pendiente__texto">{{ p.texto }}</span>
          <app-icon icono="arrow_right" [tamano]="16" class="pendiente__flecha"></app-icon>
        </button>
      </div>
    </section>

    <div class="ajustes-layout">
      <nav class="ajustes-indice" [attr.aria-label]="'Secciones de ' + nombre">
        <button type="button" *ngFor="let s of secciones; trackBy: porSeccion" class="ajustes-indice__item"
          [class.ajustes-indice__item--activo]="actual === s.id" [attr.aria-current]="actual === s.id ? 'page' : null"
          [attr.aria-controls]="'pw-seccion-' + s.id" (click)="actualChange.emit(s.id)">
          <span class="ajustes-indice__icono"><app-icon [icono]="s.icono" [tamano]="18"></app-icon></span>
          <span class="ajustes-indice__textos">
            <span class="ajustes-indice__nombre">{{ s.nombre }}</span>
            <span class="ajustes-indice__estado" [class.ajustes-indice__estado--aviso]="resumenes[s.id]?.aviso">
              <app-icon *ngIf="resumenes[s.id]?.aviso" icono="warning" [tamano]="13"></app-icon>
              {{ resumenes[s.id]?.estado ?? 'Cargando…' }}
            </span>
          </span>
        </button>
      </nav>

      <div class="ajustes-contenido pw-detalle">
        <ng-content></ng-content>
      </div>
    </div>
  `,
})
export class IndiceDeSeccionesComponent {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);

  /** El nombre de la pantalla: «Pendientes de Inicio», «Secciones de Inicio». */
  @Input({ required: true }) nombre = '';
  @Input({ required: true }) secciones: SeccionDelIndice[] = [];
  @Input() resumenes: Record<string, ResumenDeSeccion | undefined> = {};
  @Input() pendientes: PendienteConSeccion[] = [];
  @Input() actual = '';
  @Output() actualChange = new EventEmitter<string>();

  readonly id = 'pw-indice-' + Math.random().toString(36).slice(2, 8);

  /** Los pendientes se vuelven a armar cada vez que una sección se resume: el mismo es el de la misma sección y texto. */
  readonly porPendiente = (_: number, p: PendienteConSeccion): string => `${p.seccion}|${p.texto}`;
  readonly porSeccion = (_: number, s: SeccionDelIndice): string => s.id;

  irA(id: string): void {
    this.actualChange.emit(id);
    setTimeout(() => {
      const titulo = this.host.nativeElement.querySelector<HTMLElement>(`#pw-seccion-${id} h2`);
      if (!titulo) return;
      titulo.setAttribute('tabindex', '-1');
      titulo.focus({ preventScroll: true });
      const suave = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      titulo.scrollIntoView({ block: 'start', behavior: suave ? 'smooth' : 'auto' });
    });
  }
}
