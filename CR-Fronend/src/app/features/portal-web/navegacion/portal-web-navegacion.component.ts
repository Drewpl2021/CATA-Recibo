import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { PanelMenuComponent } from './panel-menu.component';
import { PanelTitulosComponent } from './panel-titulos.component';

/**
 * Panel «Portal Web» › Menú y títulos (contrato 2.13): lo que el portal tenía
 * escrito en su código hasta la 2.12. El menú con los grupos de Nosotros y el
 * pie, y los títulos, botones y rótulos de cada página.
 */
@Component({
  selector: 'app-portal-web-navegacion',
  standalone: true,
  imports: [PageHeaderComponent, IconComponent, PanelMenuComponent, PanelTitulosComponent],
  template: `
    <app-page-header
      icono="menu"
      titulo="Menú y títulos"
      subtitulo="El menú del portal, las columnas de Nosotros, el pie y los títulos y botones de cada página. Cada cambio queda en la Auditoría y se ve en el portal en hasta un minuto."
    >
      <button headerActions type="button" class="btn-secondary" (click)="volver()">
        <app-icon icono="arrow_left" [tamano]="16"></app-icon>
        Volver a Portal Web
      </button>
    </app-page-header>

    <app-panel-menu></app-panel-menu>
    <app-panel-titulos></app-panel-titulos>
  `,
})
export class PortalWebNavegacionComponent {
  private router = inject(Router);

  volver(): void {
    this.router.navigate(['/inicio/portal-web']);
  }
}
