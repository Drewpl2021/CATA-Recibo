import { Component } from '@angular/core';

import { IconComponent } from '../../../shared/components/icon/icon.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { EditorCifrasComponent } from '../compartido/editor-cifras/editor-cifras.component';
import { IndiceDeSeccionesComponent } from '../compartido/indice-de-secciones/indice-de-secciones.component';
import { PantallaConIndice, SeccionDelIndice } from '../compartido/pantalla-con-indice';
import { PanelAcademiaComponent } from './academia/panel-academia.component';
import { PanelCabeceraPropuestaComponent } from './cabecera/panel-cabecera-propuesta.component';
import { PanelNivelesPropuestaComponent } from './niveles/panel-niveles-propuesta.component';
import { PanelPilaresComponent } from './pilares/panel-pilares.component';
import { PanelPlataformasComponent } from './plataformas/panel-plataformas.component';

/**
 * Panel «Portal Web» › Propuesta Educativa, en el orden en que se ve en el
 * portal: la cabecera, las cifras, los pilares, los niveles, la academia
 * preuniversitaria y las plataformas digitales. Cada parte es su propio panel.
 *
 * Con el molde de Ajustes: un índice con una sección por panel.
 */
@Component({
  selector: 'app-portal-web-propuesta',
  standalone: true,
  imports: [
    PageHeaderComponent, IconComponent, EditorCifrasComponent, IndiceDeSeccionesComponent,
    PanelCabeceraPropuestaComponent, PanelPilaresComponent, PanelNivelesPropuestaComponent, PanelAcademiaComponent, PanelPlataformasComponent,
  ],
  styleUrls: ['../compartido/pw-estilos.scss'],
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

    <pw-indice-de-secciones nombre="Propuesta Educativa" [secciones]="secciones" [resumenes]="resumenes" [pendientes]="pendientes"
      [actual]="actual" (actualChange)="elegir($event)">
      <div id="pw-seccion-cabecera" [hidden]="actual !== 'cabecera'">
        <app-panel-cabecera-propuesta (resumen)="alResumir('cabecera', $event)"></app-panel-cabecera-propuesta>
      </div>
      <div id="pw-seccion-cifras" [hidden]="actual !== 'cifras'">
        <app-editor-cifras ambito="propuesta" vista="tarjetas" dondeSeVen="de Propuesta Educativa, en la franja bajo la cabecera"
          (resumen)="alResumir('cifras', $event)"></app-editor-cifras>
      </div>
      <div id="pw-seccion-pilares" [hidden]="actual !== 'pilares'">
        <app-panel-pilares (resumen)="alResumir('pilares', $event)"></app-panel-pilares>
      </div>
      <div id="pw-seccion-niveles" [hidden]="actual !== 'niveles'">
        <app-panel-niveles-propuesta (resumen)="alResumir('niveles', $event)"></app-panel-niveles-propuesta>
      </div>
      <div id="pw-seccion-academia" [hidden]="actual !== 'academia'">
        <app-panel-academia (resumen)="alResumir('academia', $event)"></app-panel-academia>
      </div>
      <div id="pw-seccion-plataformas" [hidden]="actual !== 'plataformas'">
        <app-panel-plataformas (resumen)="alResumir('plataformas', $event)"></app-panel-plataformas>
      </div>
    </pw-indice-de-secciones>
  `,
})
export class PortalWebPropuestaComponent extends PantallaConIndice {
  readonly secciones: SeccionDelIndice[] = [
    { id: 'cabecera', nombre: 'Cabecera', icono: 'graduation_cap' },
    { id: 'cifras', nombre: 'Cifras', icono: 'bar_chart' },
    { id: 'pilares', nombre: 'Pilares', icono: 'layers' },
    { id: 'niveles', nombre: 'Niveles', icono: 'view_module' },
    { id: 'academia', nombre: 'Academia preuniversitaria', icono: 'domain' },
    { id: 'plataformas', nombre: 'Plataformas digitales', icono: 'table_chart' },
  ];
}
