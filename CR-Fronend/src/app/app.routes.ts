import { Routes } from '@angular/router';
import { LoginComponent } from './autenticacion/login/login.component';
import { RegistroComponent } from './autenticacion/registro/registro.component';
import { LayoutComponent } from './shared/layout/layout.component';
import { authGuard } from './core/guards/auth.guard';
import { roleGuard } from './core/guards/role.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: LoginComponent },
  { path: 'registro', component: RegistroComponent },
  {
    path: 'inicio',
    component: LayoutComponent,
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { 
        path: 'dashboard', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./inicio/dashboard/dashboard-view.component').then(m => m.DashboardViewComponent) 
      },
      { path: 'mis-boletas', loadComponent: () => import('./boletas/mis-boletas/mis-boletas.component').then(m => m.MisBoletasComponent) },
      { path: 'documentos', loadComponent: () => import('./documentos/documentos-list/documentos-list.component').then(m => m.DocumentosListComponent) },
      { path: 'mis-documentos', loadComponent: () => import('./documentos/documentos-list/documentos-list.component').then(m => m.DocumentosListComponent) },
      { path: 'vacaciones', loadComponent: () => import('./vacaciones/vacaciones-list/vacaciones-list.component').then(m => m.VacacionesListComponent) },
      { 
        path: 'historial-boletas', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./documentos/historial-boletas/historial-boletas.component').then(m => m.HistorialBoletasComponent) 
      },
      { 
        path: 'empleados', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./empleados/empleados-list/empleados-list.component').then(m => m.EmpleadosListComponent) 
      },
      { 
        path: 'empleados/nuevo', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) 
      },
      { 
        path: 'empleados/editar/:id', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) 
      },
      { 
        path: 'empleados/ver/:id', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) 
      },
      { 
        path: 'emision-boleta', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./emision-boleta/emision-boleta-list/emision-boleta-list.component').then(m => m.EmisionBoletaListComponent) 
      },
      { 
        path: 'emision-boleta/descuentos/:id', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./emision-boleta/emision-descuentos-form/emision-descuentos-form.component').then(m => m.EmisionDescuentosFormComponent) 
      },
      { 
        path: 'planillas', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./planillas/planillas-list/planillas-list.component').then(m => m.PlanillasListComponent) 
      },
      { 
        path: 'planillas/nuevo', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./planillas/planilla-form/planilla-form.component').then(m => m.PlanillaFormComponent) 
      },
      { 
        path: 'planillas/editar/:id', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./planillas/planilla-form/planilla-form.component').then(m => m.PlanillaFormComponent) 
      },
      { 
        path: 'catalogos', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'areas', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'cargos', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'sedes', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'periodos', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'conceptos', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { 
        path: 'descuentos', 
        canActivate: [roleGuard], 
        data: { roles: ['admin', 'rrhh'] },
        loadComponent: () => import('./configuracion/catalogos/catalogos.component').then(m => m.CatalogosComponent) 
      },
      { path: '**', redirectTo: 'mis-boletas' }
    ]
  },
  { path: '**', redirectTo: 'login' }
];
