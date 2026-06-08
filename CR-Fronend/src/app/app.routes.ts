import { Routes } from '@angular/router';
import { LoginComponent } from './autenticacion/login/login.component';
import { RegistroComponent } from './autenticacion/registro/registro.component';
import { LayoutComponent } from './shared/layout/layout.component';
import { DashboardViewComponent } from './inicio/dashboard/dashboard-view.component';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: LoginComponent },
  { path: 'registro', component: RegistroComponent },
  {
    path: 'inicio',
    component: LayoutComponent,
    children: [
      { path: '', component: DashboardViewComponent },
      { path: 'mis-boletas', loadComponent: () => import('./boletas/mis-boletas/mis-boletas.component').then(m => m.MisBoletasComponent) },
      { path: 'documentos', loadComponent: () => import('./documentos/documentos-list/documentos-list.component').then(m => m.DocumentosListComponent) },
      { path: 'empleados', loadComponent: () => import('./empleados/empleados-list/empleados-list.component').then(m => m.EmpleadosListComponent) },
      { path: 'empleados/nuevo', loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) },
      { path: 'empleados/editar/:id', loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) },
      { path: 'empleados/ver/:id', loadComponent: () => import('./empleados/empleado-form/empleado-form.component').then(m => m.EmpleadoFormComponent) },
      { path: 'emision-boleta', loadComponent: () => import('./emision-boleta/emision-boleta-list/emision-boleta-list.component').then(m => m.EmisionBoletaListComponent) },
      { path: 'emision-boleta/descuentos/:id', loadComponent: () => import('./emision-boleta/emision-descuentos-form/emision-descuentos-form.component').then(m => m.EmisionDescuentosFormComponent) }
    ]
  }
];
