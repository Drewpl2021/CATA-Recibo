import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter } from 'rxjs/operators';
import { CommonModule } from '@angular/common';
import { AuthService,
  NotificacionService,
} from '../core/services';
import { MisModulosService } from '../core/services';
import { ModuloPadre, Notificacion } from '../core/models';
import { MisDocumentosService } from '../core/services';
import { Documento } from '../core/models';
import { ToastService } from '../core/services';
import { ThemeService } from '../core/services';
import { FormsModule } from '@angular/forms';
import { EmpleadoService } from '../core/services';
import { Empleado } from '../core/models';
import { NOMBRE_ROL_LEGIBLE } from '../shared/constants';
import { IconComponent } from '../shared/components/icon/icon.component';
import { fechaLegible } from '../core/utils';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, IconComponent],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss'
})
export class LayoutComponent implements OnInit {
  activeMenu = '';
  userName = '';
  userRole = '';
  /**
   * ¿Se ve la barra lateral?
   *
   * Arranca visible en escritorio y oculta en móvil, que es lo que se
   * espera en cada caso. El mismo botón sirve para las dos situaciones:
   * en escritorio la colapsa, en móvil la despliega encima del contenido.
   */
  sidebarVisible = window.innerWidth > 768;

  // Módulos dinámicos del backend
  modulosPadre: ModuloPadre[] = [];
  openGroups: Set<string> = new Set();
  cargandoModulos = true;

  // ── Avisos (la campanita) ──
  // Antes esto contaba los documentos sin firmar cada vez que se abría la
  // pantalla: servía para "tienes N pendientes", pero el aviso desaparecía
  // en cuanto se firmaba. Ahora vienen guardados del backend, con su fecha
  // y por páginas.
  documentosPendientes: Documento[] = [];
  showNotifications = false;

  avisos: Notificacion[] = [];
  noLeidas = 0;
  cargandoAvisos = false;
  paginaAvisos = 0;
  totalPaginasAvisos = 1;
  readonly AVISOS_POR_PAGINA = 5;

