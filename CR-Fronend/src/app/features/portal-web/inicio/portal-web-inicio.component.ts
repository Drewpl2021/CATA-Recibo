import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { PanelBannersComponent } from './banners/panel-banners.component';
import { PanelCierreComponent } from './cierre/panel-cierre.component';
import { PanelNivelesComponent } from './niveles/panel-niveles.component';
import { PanelNoticiasComponent } from './noticias/panel-noticias.component';
import { PanelQuienesSomosComponent } from './quienes-somos/panel-quienes-somos.component';

/** En el orden en que se ven en el portal. */
const SECCIONES: SeccionDelIndice[] = [
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
 * cada uno cargue y cuente su estado. El índice y la sección elegida, en
 * PantallaConIndice y pw-indice-de-secciones.
 */
@Component({
  selector: 'app-portal-web-inicio',
  standalone: true,
  imports: [
    CommonModule, PageHeaderComponent, IconComponent, EditorCifrasComponent, IndiceDeSeccionesComponent,
    PanelBannersComponent, PanelQuienesSomosComponent, PanelNivelesComponent, PanelNoticiasComponent, PanelCierreComponent,
  ],
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

    <pw-indice-de-secciones nombre="Inicio" [secciones]="secciones" [resumenes]="resumenes" [pendientes]="pendientes"
      [actual]="actual" (actualChange)="elegir($event)">
      <div id="pw-seccion-carrusel" [hidden]="actual !== 'carrusel'">
        <app-panel-banners (resumen)="alResumir('carrusel', $event)"></app-panel-banners>
      </div>
      <div id="pw-seccion-cifras" [hidden]="actual !== 'cifras'">
        <app-editor-cifras ambito="portada" vista="tarjetas" dondeSeVen="bajo el carrusel"
          (resumen)="alResumir('cifras', $event)"></app-editor-cifras>
      </div>
      <div id="pw-seccion-quienes-somos" [hidden]="actual !== 'quienes-somos'">
        <app-panel-quienes-somos (resumen)="alResumir('quienes-somos', $event)"></app-panel-quienes-somos>
      </div>
      <div id="pw-seccion-niveles" [hidden]="actual !== 'niveles'">
        <app-panel-niveles (resumen)="alResumir('niveles', $event)"></app-panel-niveles>
      </div>
      <div id="pw-seccion-noticias" [hidden]="actual !== 'noticias'">
        <app-panel-noticias (resumen)="alResumir('noticias', $event)"></app-panel-noticias>
      </div>
      <div id="pw-seccion-cierre" [hidden]="actual !== 'cierre'">
        <app-panel-cierre (resumen)="alResumir('cierre', $event)"></app-panel-cierre>
      </div>
    </pw-indice-de-secciones>
  `,
})
export class PortalWebInicioComponent extends PantallaConIndice {
  readonly secciones = SECCIONES;
}
