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
      { path: 'mis-boletas', loadComponent: () => import('./boletas/mis-boletas/mis-boletas.component').then(m => m.MisBoletasComponent) }
    ]
  }
];
