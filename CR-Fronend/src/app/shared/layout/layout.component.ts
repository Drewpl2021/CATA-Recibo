import { Component } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss'
})
export class LayoutComponent {
  activeMenu = 'mis-boletas';
  isBoletasOpen = true;
  isAdmin = false;
  userName = '';
  userRole = '';

  constructor(private router: Router, private authService: AuthService) {
    const user = this.authService.getUser();
    const rol = user?.rol?.toLowerCase() || '';
    const email = user?.email?.toLowerCase() || '';
    
    console.log('User Data:', user);
    this.isAdmin = rol === 'admin' || rol === 'rrhh' || email === 'admin@colegio.com' || email === 'rrhh@colegio.com';
    
    this.userName = user?.name || 'Usuario';
    if (this.isAdmin) {
      this.userRole = rol === 'admin' ? 'Administrador' : 'Recursos Humanos';
    } else {
      this.userRole = 'Empleado';
    }
  }

  setActive(menu: string) {
    this.activeMenu = menu;
  }

  toggleBoletas() {
    this.isBoletasOpen = !this.isBoletasOpen;
  }

  logout() {
    this.authService.logout();
  }
}
