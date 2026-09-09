import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { Router, RouterModule, NavigationEnd } from '@angular/router';
import { CommonModule } from '@angular/common';
import { filter } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { MisModulosService, ModuloPadre } from '../../core/services/mis-modulos.service';
import { MisDocumentosService, MiDocumento } from '../../core/services/mis-documentos.service';
import { ToastService } from '../../core/services/toast.service';
import { ThemeService } from '../../core/services/theme.service';
import { FormsModule } from '@angular/forms';
import { EmpleadoService, Empleado } from '../../core/services/empleado.service';
import { NotificacionService, NotificacionItem } from '../../core/services/notificacion.service';

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
  isSidebarOpen = false;

  // Módulos dinámicos del backend
  modulosPadre: ModuloPadre[] = [];
  openGroups: Set<string> = new Set();
  cargandoModulos = true;

  // Notificaciones (Campanita)
  notificaciones: NotificacionItem[] = [];
  noLeidasCount = 0;
  documentosPendientes: MiDocumento[] = [];
  showNotifications = false;

  // Firma rápida
  showSignModal = false;
  boletaAFirmar: MiDocumento | null = null;
  passwordFirma = '';
  signErrorMsg = '';
  firmandoDoc = false;

  // User Dropdown & Profile
  showUserMenu = false;
  showProfileModal = false;
  showPasswordModal = false;
  
  userEmail = '';
  userInitials = '';
  empleadoData: Empleado | null = null;
  
  // Cambiar password form
  currentPassword = '';
  newPassword = '';
  newPasswordConfirm = '';
  changingPassword = false;

  // Total dinámico que unifica notificaciones del sistema y boletas pendientes sin duplicar
  get totalAvisosCount(): number {
    const boletasPendientesCount = this.documentosPendientes.length;
    const docIdsPendientes = new Set(this.documentosPendientes.map(d => d.id));
    const notifsNoLeidasCount = this.notificaciones.filter(n => {
      if (n.leida_at) return false;
      if (n.documento_id && docIdsPendientes.has(n.documento_id)) return false;
      return true;
    }).length;

    return boletasPendientesCount + notifsNoLeidasCount;
  }

  constructor(
    private router: Router,
    private authService: AuthService,
    private misModulosService: MisModulosService,
    private misDocumentosService: MisDocumentosService,
    private notificacionService: NotificacionService,
    private toastService: ToastService,
    private empleadoService: EmpleadoService,
    public themeService: ThemeService,
    private cdr: ChangeDetectorRef
  ) {
    const user = this.authService.getUser();
    if (user) {
      this.userName = user.name;
      this.userEmail = user.email || '';
      this.userInitials = this.userName.substring(0, 2).toUpperCase();
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

    // Sincronizar el elemento activo del menú lateral ante cualquier cambio de ruta
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd)
    ).subscribe((event: NavigationEnd) => {
      this.syncActiveMenuWithUrl(event.urlAfterRedirects || event.url);
    });
  }

  syncActiveMenuWithUrl(url: string): void {
    if (!url) return;
    const cleanUrl = url.split('?')[0].split('#')[0];
    let subRuta = cleanUrl;
    if (cleanUrl.startsWith('/inicio/')) {
      subRuta = '/' + cleanUrl.substring('/inicio/'.length);
    } else if (cleanUrl === '/inicio') {
      subRuta = this.homeRoute.replace('/inicio', '');
    }

    // Mapear rutas alias conocidas
    if (subRuta === '/emision-boleta') subRuta = '/boletas';
    if (subRuta === '/conceptos') subRuta = '/descuentos';

    this.activeMenu = subRuta;

    // Asegurar que el grupo padre esté desplegado en el menú lateral
    if (this.modulosPadre && this.modulosPadre.length > 0) {
      for (const padre of this.modulosPadre) {
        if (padre.modulos && padre.modulos.some(m => m.ruta === subRuta)) {
          this.openGroups.add(padre.id);
          break;
        }
      }
    }
    this.cdr.markForCheck();
  }

  toggleNotifications(): void {
    this.showNotifications = !this.showNotifications;
    if (this.showNotifications) {
      this.showUserMenu = false;
      this.showProfileModal = false;
      this.showPasswordModal = false;
      this.notificacionService.getNotificaciones().subscribe({ error: () => {} });
      this.misDocumentosService.getMisDocumentos().subscribe({ error: () => {} });
    }
  }

  get homeRoute(): string {
    const user = this.authService.getUser();
    let rol = '';
    if (typeof user?.rol === 'string') {
      rol = user.rol.toLowerCase();
    } else if (user?.rol && typeof user.rol === 'object') {
      rol = ((user.rol as any).nombre || '').toLowerCase();
    }
    const email = user?.email?.toLowerCase() || '';
    const isAdmin = rol === 'admin' || rol === 'rrhh' || email === 'admin@colegio.com' || email === 'rrhh@colegio.com';
    return isAdmin ? '/inicio/dashboard' : '/inicio/mis-boletas';
  }

  // Rutas habilitadas para mostrar en el menú (solo módulos con pantallas programadas y activas)
  rutasPermitidas = [
    '/dashboard', '/mis-boletas', '/mis-documentos', '/documentos', 
    '/empleados', '/boletas', '/historial-boletas', '/planillas', 
    '/areas', '/cargos', '/periodos', '/sedes', '/vacaciones',
    '/catalogos', '/conceptos', '/descuentos'
  ];

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

          // Sincronizar el menú activo con la URL actual
          this.syncActiveMenuWithUrl(this.router.url);
        }
        this.cargandoModulos = false;
      },
      error: () => {
        // Si falla el endpoint, la barra queda vacía pero no rompe
        this.cargandoModulos = false;
      }
    });

    // 2. Suscribirse a notificaciones del backend
    this.notificacionService.notificaciones$.subscribe({
      next: (items) => this.notificaciones = items
    });
    this.notificacionService.noLeidas$.subscribe({
      next: (count) => this.noLeidasCount = count
    });
    this.notificacionService.getNotificaciones().subscribe({
      error: () => {}
    });

    // 3. Suscribirse a boletas pendientes
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

    // Sincronización inicial
    this.syncActiveMenuWithUrl(this.router.url);
  }

  irABoletas(): void {
    this.showNotifications = false;
    this.syncActiveMenuWithUrl('/inicio/mis-boletas');
    this.router.navigate(['/inicio/mis-boletas']);
  }

  clickNotificacion(notif: NotificacionItem): void {
    if (!notif.leida_at) {
      this.notificacionService.marcarLeida(notif.id).subscribe();
    }
    this.showNotifications = false;

    // Si la notificación apunta a una boleta
    if (notif.documento_id || notif.tipo === 'boleta_disponible' || notif.tipo === 'boleta_nueva') {
      let docPendiente = this.documentosPendientes.find(d => d.id === notif.documento_id);
      if (!docPendiente && this.documentosPendientes.length === 1) {
        docPendiente = this.documentosPendientes[0];
      }
      if (docPendiente) {
        this.iniciarFirmaRapida(docPendiente);
      } else {
        this.irABoletas();
      }
    }
  }

  marcarTodasNotificacionesLeidas(): void {
    this.notificacionService.marcarTodas().subscribe({
      next: () => {
        this.toastService.success('Avisos', 'Todas las notificaciones se marcaron como leídas.');
      }
    });
  }

  toggleSidebar(): void {
    this.isSidebarOpen = !this.isSidebarOpen;
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
    // Redirecciones directas a componentes existentes
    if (ruta === '/boletas') return '/inicio/emision-boleta';
    if (ruta === '/descuentos') return '/inicio/conceptos';
    return `/inicio${ruta}`;
  }

  logout(): void {
    this.authService.logout();
  }

  iniciarFirmaRapida(doc: MiDocumento, event?: Event): void {
    if (event) {
      event.stopPropagation();
      event.preventDefault();
    }
    this.showNotifications = false;
    this.boletaAFirmar = doc;
    this.passwordFirma = '';
    this.signErrorMsg = '';
    this.showSignModal = true;
    this.cdr.detectChanges();
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
          this.misDocumentosService.getMisDocumentos().subscribe();
          this.notificacionService.getNotificaciones().subscribe();
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

  // ── USER MENU & PROFILE ──

  toggleUserMenu(): void {
    // Cerrar todos primero, luego toggle
    this.showProfileModal = false;
    this.showPasswordModal = false;
    this.showNotifications = false;
    this.showUserMenu = !this.showUserMenu;
  }

  openProfile(): void {
    setTimeout(() => {
      this.showProfileModal = true;
      if (this.showProfileModal) {
        this.showUserMenu = false;
        this.showPasswordModal = false;
        this.showNotifications = false;
      }
    }, 0);
    
    const empleadoId = this.authService.getEmpleadoId();
    if (empleadoId && !this.empleadoData) {
      this.empleadoService.getEmpleado(empleadoId).subscribe({
        next: (res) => { 
          if (res.success) {
            this.empleadoData = res.data; 
          }
        },
        error: (err) => { console.error('Error fetching empleado data:', err); }
      });
    }
  }

  closeProfile(): void {
    this.showProfileModal = false;
    this.cdr.detectChanges();
  }

  openPassword(): void {
    setTimeout(() => {
      this.showPasswordModal = true;
      if (this.showPasswordModal) {
        this.showUserMenu = false;
        this.showProfileModal = false;
        this.showNotifications = false;
      }
    }, 0);
    
    this.currentPassword = '';
    this.newPassword = '';
    this.newPasswordConfirm = '';
  }

  closePassword(): void {
    this.showPasswordModal = false;
    this.cdr.detectChanges();
  }

  toggleTheme(): void {
    this.themeService.toggleTheme();
  }

  submitPasswordChange(): void {
    if (!this.currentPassword || !this.newPassword || !this.newPasswordConfirm) {
      this.toastService.warning('Aviso', 'Completa todos los campos.');
      return;
    }
    if (this.newPassword !== this.newPasswordConfirm) {
      this.toastService.error('Error', 'Las nuevas contraseñas no coinciden.');
      return;
    }
    this.changingPassword = true;
    this.authService.cambiarPassword({
      current_password: this.currentPassword,
      new_password: this.newPassword,
      new_password_confirmation: this.newPasswordConfirm
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.success('Éxito', 'Contraseña actualizada correctamente.');
          this.closePassword();
        }
        this.changingPassword = false;
      },
      error: (err) => {
        this.toastService.error('Error', err.error?.message || 'Error al cambiar contraseña.');
        this.changingPassword = false;
      }
    });
  }
}
