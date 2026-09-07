import { Routes } from '@angular/router';
import { LoginComponent } from './features/autenticacion/login/login.component';
import { RegistroComponent } from './features/autenticacion/registro/registro.component';
import { LayoutComponent } from './layout/layout.component';
import { authGuard, guestGuard, roleGuard } from './core/guards/auth.guard';

const soloRrhhOAdmin = roleGuard(['admin', 'rrhh']);
const soloAdmin = roleGuard(['admin']);

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: LoginComponent, canActivate: [guestGuard] },
  { path: 'registro', component: RegistroComponent, canActivate: [guestGuard] },
  {
    path: 'inicio',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', loadComponent: () => import('./features/inicio/dashboard/dashboard-view.component').then(m => m.DashboardViewComponent) },
      { path: 'mis-boletas', loadComponent: () => import('./features/boletas/mis-boletas/mis-boletas.component').then(m => m.MisBoletasComponent) },
      // "documentos" es en realidad el autoservicio de cada quien (mis-documentos por dentro) — accesible a todos.
      { path: 'documentos', loadComponent: () => import('./features/documentos/documentos-list/documentos-list.component').then(m => m.DocumentosListComponent) },
      // El seeder crea dos ítems de menú que llevan a la misma pantalla:
      // "Documentos" (/documentos) y "Mis Documentos" (/mis-documentos).
      { path: 'mis-documentos', loadComponent: () => import('./features/documentos/documentos-list/documentos-list.component').then(m => m.DocumentosListComponent) },
      {
        path: 'historial-boletas',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/documentos/historial-boletas/historial-boletas.component').then(m => m.HistorialBoletasComponent),
      },
      // ── Configuración base (catálogos) ──
      {
        path: 'areas',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/areas/areas-list/areas-list.component').then(m => m.AreasListComponent),
      },
      {
        path: 'cargos',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/cargos/cargos-list/cargos-list.component').then(m => m.CargosListComponent),
      },
      {
        path: 'sedes',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/sedes/sedes-list/sedes-list.component').then(m => m.SedesListComponent),
      },
      {
        path: 'periodos',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/periodos/periodos-list/periodos-list.component').then(m => m.PeriodosListComponent),
      },
      {
        path: 'conceptos-pago',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/conceptos-pago/conceptos-pago-list/conceptos-pago-list.component').then(m => m.ConceptosPagoListComponent),
      },
      {
        path: 'empleados',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/empleados/empleados-list/empleados-list.component').then(m => m.EmpleadosListComponent),
      },
      {
        path: 'empleados/nuevo',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent),
      },
      {
        path: 'empleados/editar/:id',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent),
      },
      {
        path: 'empleados/ver/:id',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent),
      },
      {
        path: 'emision-boleta',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/emision-boleta/emision-boleta-list/emision-boleta-list.component').then(m => m.EmisionBoletaListComponent),
      },
      {
        path: 'emision-boleta/descuentos/:id',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/emision-boleta/emision-descuentos-form/emision-descuentos-form.component').then(m => m.EmisionDescuentosFormComponent),
      },
      {
        path: 'planillas',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/planillas/planillas-list/planillas-list.component').then(m => m.PlanillasListComponent),
      },
      {
        path: 'planillas/nuevo',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/planillas/planilla-form/planilla-form.component').then(m => m.PlanillaFormComponent),
      },
      {
        path: 'planillas/editar/:id',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/planillas/planilla-form/planilla-form.component').then(m => m.PlanillaFormComponent),
      },
      {
        path: 'planillas/detalle/:id',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/planillas/planilla-detalle/planilla-detalle.component').then(m => m.PlanillaDetalleComponent),
      },
      {
        path: 'contratos',
        canActivate: [soloRrhhOAdmin],
        loadComponent: () => import('./features/contratos/contratos-list/contratos-list.component').then(m => m.ContratosListComponent),
      },
      // ── Sistema de permisos y menús (solo Admin) ──
      {
        path: 'usuarios',
        canActivate: [soloAdmin],
        loadComponent: () => import('./features/usuarios/usuarios-list/usuarios-list.component').then(m => m.UsuariosListComponent),
      },
      {
        path: 'roles',
        canActivate: [soloAdmin],
        loadComponent: () => import('./features/roles/roles-list/roles-list.component').then(m => m.RolesListComponent),
      },
      {
        path: 'modulos-padre',
        canActivate: [soloAdmin],
        loadComponent: () => import('./features/modulos-padre/modulos-padre-list/modulos-padre-list.component').then(m => m.ModulosPadreListComponent),
      },
      {
        path: 'modulos',
        canActivate: [soloAdmin],
        loadComponent: () => import('./features/modulos/modulos-list/modulos-list.component').then(m => m.ModulosListComponent),
      },
      { path: '**', redirectTo: 'dashboard' }
    ]
  },
  { path: '**', redirectTo: 'login' }
];
