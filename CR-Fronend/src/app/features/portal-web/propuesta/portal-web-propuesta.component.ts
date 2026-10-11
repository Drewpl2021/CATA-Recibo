import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { PanelAcademiaComponent } from './academia/panel-academia.component';
import { PanelCabeceraPropuestaComponent } from './cabecera/panel-cabecera-propuesta.component';
import { PanelNivelesPropuestaComponent } from './niveles/panel-niveles-propuesta.component';
import { PanelPilaresComponent } from './pilares/panel-pilares.component';
import { PanelPlataformasComponent } from './plataformas/panel-plataformas.component';

/**
 * Panel «Portal Web» › Propuesta Educativa, en el orden en que se ve en el
 * portal: la cabecera, las cifras, los pilares, los niveles, la academia
 * preuniversitaria y las plataformas digitales. Cada parte es su propio panel.
 */
@Component({
  selector: 'app-portal-web-propuesta',
  standalone: true,
  imports: [
    PageHeaderComponent, IconComponent, EditorCifrasComponent,
    PanelCabeceraPropuestaComponent, PanelPilaresComponent, PanelNivelesPropuestaComponent, PanelAcademiaComponent, PanelPlataformasComponent,
  ],
  template: `
    <app-page-header
      icono="graduation_cap"
      titulo="Propuesta Educativa"
      subtitulo="Cómo enseña el colegio, en el orden en que se ve en el portal. Cada cambio queda en la Auditoría y se ve en el portal en hasta un minuto."
    >
      <button headerActions type="button" class="btn-secondary" (click)="volver()">
        <app-icon icono="arrow_left" [tamano]="16"></app-icon>
        Volver a Portal Web
      </button>
    </app-page-header>

    <app-panel-cabecera-propuesta></app-panel-cabecera-propuesta>
    <app-editor-cifras ambito="propuesta" dondeSeVen="de Propuesta Educativa, en la franja bajo la cabecera"></app-editor-cifras>
    <app-panel-pilares></app-panel-pilares>
    <app-panel-niveles-propuesta></app-panel-niveles-propuesta>
    <app-panel-academia></app-panel-academia>
    <app-panel-plataformas></app-panel-plataformas>
  `,
})
export class PortalWebPropuestaComponent {
  private router = inject(Router);

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }
}