  // Firma rápida
  showSignModal = false;
  boletaAFirmar: Documento | null = null;
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
    this.cargarDatosUsuario();
  }


  /** Pide al backend los módulos que le tocan a este rol y arma el menú. */
  private cargarModulos(): void {
      this.misModulosService.getMisModulos().subscribe({
        next: (res) => {
          if (res.success) {
            // El backend manda todo lo que el rol puede ver; acá solo se ocultan
            // los módulos cuya pantalla todavía no existe en Angular, para que
            // nadie haga clic en un ítem que no lleva a ningún lado.
            const modulosFiltrados = res.data.map(padre => {
              return {
                ...padre,
                modulos: padre.modulos.filter(hijo => this.estaProgramada(hijo.ruta))
              };
            }).filter(padre => padre.modulos.length > 0); // Si el padre se quedó sin hijos, lo ocultamos

            this.modulosPadre = modulosFiltrados;
          
            const grupoActivo = this.modulosPadre.find((padre) =>
              padre.modulos.some((hijo) => this.getRouterLink(hijo.ruta) === this.activeMenu)
            );
            if (grupoActivo) {
              this.openGroups.add(grupoActivo.id);
            } else if (this.modulosPadre.length > 0) {
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
  }

  /** Nombre, correo, iniciales y rol legible que se ven en la cabecera. */
  private cargarDatosUsuario(): void {
    const user = this.authService.getUser();
    if (!user) return;

    this.userName = user.name;
    this.userEmail = user.email || '';
    this.userInitials = this.userName.substring(0, 2).toUpperCase();
    this.userRole = NOMBRE_ROL_LEGIBLE[this.authService.getRolNombre()] ?? 'Empleado';
  }

  toggleNotifications(): void {
    this.showNotifications = !this.showNotifications;
    if (this.showNotifications) {
      this.showUserMenu = false;
      this.showProfileModal = false;
      this.showPasswordModal = false;
      // Se pide al abrir, no al arrancar la app: así se ve lo que llegó
      // mientras la pantalla estaba abierta, sin preguntar cada minuto.
      this.cargarAvisos(0);
    }
  }

  cerrarNotificaciones(): void {
    this.showNotifications = false;
  }

  /** Fecha en formato peruano, sin el desfase de zona horaria. */
  fechaAviso(valor: string): string {
    return fechaLegible(valor);
  }

  private cargarAvisos(pagina: number): void {
    this.cargandoAvisos = true;
    this.notificacionService.listar(pagina, this.AVISOS_POR_PAGINA).subscribe({
      next: (res) => {
        if (res.success) {
          this.avisos = res.data.content;
          this.paginaAvisos = res.data.currentPage;
          this.totalPaginasAvisos = res.data.totalPages;
        }
        this.cargandoAvisos = false;
        this.cdr.detectChanges();
      },
      error: () => {
        // No es crítico: la app sigue funcionando sin la campana.
        this.cargandoAvisos = false;
      },
    });
  }

  cambiarPaginaAvisos(pagina: number): void {
    if (pagina < 0 || pagina >= this.totalPaginasAvisos) return;
    this.cargarAvisos(pagina);
  }

  marcarTodasLeidas(): void {
    this.notificacionService.marcarTodas().subscribe({
      next: () => this.cargarAvisos(this.paginaAvisos),
    });
  }

  /**
   * La boleta a la que apunta el aviso, si sigue sin firmar. Sirve para
   * ofrecer "Firmar" ahí mismo en vez de mandar a buscarla a otra pantalla.
   */
  documentoPendienteDe(aviso: Notificacion): Documento | undefined {
    if (!aviso.documento_id) return undefined;
    return this.documentosPendientes.find((d) => d.id === aviso.documento_id);
  }

  /**
   * Al tocar un aviso se marca leído y, si su boleta sigue pendiente, se abre
   * la firma rápida sin salir de acá. Si ya está firmada o el aviso es viejo,
   * lleva a Mis Boletas.
   *
   * Se sigue adelante aunque falle el marcado: dejar al usuario atascado
   * porque no se pudo actualizar un booleano sería peor.
   */
  abrirAviso(aviso: Notificacion, evento: Event): void {
    if (!aviso.leida) {
      this.notificacionService.marcarLeida(aviso.id).subscribe({
        next: () => { aviso.leida = true; },
        error: () => {},
      });
    }

    const documento = this.documentoPendienteDe(aviso);

    if (documento) {
      this.showNotifications = false;
      this.iniciarFirmaRapida(documento, evento);
      return;
    }

    this.showNotifications = false;
    this.router.navigate(['/inicio/mis-boletas']);
  }

  // Rutas habilitadas para mostrar en el menú (incluye los nuevos módulos del backend)
  /**
   * Rutas que el front tiene REALMENTE programadas bajo /inicio.
   *
   * Se leen de la configuración del Router en vez de mantenerse a mano: la
   * lista escrita a dedo se quedaba corta y escondía módulos que el backend
   * sí manda (al admin le faltaban Roles, Módulos y Módulos Padre). Así,
   * cuando se agrega una pantalla nueva, aparece sola en el menú.
   */
  private rutasProgramadas = new Set<string>();

  ngOnInit(): void {
    // 0. El ítem activo sale de la URL, no del clic: si no, al entrar por
    //    enlace directo o al recargar, la barra no marcaba nada.
    this.rutasProgramadas = this.leerRutasProgramadas();
    this.sincronizarMenuActivo(this.router.url);

    // Si a este usuario le cambiaron el rol mientras estaba dentro, su copia
    // en localStorage se quedó vieja: se pone al día antes de pintar el menú.
    const rolAntes = this.authService.getRolNombre();
    this.authService.refrescarUsuario().subscribe({
      next: () => {
        this.cargarDatosUsuario();
        // Con otro rol le tocan otros módulos: hay que volver a pedirlos.
        if (this.authService.getRolNombre() !== rolAntes) {
          this.cargarModulos();
        }
      },
      // Si falla, se sigue con lo que había: el 401 lo maneja el interceptor.
      error: () => {},
    });
    this.router.events
      .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
      .subscribe((e) => this.sincronizarMenuActivo(e.urlAfterRedirects));

    // 1. Cargar menú dinámico
    this.cargarModulos();

    // 2. Suscribirse a boletas pendientes
    this.misDocumentosService.documentos$.subscribe({
      next: (docs) => {
        this.documentosPendientes = docs.filter(doc => doc.estado_firma !== 'firmado');
      }
    });
    // El globito: cuántos avisos hay sin leer. Se pide una vez al entrar y
    // el servicio lo mantiene al día cuando se marcan como leídos.
    this.notificacionService.noLeidas$.subscribe((n) => {
      this.noLeidas = n;
      this.cdr.detectChanges();
    });
    this.notificacionService.listar(0, this.AVISOS_POR_PAGINA).subscribe({
      error: () => {
        // Si falla, la campana se queda en cero y la app sigue igual.
      },
    });

    this.misDocumentosService.getMisDocumentos().subscribe({
      error: () => {
        // Ignorar si falla, por ej. si el usuario es un admin sin documentos
      }
    });
  }

  toggleSidebar(): void {
    this.sidebarVisible = !this.sidebarVisible;
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

  /**
   * Marca como activo el módulo cuya ruta coincide con la URL actual.
   * Compara por prefijo para que "/inicio/empleados/editar/xyz" siga
   * resaltando "Empleados".
   */
  private sincronizarMenuActivo(url: string): void {
    const limpia = url.split('?')[0].split('#')[0];
    this.activeMenu = limpia;

    const grupoActivo = this.modulosPadre.find((padre) =>
      padre.modulos.some((hijo) => this.esRutaActiva(hijo.ruta))
    );
    if (grupoActivo) {
      this.openGroups.add(grupoActivo.id);
    }
  }

  /** Los paths hijos de /inicio declarados en app.routes.ts, como "/areas". */
  private leerRutasProgramadas(): Set<string> {
    const inicio = this.router.config.find((r) => r.path === 'inicio');
    const hijos = inicio?.children ?? [];
    return new Set(
      hijos
        .map((r) => r.path ?? '')
        .filter((path) => path && path !== '**' && !path.includes(':'))
        .map((path) => `/${path}`)
    );
  }

  /** ¿Hay pantalla programada para la ruta que trae este módulo del backend? */
  private estaProgramada(ruta: string): boolean {
    return this.rutasProgramadas.has(this.getRouterLink(ruta).replace('/inicio', ''));
  }

  /** ¿Este módulo del menú corresponde a la pantalla que se está viendo? */
  esRutaActiva(ruta: string): boolean {
    const enlace = this.getRouterLink(ruta);
    return this.activeMenu === enlace || this.activeMenu.startsWith(enlace + '/');
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

  iniciarFirmaRapida(doc: Documento, event: Event): void {
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
      this.empleadoService.getById(empleadoId).subscribe({
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
      password_actual: this.currentPassword,
      password_nuevo: this.newPassword,
      password_nuevo_confirmation: this.newPasswordConfirm
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toastService.success('Éxito', 'Contraseña actualizada correctamente.');
          this.closePassword();
        }
        this.changingPassword = false;
      },
      error: (err) => {
        const msg = err.error?.errors?.password_actual?.[0] || err.error?.message || 'Error al cambiar contraseña.';
        this.toastService.error('Error', msg);
        this.changingPassword = false;
      }
    });
  }
}
