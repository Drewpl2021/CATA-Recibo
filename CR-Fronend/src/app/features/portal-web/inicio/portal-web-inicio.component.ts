import { Component, ElementRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { PendienteDeSeccion, ResumenDeSeccion } from '../compartido/resumen-de-seccion';
import { PanelBannersComponent } from './banners/panel-banners.component';
import { PanelCierreComponent } from './cierre/panel-cierre.component';
import { PanelNivelesComponent } from './niveles/panel-niveles.component';
import { PanelNoticiasComponent } from './noticias/panel-noticias.component';
import { PanelQuienesSomosComponent } from './quienes-somos/panel-quienes-somos.component';

type IdSeccion = 'carrusel' | 'cifras' | 'quienes-somos' | 'niveles' | 'noticias' | 'cierre';

interface SeccionDeInicio {
  id: IdSeccion;
  nombre: string;
  icono: string;
}

/** En el orden en que se ven en el portal. */
const SECCIONES: SeccionDeInicio[] = [
  { id: 'carrusel', nombre: 'Carrusel', icono: 'view_module' },
  { id: 'cifras', nombre: 'Cifras', icono: 'bar_chart' },
  { id: 'quienes-somos', nombre: 'Quiénes somos', icono: 'info' },
  { id: 'niveles', nombre: 'Niveles', icono: 'graduation_cap' },
  { id: 'noticias', nombre: 'Noticias', icono: 'description' },
  { id: 'cierre', nombre: 'Cierre', icono: 'check_circle' },
];

/**
 * Panel «Portal Web» › Inicio: la portada, con el molde de Ajustes del
 * sistema. A la izquierda el índice, que dice cómo está cada sección; a la
 * derecha la elegida. Arriba, «Pendientes», como en el dashboard: lo que le
 * falta a la portada, y cada uno lleva a su sección.
 *
 * Los seis paneles se crean a la vez (escondidos los que no se ven) para que
 * cada uno cargue y cuente su estado. La sección elegida va en la dirección
 * (?seccion=niveles): al recargar se vuelve a ella.
 */
@Component({
  selector: 'app-portal-web-inicio',
  standalone: true,
  imports: [
    CommonModule, PageHeaderComponent, IconComponent, EditorCifrasComponent,
    PanelBannersComponent, PanelQuienesSomosComponent, PanelNivelesComponent, PanelNoticiasComponent, PanelCierreComponent,
  ],
  styleUrls: ['../compartido/pw-estilos.scss'],
  template: `
    <app-page-header
      icono="dashboard"
      titulo="Inicio"
      subtitulo="La portada del portal, sección por sección, en el orden en que se ve."
    >
      <button headerActions type="button" class="btn-secondary" (click)="volver()">
        <app-icon icono="arrow_left" [tamano]="16"></app-icon>
        Volver a Portal Web
      </button>
    </app-page-header>

    <section class="pendientes" *ngIf="pendientes.length" aria-labelledby="pw-inicio-pendientes">
      <h2 class="pendientes__titulo" id="pw-inicio-pendientes">Pendientes de Inicio</h2>
      <div class="pendientes__lista pendientes__lista--fila">
        <button type="button" class="pendiente" *ngFor="let p of pendientes" (click)="irA(p.seccion)">
          <strong class="pendiente__cuantos">{{ p.cuantos }}</strong>
          <span class="pendiente__texto">{{ p.texto }}</span>
          <app-icon icono="arrow_right" [tamano]="16" class="pendiente__flecha"></app-icon>
        </button>
      </div>
    </section>

    <div class="ajustes-layout">
      <nav class="ajustes-indice" aria-label="Secciones de Inicio">
        <button type="button" *ngFor="let s of secciones" class="ajustes-indice__item"
          [class.ajustes-indice__item--activo]="actual === s.id" [attr.aria-current]="actual === s.id ? 'page' : null"
          [attr.aria-controls]="'pw-inicio-' + s.id" (click)="elegir(s.id)">
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
        <div id="pw-inicio-carrusel" [hidden]="actual !== 'carrusel'">
          <app-panel-banners (resumen)="alResumir('carrusel', $event)"></app-panel-banners>
        </div>
        <div id="pw-inicio-cifras" [hidden]="actual !== 'cifras'">
          <app-editor-cifras ambito="portada" vista="tarjetas" dondeSeVen="bajo el carrusel"
            (resumen)="alResumir('cifras', $event)"></app-editor-cifras>
        </div>
        <div id="pw-inicio-quienes-somos" [hidden]="actual !== 'quienes-somos'">
          <app-panel-quienes-somos (resumen)="alResumir('quienes-somos', $event)"></app-panel-quienes-somos>
        </div>
        <div id="pw-inicio-niveles" [hidden]="actual !== 'niveles'">
          <app-panel-niveles (resumen)="alResumir('niveles', $event)"></app-panel-niveles>
        </div>
        <div id="pw-inicio-noticias" [hidden]="actual !== 'noticias'">
          <app-panel-noticias (resumen)="alResumir('noticias', $event)"></app-panel-noticias>
        </div>
        <div id="pw-inicio-cierre" [hidden]="actual !== 'cierre'">
          <app-panel-cierre (resumen)="alResumir('cierre', $event)"></app-panel-cierre>
        </div>
      </div>
    </div>
  `,
})
export class PortalWebInicioComponent {
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private host = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly secciones = SECCIONES;
  actual: IdSeccion = this.seccionDeLaDireccion();
  resumenes: Partial<Record<IdSeccion, ResumenDeSeccion>> = {};
  pendientes: (PendienteDeSeccion & { seccion: IdSeccion })[] = [];

  private seccionDeLaDireccion(): IdSeccion {
    const pedida = this.route.snapshot.queryParamMap.get('seccion');
    return SECCIONES.find((s) => s.id === pedida)?.id ?? 'carrusel';
  }

  elegir(id: IdSeccion): void {
    this.actual = id;
    this.router.navigate([], { relativeTo: this.route, queryParams: { seccion: id }, replaceUrl: true });
  }

  /** Desde «Pendientes»: abre la sección y lleva el foco a su título, para que el lector de pantalla diga dónde está. */
  irA(id: IdSeccion): void {
    this.elegir(id);
    setTimeout(() => {
      const titulo = this.host.nativeElement.querySelector<HTMLElement>(`#pw-inicio-${id} h2`);
      if (!titulo) return;
      titulo.setAttribute('tabindex', '-1');
      titulo.focus({ preventScroll: true });
      const suave = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      titulo.scrollIntoView({ block: 'start', behavior: suave ? 'smooth' : 'auto' });
    });
  }

  alResumir(id: IdSeccion, resumen: ResumenDeSeccion): void {
    this.resumenes = { ...this.resumenes, [id]: resumen };
    this.pendientes = SECCIONES.flatMap((s) => (this.resumenes[s.id]?.pendientes ?? []).map((p) => ({ ...p, seccion: s.id })));
  }

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }
}
