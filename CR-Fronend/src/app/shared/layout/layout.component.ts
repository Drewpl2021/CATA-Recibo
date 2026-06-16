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
    if (user) {
      this.userName = user.name;
      const email = user.email;
      
      // Manejar si rol es un string o un objeto { nombre: string }
      let rolName = '';
      if (typeof user.rol === 'string') {
        rolName = user.rol;
      } else if (user.rol && typeof user.rol === 'object') {
        rolName = (user.rol as any).nombre || '';
      }
      
      const rol = rolName.toLowerCase();
      this.isAdmin = rol === 'admin' || rol === 'rrhh' || email === 'admin@colegio.com' || email === 'rrhh@colegio.com';
      
      if (this.isAdmin) {
        this.userRole = rol === 'admin' || email === 'admin@colegio.com' ? 'Administrador' : 'Recursos Humanos';
      } else {
        this.userRole = 'Empleado';
      }
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
