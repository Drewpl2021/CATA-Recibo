import { Component, OnInit } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/services/auth.service';
import { MisModulosService, ModuloPadre } from '../../core/services/mis-modulos.service';
import { MisDocumentosService, MiDocumento } from '../../core/services/mis-documentos.service';
import { ToastService } from '../../core/services/toast.service';
import { FormsModule } from '@angular/forms';

// Mapa de iconos SVG por nombre (del seeder de Jordan)
const ICON_MAP: Record<string, string> = {
  receipt:        `<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/>`,
  settings:       `<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>`,
  person:         `<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>`,
  people:         `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>`,
  table_chart:    `<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>`,
  description:    `<path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M17 8h4"/><path d="M17 12h4"/><path d="M17 16h4"/>`,
  remove_circle:  `<circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>`,
  folder:         `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>`,
  domain:         `<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>`,
  badge:          `<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>`,
  admin_panel_settings: `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
  date_range:     `<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>`,
  location_on:    `<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>`,
  view_module:    `<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>`,
  folder_open:    `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><polyline points="8 10 12 14 16 10"/>`,
  receipt_long:   `<path d="M15 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M17 8h4"/><path d="M17 12h4"/><path d="M17 16h4"/><path d="M12 8H8"/><path d="M12 12H8"/><path d="M12 16H8"/>`,
  dashboard:      `<rect x="3" y="3" width="8" height="9" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="12" width="8" height="9" rx="1"/><rect x="3" y="16" width="8" height="5" rx="1"/>`,
  folder_shared:  `<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><path d="M12 11a2 2 0 1 0 4 0 2 2 0 0 0-4 0"/><path d="M10 19c0-2.2 1.8-4 4-4s4 1.8 4 4"/>`,
};

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss'
})
export class LayoutComponent implements OnInit {
  activeMenu = '';
  userName = '';
  userRole = '';

  // Módulos dinámicos del backend
  modulosPadre: ModuloPadre[] = [];
  openGroups: Set<string> = new Set();
  cargandoModulos = true;

  // Notificaciones (Campanita)
  documentosPendientes: MiDocumento[] = [];
  showNotifications = false;

  // Firma rápida
  showSignModal = false;
  boletaAFirmar: MiDocumento | null = null;
  passwordFirma = '';
  signErrorMsg = '';
  firmandoDoc = false;

  constructor(
    private router: Router,
    private authService: AuthService,
    private misModulosService: MisModulosService,
    private misDocumentosService: MisDocumentosService,
    private toastService: ToastService
  ) {
    const user = this.authService.getUser();
    if (user) {
      this.userName = user.name;
      let rolName = '';
      if (typeof user.rol === 'string') {
        rolName = user.rol;
      } else if (user.rol && typeof user.rol === 'object') {
        rolName = (user.rol as any).nombre || '';
      }
      const rol = rolName.toLowerCase();
      if (rol === 'admin') this.userRole = 'Administrador';
      else if (rol === 'rrhh') this.userRole = 'Recursos Humanos';
      else this.userRole = 'Empleado';
    }
  }

  toggleNotifications(): void {
    this.showNotifications = !this.showNotifications;
  }

  // Rutas que sí hemos construido en el frontend
  rutasPermitidas = ['/dashboard', '/mis-boletas', '/documentos', '/empleados', '/boletas', '/historial-boletas'];

  ngOnInit(): void {
    // 1. Cargar menú dinámico
    this.misModulosService.getMisModulos().subscribe({
      next: (res) => {
        if (res.success) {
          // Filtrar para ocultar los módulos que Jordan agregó pero aún no programamos en Angular
          const modulosFiltrados = res.data.map(padre => {
            return {
              ...padre,
              modulos: padre.modulos.filter(hijo => this.rutasPermitidas.includes(hijo.ruta))
            };
          }).filter(padre => padre.modulos.length > 0); // Si el padre se quedó sin hijos, lo ocultamos

          this.modulosPadre = modulosFiltrados;
          
          if (this.modulosPadre.length > 0) {
            this.openGroups.add(this.modulosPadre[0].id);
          }
        }
        this.cargandoModulos = false;
      },
      error: () => {
        // Si falla el endpoint, la barra queda vacía pero no rompe
        this.cargandoModulos = false;
      }
    });

    // 2. Suscribirse a boletas pendientes
    this.misDocumentosService.documentos$.subscribe({
      next: (docs) => {
        this.documentosPendientes = docs.filter(doc => doc.estado_firma !== 'firmado');
      }
    });
    this.misDocumentosService.getMisDocumentos().subscribe({
      error: () => {
        // Ignorar si falla, por ej. si el usuario es un admin sin documentos
      }
    });
  }

  toggleGroup(id: string): void {
    if (this.openGroups.has(id)) {
      this.openGroups.delete(id);
    } else {
      this.openGroups.add(id);
    }
  }

  isGroupOpen(id: string): boolean {
    return this.openGroups.has(id);
  }

  setActive(ruta: string): void {
    this.activeMenu = ruta;
  }

  getIcon(iconName: string): string {
    return ICON_MAP[iconName] ?? `<circle cx="12" cy="12" r="10"/>`;
  }

  // Convierte "/mis-boletas" → "/inicio/mis-boletas"
  getRouterLink(ruta: string): string {
    // Parche temporal: Jordan puso '/boletas' en la BD, pero nuestro componente se llama 'emision-boleta'
    if (ruta === '/boletas') return '/inicio/emision-boleta';
    return `/inicio${ruta}`;
  }

  logout(): void {
    this.authService.logout();
  }

  iniciarFirmaRapida(doc: MiDocumento, event: Event): void {
    event.stopPropagation();
    event.preventDefault();
    this.boletaAFirmar = doc;
    this.passwordFirma = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
  }

  closeSignModal(): void {
    this.showSignModal = false;
    this.boletaAFirmar = null;
    this.passwordFirma = '';
    this.signErrorMsg = '';
  }

  confirmarFirmaRapidaModal(): void {
    if (!this.passwordFirma) {
      this.signErrorMsg = 'Por favor, ingresa tu contraseña para firmar.';
      return;
    }

    if (!this.boletaAFirmar) return;

    this.firmandoDoc = true;
    this.signErrorMsg = '';

    this.misDocumentosService.firmar(this.boletaAFirmar.id, this.passwordFirma).subscribe({
      next: (res) => {
        this.firmandoDoc = false;
        if (res.success) {
          this.toastService.success('¡Firma Exitosa!', `Boleta firmada correctamente.`);
          this.closeSignModal();
        } else {
          this.signErrorMsg = res.message || 'Error al firmar.';
        }
      },
      error: (err) => {
        this.firmandoDoc = false;
        console.error('Error firmando boleta', err);
        this.signErrorMsg = err?.error?.message || 'Contraseña incorrecta o error del servidor.';
      }
    });
  }

  getMesNombre(mesNum?: number): string {
    if (!mesNum) return '';
    const meses: { [key: number]: string } = {
      1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril', 5: 'Mayo', 6: 'Junio',
      7: 'Julio', 8: 'Agosto', 9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
    };
    return meses[mesNum] ?? `Mes ${mesNum}`;
  }
}
