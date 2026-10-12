import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { PanelBannersComponent } from './banners/panel-banners.component';
import { PanelCierreComponent } from './cierre/panel-cierre.component';
import { PanelNivelesComponent } from './niveles/panel-niveles.component';
import { PanelNoticiasComponent } from './noticias/panel-noticias.component';
import { PanelQuienesSomosComponent } from './quienes-somos/panel-quienes-somos.component';

/**
 * Panel «Portal Web» › Inicio: la portada, en el orden en que se ve en el
 * portal: el carrusel, las cifras, Quiénes somos, los niveles, las
 * noticias y el cierre. Cada parte es su propio panel. Las sedes, que Inicio también
 * muestra, se editan en Contacto.
 */
@Component({
  selector: 'app-portal-web-inicio',
  standalone: true,
  imports: [
    PageHeaderComponent, IconComponent, EditorCifrasComponent,
    PanelBannersComponent, PanelQuienesSomosComponent, PanelNivelesComponent, PanelNoticiasComponent, PanelCierreComponent,
  ],
  template: `
    <app-page-header
      icono="dashboard"
      titulo="Inicio"
      subtitulo="La portada del portal, en el orden en que se ve. Las sedes se editan en Contacto. Cada cambio queda en la Auditoría y se ve en el portal en hasta un minuto."
    >
      <button headerActions type="button" class="btn-secondary" (click)="volver()">
        <app-icon icono="arrow_left" [tamano]="16"></app-icon>
        Volver a Portal Web
      </button>
    </app-page-header>

    <app-panel-banners></app-panel-banners>
    <app-editor-cifras ambito="portada" dondeSeVen="de Inicio, bajo el carrusel"></app-editor-cifras>
    <app-panel-quienes-somos></app-panel-quienes-somos>
    <app-panel-niveles></app-panel-niveles>
    <app-panel-noticias></app-panel-noticias>
    <app-panel-cierre></app-panel-cierre>
  `,
})
export class PortalWebInicioComponent {
  private router = inject(Router);

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }
}
