import { Routes } from '@angular/router';

/**
 * Las pantallas del panel «Portal Web», bajo /inicio/portal-web.
 *
 * El guard va en app.routes.ts, sobre todo el módulo (solo Administrador,
 * como la puerta 'editar-portal' del backend). Cada categoría se suma aquí a
 * medida que se habilita.
 */
export const PORTAL_WEB_ROUTES: Routes = [
  {
    path: '',
    loadComponent: () => import('./indice/portal-web-indice.component').then((m) => m.PortalWebIndiceComponent),
  },
  {
    path: 'inicio',
    loadComponent: () => import('./inicio/portal-web-inicio.component').then((m) => m.PortalWebInicioComponent),
  },
  {
    path: 'general',
    loadComponent: () => import('./general/portal-web-general.component').then((m) => m.PortalWebGeneralComponent),
  },
  {
    path: 'contacto',
    loadComponent: () => import('./contacto/portal-web-contacto.component').then((m) => m.PortalWebContactoComponent),
  },
  {
    path: 'matricula',
    loadComponent: () => import('./matricula/portal-web-matricula.component').then((m) => m.PortalWebMatriculaComponent),
  },
  {
    path: 'logros',
    loadComponent: () => import('./logros/portal-web-logros.component').then((m) => m.PortalWebLogrosComponent),
  },
];
