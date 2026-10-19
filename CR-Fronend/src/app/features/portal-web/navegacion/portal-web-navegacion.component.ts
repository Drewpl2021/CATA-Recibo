import { Component } from '@angular/core';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { PanelMenuComponent } from './panel-menu.component';
import { PanelTitulosComponent } from './panel-titulos.component';

/**
 * Panel «Portal Web» › Menú y títulos (contrato 2.13): lo que el portal tenía
 * escrito en su código hasta la 2.12. El menú con los grupos de Nosotros y el
 * pie, y los títulos, botones y rótulos de cada página.
 *
 * Con el molde de Ajustes: el menú, los grupos y el pie son una sola ficha,
 * pero cada uno tiene su sección (el panel del menú dibuja las tres); los
 * títulos, la suya.
 */
@Component({
  selector: 'app-portal-web-navegacion',
  standalone: true,
  imports: [PageHeaderComponent, IconComponent, IndiceDeSeccionesComponent, PanelMenuComponent, PanelTitulosComponent],
  styleUrls: ['../compartido/pw-estilos.scss'],
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

    <pw-indice-de-secciones nombre="Menú y títulos" [secciones]="secciones" [resumenes]="resumenes" [pendientes]="pendientes"
      [actual]="actual" (actualChange)="elegir($event)">
      <app-panel-menu [actual]="actual" (resumen)="alResumir($event.parte, $event.resumen)"></app-panel-menu>
      <div id="pw-seccion-titulos" [hidden]="actual !== 'titulos'">
        <app-panel-titulos (resumen)="alResumir('titulos', $event)"></app-panel-titulos>
      </div>
    </pw-indice-de-secciones>
  `,
})
export class PortalWebNavegacionComponent extends PantallaConIndice {
  readonly secciones: SeccionDelIndice[] = [
    { id: 'menu', nombre: 'Menú', icono: 'menu' },
    { id: 'grupos', nombre: 'Grupos de Nosotros', icono: 'people' },
    { id: 'pie', nombre: 'Pie', icono: 'layers' },
    { id: 'titulos', nombre: 'Títulos de las páginas', icono: 'description' },
  ];
}
