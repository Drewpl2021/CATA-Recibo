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

  constructor(private router: Router, private authService: AuthService) {}

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
