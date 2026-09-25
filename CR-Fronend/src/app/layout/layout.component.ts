import { Component, HostListener, OnInit, ChangeDetectorRef } from '@angular/core';
import { NavigationCancel, NavigationEnd, NavigationError, NavigationStart, Router, RouterModule } from '@angular/router';
import { filter } from 'rxjs/operators';
import { CommonModule } from '@angular/common';
import { AuthService,
  NotificacionService,
} from '../core/services';
import { MisModulosService, PrimerosPasosService } from '../core/services';
import { MiPerfil, ModuloPadre, Notificacion } from '../core/models';
import { MisDocumentosService } from '../core/services';
import { Documento } from '../core/models';
import { ToastService } from '../core/services';
import { ThemeService } from '../core/services';
import { FormsModule } from '@angular/forms';
import { EmpleadoService } from '../core/services';
import { FotoPerfilService } from '../core/services';
import { Empleado } from '../core/models';
import {
  etiquetaEstado, NOMBRE_ROL_LEGIBLE, TIPO_CONTRATO_OPCIONES, NIVEL_ESTUDIOS_OPCIONES,
} from '../shared/constants';
import { IconComponent } from '../shared/components/icon/icon.component';
import { FormModalComponent } from '../shared/components/form-modal/form-modal.component';
import { PistaDirective } from '../shared/directives/pista.directive';
import { fechaLegible, mensajeErrorApi, tieneLetrasYNumeros } from '../core/utils';
import { PrimerosPasosComponent } from '../shared/components/primeros-pasos/primeros-pasos.component';

/** Un paso de las migas de pan de la barra de arriba. */
interface Miga {
  etiqueta: string;
  /** Sin enlace, es solo texto: el grupo del menú y la pantalla actual. */
  enlace?: string;
  /** El grupo del menú ("Boletas y Finanzas"): se esconde en pantallas chicas. */
  grupo?: boolean;
}

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, IconComponent, PistaDirective, FormModalComponent, PrimerosPasosComponent],
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

  /**
   * Se está yendo a otra pantalla: se pinta una barra fina bajo la cabecera.
   *
   * La pantalla nueva tarda en llegar (su código se baja aparte), y mientras
   * tanto no pasaba nada: parecía que el clic no había funcionado.
   */
  navegando = false;

  private temporizadorNavegacion?: ReturnType<typeof setTimeout>;

  /**
   * Dónde estás, para la barra de arriba: "Inicio › Boletas y Finanzas ›
   * Documentos › Expediente".
   *
   * El nombre del módulo y de su grupo salen del menú que manda el backend,
   * no de una lista escrita acá: así las migas dicen exactamente lo mismo que
   * la barra lateral, y si mañana le cambian el nombre a un módulo, cambia en
   * los dos sitios. Lo que cuelga más abajo ("nuevo", "importar") sí lleva
   * nombre propio, porque no es un módulo del menú.
   */
  migas: Miga[] = [];

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

  /**
   * Los datos de la CUENTA, que existen siempre.
   *
   * Administración y RR.HH. no tienen ficha de trabajador —son cuentas para
   * operar el sistema—, así que su perfil enseña esto: su rol, su correo y
   * desde cuándo existe la cuenta.
   */
  cuentaData: MiPerfil['cuenta'] | null = null;

  // ── Primeros pasos ──

  /**
   * La guía vive detrás del foquito de la barra de arriba.
   *
   * No ocupa sitio en ninguna pantalla y está siempre a mano: se abre
   * cuando uno quiere y se cierra al ir a hacer el paso. El globito enseña
   * cuántos faltan, y deja de insistir cuando pulsa "Ya lo tengo".
   */
  mostrarGuia = false;

  /** Cuántos pasos le faltan. 0 = el globito no sale. */
  pasosPendientes = 0;

  /** Los pasos están todos hechos: el foquito sigue, sin globito. */
  guiaCompleta = false;

  toggleGuia(): void {
    this.mostrarGuia = !this.mostrarGuia;

    if (this.mostrarGuia) {
      this.showUserMenu = false;
      this.showProfileModal = false;
      this.showPasswordModal = false;
      this.showNotifications = false;
      // Se vuelve a pedir al abrir: puede haber avanzado desde que entró.
      this.primerosPasosService.cargar();
    }
  }

  cerrarGuia(): void {
    this.mostrarGuia = false;
    this.cdr.detectChanges();
  }

  /** Desde el menú del usuario: lo mismo que pulsar el foquito. */
  abrirGuia(): void {
    this.showUserMenu = false;
    this.mostrarGuia = false;
    this.toggleGuia();
  }

  /** Una fecha suelta del perfil, en palabras. */
  fechaDeCuenta(valor?: string | null): string {
    return valor ? fechaLegible(valor) : '—';
  }

  /**
   * La foto de perfil, ya en memoria.
   *
   * Es una URL de objeto (blob:), no la ruta del servidor: la imagen vive en
   * el disco privado y su petición necesita el token, así que no se puede
   * poner en un `<img src>` apuntando al backend. Se trae una vez y se pinta
   * desde acá, en la cabecera y en el modal.
   */
  fotoUrl: string | null = null;
  subiendoFoto = false;

  /**
   * "Activo" / "Inactivo" para el panel del perfil.
   *
   * Antes se pintaba el valor crudo del backend, que viene en minúscula, y
   * salía "activo" en medio de una ficha donde todo lo demás va con
   * mayúscula. Sin ficha vinculada se asume activo: la persona está usando
   * el sistema en ese momento.
   */
  get estadoDelPerfil(): string {
    return etiquetaEstado(this.empleadoData?.estado);
  }

  /** Un trabajador dado de baja no se pinta en verde. */
  get perfilDadoDeBaja(): boolean {
    return this.empleadoData?.estado === 'inactivo';
  }

  /**
   * "Plazo fijo", no "plazo_fijo".
   *
   * La base guarda la clave con guion bajo, y sacarla cruda a una ficha que
   * lee el propio trabajador se ve a medio hacer. La etiqueta sale del mismo
   * catálogo que usa el formulario de alta, así que no hay dos maneras de
   * llamar a lo mismo.
   */
  get tipoDeContrato(): string {
    const valor = this.empleadoData?.tipo_contrato;
    if (!valor) return '-';

    return TIPO_CONTRATO_OPCIONES.find((t) => t.value === valor)?.label ?? valor;
  }

  get nivelDeEstudios(): string {
    const valor = this.empleadoData?.nivel_estudios;
    if (!valor) return '-';

    return NIVEL_ESTUDIOS_OPCIONES.find((n) => n.value === valor)?.label ?? valor;
  }
  
  // ── Cambiar contraseña ──
  currentPassword = '';
  newPassword = '';
  newPasswordConfirm = '';
  changingPassword = false;
  /** Los errores se muestran al intentar guardar, no mientras se escribe. */
  intentoPassword = false;
  /** Lo que respondió el backend para cada campo. */
  errorPasswordActual = '';
  errorPasswordNuevo = '';

  get faltaPasswordActual(): boolean {
    return this.intentoPassword && !this.currentPassword;
  }

  get passwordNuevaCorta(): boolean {
    return this.intentoPassword && this.newPassword.length < 8;
  }

  get passwordDebil(): boolean {
    return this.intentoPassword && this.newPassword.length >= 8 && !tieneLetrasYNumeros(this.newPassword);
  }

  get passwordsNoCoinciden(): boolean {
    return this.intentoPassword && this.newPassword.length >= 8 && this.newPassword !== this.newPasswordConfirm;
  }

  constructor(
    private router: Router,
    private authService: AuthService,
    private misModulosService: MisModulosService,
    private misDocumentosService: MisDocumentosService,
    private notificacionService: NotificacionService,
    private toastService: ToastService,
    private empleadoService: EmpleadoService,
    private fotoPerfilService: FotoPerfilService,
    public themeService: ThemeService,
    private primerosPasosService: PrimerosPasosService,
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
            // Ya se sabe cómo se llaman los módulos: las migas se rehacen
            // para que la primera pantalla también salga con su grupo.
            this.armarMigas(this.router.url);
          
            const grupoActivo = this.modulosPadre.find((padre) =>
              padre.modulos.some((hijo) => this.getRouterLink(hijo.ruta) === this.activeMenu)
            );
            if (grupoActivo) {
              this.abrirSoloGrupo(grupoActivo.id);
            } else if (this.modulosPadre.length > 0) {
              this.abrirSoloGrupo(this.modulosPadre[0].id);
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
    this.cargarFoto();
  }

  // ── Foto de perfil ──

  /**
   * Trae la imagen si la cuenta tiene una.
   *
   * Si falla no se avisa: sin foto se ven las iniciales, que es exactamente
   * lo que había antes. Un error acá no debería sacar un aviso rojo en la
   * cabecera cada vez que se entra.
   */
  private cargarFoto(): void {
    const user = this.authService.getUser();

    if (!user?.foto) {
      this.liberarFoto();
      return;
    }

    this.fotoPerfilService.ver(user.id).subscribe({
      next: (blob) => {
        this.liberarFoto();
        this.fotoUrl = URL.createObjectURL(blob);
        this.cdr.detectChanges();
      },
      error: () => this.liberarFoto(),
    });
  }

  /** Suelta la URL de memoria anterior: si no, cada cambio deja una colgada. */
  private liberarFoto(): void {
    if (this.fotoUrl) {
      URL.revokeObjectURL(this.fotoUrl);
      this.fotoUrl = null;
    }
  }

  /**
   * Se sube en cuanto se elige, sin un botón de "guardar" aparte: es una sola
   * cosa y esperar a confirmar solo agrega un paso.
   */
  alElegirFoto(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    // Se limpia el input para que volver a elegir la MISMA foto dispare el
    // evento otra vez (si no, el navegador lo considera "sin cambios").
    input.value = '';

    // El backend lo valida igual; acá se avisa antes de gastar la subida.
    if (archivo.size > 4 * 1024 * 1024) {
      this.toastService.error('La foto pesa demasiado', 'El máximo son 4 MB. Prueba con una más pequeña.');
      return;
    }

    this.subiendoFoto = true;
    this.fotoPerfilService.subirMia(archivo).subscribe({
      next: () => {
        this.subiendoFoto = false;
        // Se releen el usuario (para que la ruta quede guardada) y la imagen.
        this.authService.refrescarUsuario().subscribe({
          next: () => this.cargarFoto(),
          error: () => this.cargarFoto(),
        });
        this.toastService.success('Foto actualizada', 'Así te verán en el sistema.');
      },
      error: (err) => {
        this.subiendoFoto = false;
        const msg = err?.error?.errors?.foto?.[0] ?? 'No se pudo subir la foto.';
        this.toastService.error('No se subió', msg);
      },
    });
  }

  /** Quita la foto y vuelve a las iniciales. */
  quitarFoto(): void {
    this.fotoPerfilService.quitarMia().subscribe({
      next: () => {
        this.liberarFoto();
        this.authService.refrescarUsuario().subscribe({ next: () => {}, error: () => {} });
        this.toastService.success('Foto quitada', 'Vuelves a aparecer con tus iniciales.');
        this.cdr.detectChanges();
      },
      error: () => this.toastService.error('Error', 'No se pudo quitar la foto.'),
    });
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

  /**
   * Cómo se llama cada trozo de URL que no es un módulo del menú.
   *
   * Los nombres de módulo salen del backend, pero el menú llega un instante
   * después que la URL: mientras tanto, el nombre se saca de la propia ruta.
   * Los que quedarían mal escritos así ("Mis boletas", "Auditoria") llevan
   * su nombre aquí, para que no haya un parpadeo con la palabra chueca.
   */
  private static readonly NOMBRE_DE_SEGMENTO: Record<string, string> = {
    dashboard: 'Panel de Control',
    areas: 'Áreas',
    auditoria: 'Auditoría',
    'conceptos-pago': 'Conceptos de Pago',
    'emision-boleta': 'Emisión de Boletas',
    'historial-boletas': 'Historial de boletas',
    'mis-boletas': 'Mis Boletas',
    'mis-documentos': 'Mis Documentos',
    'mis-vacaciones': 'Mis Vacaciones',
    modulos: 'Módulos',
    'modulos-padre': 'Módulos Padre',
    nuevo: 'Nuevo trabajador',
    importar: 'Importar desde Excel',
    editar: 'Editar',
    ver: 'Su ficha',
    'subir-anteriores': 'Subir anteriores',
    descuentos: 'Descuentos',
    corrida: 'Planilla agrupada',
    'sin-agrupar': 'Sin agrupar',
    detalle: 'Detalle',
  };

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

    this.router.events.subscribe((e) => {
      if (e instanceof NavigationStart) {
        // Solo si la espera se nota: en un viaje de 50 ms, una barra que
        // aparece y desaparece es otro parpadeo.
        clearTimeout(this.temporizadorNavegacion);
        this.temporizadorNavegacion = setTimeout(() => (this.navegando = true), 150);
      } else if (e instanceof NavigationEnd || e instanceof NavigationCancel || e instanceof NavigationError) {
        clearTimeout(this.temporizadorNavegacion);
        this.navegando = false;
      }
    });

    // 1. Cargar menú dinámico
    this.cargarModulos();

    // El globito del foquito: cuántos primeros pasos le faltan.
    this.primerosPasosService.estado$.subscribe((estado) => {
      this.pasosPendientes = estado ? estado.total - estado.hechos : 0;
      // Si ya pulsó "Ya lo tengo", el foquito deja de contar: sigue ahí
      // para mirar, pero no insiste.
      if (estado?.vista) this.pasosPendientes = 0;
      this.guiaCompleta = !!estado && estado.hechos >= estado.total;
      this.cdr.detectChanges();
    });
    this.primerosPasosService.cargar();


    // 2. Suscribirse a boletas pendientes
    // El servicio ya manda solo los que le faltan firmar: acá no se filtra.
    this.misDocumentosService.documentos$.subscribe({
      next: (docs) => {
        this.documentosPendientes = docs;
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

    // Solo los pendientes, y solo los primeros: la campana no es un listado.
    // Antes esto se traía TODOS los documentos del trabajador en cada
    // entrada al sistema para quedarse con los tres que enseña.
    this.misDocumentosService.refrescarPendientes().subscribe({
      error: () => {
        // Ignorar si falla, por ej. si el usuario es un admin sin documentos
      }
    });
  }

  toggleSidebar(): void {
    this.sidebarVisible = !this.sidebarVisible;
  }

  /**
   * En móvil el menú NO empuja el contenido: flota encima y lo tapa entero.
   *
   * Por eso, ahí, entrar a una pantalla tiene que cerrarlo. Sin esto se
   * elegía un módulo, la pantalla cargaba debajo y el menú se quedaba
   * delante tapándola — y el botón de la hamburguesa queda justo debajo
   * del menú, así que tampoco había con qué cerrarlo: la aplicación se
   * quedaba trabada en el menú.
   */
  private get esMovil(): boolean {
    return window.innerWidth <= 768;
  }

  cerrarMenuEnMovil(): void {
    if (this.esMovil) {
      this.sidebarVisible = false;
    }
  }

  /** El fondo oscuro de detrás del menú: pulsarlo lo cierra. */
  cerrarMenuDesdeElFondo(): void {
    this.sidebarVisible = false;
  }

  @HostListener('document:keydown.escape')
  alPulsarEscape(): void {
    // Primero lo de encima: el panel o el modal que esté abierto. Solo si no
    // hay nada abierto, Escape cierra el menú del teléfono.
    if (this.showProfileModal || this.showNotifications || this.showPasswordModal
        || this.showUserMenu || this.mostrarGuia) {
      this.showProfileModal = false;
      this.showNotifications = false;
      this.showPasswordModal = false;
      this.showUserMenu = false;
      this.mostrarGuia = false;
      this.cdr.detectChanges();
      return;
    }

    this.cerrarMenuEnMovil();
  }

  /**
   * Dónde EMPEZÓ el clic sobre el fondo de un modal.
   *
   * Igual que en app-form-modal: al seleccionar un texto de dentro y soltar
   * el ratón fuera, el modal se cerraba de golpe.
   */
  private clicEmpezoFuera = false;

  alPresionarEnElFondo(evento: MouseEvent): void {
    this.clicEmpezoFuera = evento.target === evento.currentTarget;
  }

  /** Cierra solo si el clic empezó y terminó en el fondo. */
  seCierraDesdeElFondo(evento: MouseEvent): boolean {
    const cierra = this.clicEmpezoFuera && evento.target === evento.currentTarget;
    this.clicEmpezoFuera = false;

    return cierra;
  }

  /**
   * Al girar el teléfono o cruzar el ancho de tableta, el menú vuelve a lo
   * que toca en cada caso: abierto en escritorio, cerrado en móvil.
   *
   * Solo actúa al CRUZAR el límite, no en cada píxel del arrastre: en
   * escritorio, colapsar el menú es una decisión —se hace para ganar sitio—
   * y estirar un poco la ventana no debería deshacerla.
   */
  private eraMovil = window.innerWidth <= 768;

  @HostListener('window:resize')
  alCambiarElAncho(): void {
    const ahoraEsMovil = this.esMovil;

    if (ahoraEsMovil !== this.eraMovil) {
      this.eraMovil = ahoraEsMovil;
      this.sidebarVisible = !ahoraEsMovil;
    }
  }

  /**
   * Los grupos funcionan como acordeón: abrir uno cierra los demás.
   *
   * Antes cada grupo se abría por su cuenta y ninguno se cerraba, así que
   * bastaba con pasear por tres pantallas de grupos distintos para tener
   * TODO el menú desplegado: la barra se hacía más alta que la pantalla y
   * el "Cerrar sesión" se iba fuera de vista.
   */
  toggleGroup(id: string): void {
    if (this.openGroups.has(id)) {
      this.openGroups.delete(id);
      return;
    }
    this.abrirSoloGrupo(id);
  }

  /** Deja abierto ese grupo y ningún otro. */
  private abrirSoloGrupo(id: string): void {
    this.openGroups.clear();
    this.openGroups.add(id);
  }

  isGroupOpen(id: string): boolean {
    return this.openGroups.has(id);
  }

  setActive(ruta: string): void {
    this.activeMenu = ruta;
    this.cerrarMenuEnMovil();
  }

  /**
   * Marca como activo el módulo cuya ruta coincide con la URL actual.
   * Compara por prefijo para que "/inicio/empleados/editar/xyz" siga
   * resaltando "Empleados".
   */
  private sincronizarMenuActivo(url: string): void {
    const limpia = url.split('?')[0].split('#')[0];
    this.activeMenu = limpia;

    this.armarMigas(limpia);

    const grupoActivo = this.modulosPadre.find((padre) =>
      padre.modulos.some((hijo) => this.esRutaActiva(hijo.ruta))
    );
    if (grupoActivo) {
      // Solo el del sitio donde se está: si se fueran sumando, al cabo de
      // unas pantallas el menú entero quedaría desplegado.
      this.abrirSoloGrupo(grupoActivo.id);
    }
  }

  /**
   * Arma las migas de la URL actual.
   *
   * Se rehace en cada navegación y también cuando llega el menú del backend:
   * al entrar por un enlace directo, la URL se conoce antes que los nombres
   * de los módulos, y sin esto la primera pantalla salía sin su grupo.
   */
  private armarMigas(url: string): void {
    const partes = url.split('?')[0].split('#')[0].split('/').filter((p) => p && p !== 'inicio');

    // El tablero es la casa: no se pone "Inicio › Inicio".
    if (!partes.length || partes[0] === 'dashboard') {
      this.migas = [{ etiqueta: 'Panel de Control' }];
      return;
    }

    const migas: Miga[] = [{ etiqueta: 'Inicio', enlace: '/inicio/dashboard' }];
    const delMenu = this.modulosPadre
      .flatMap((padre) => padre.modulos.map((modulo) => ({ padre, modulo })))
      .find((x) => this.getRouterLink(x.modulo.ruta) === `/inicio/${partes[0]}`);

    if (delMenu) {
      migas.push({ etiqueta: delMenu.padre.nombre, grupo: true });
    }
    migas.push({
      etiqueta: delMenu?.modulo.nombre ?? this.nombreDeSegmento(partes[0]) ?? partes[0],
      enlace: `/inicio/${partes[0]}`,
    });

    for (let i = 1; i < partes.length; i++) {
      const etiqueta = this.nombreDeSegmento(partes[i], partes[i - 1]);
      if (etiqueta) {
        migas.push({ etiqueta });
      }
    }

    this.migas = migas;
  }

  /**
   * El nombre de un trozo de URL; null cuando no hay que enseñarlo.
   *
   * Los identificadores no se pintan: "Documentos › 01a0a6bc-2c11-…" no le
   * dice nada a nadie. El de un expediente se cambia por la palabra, que sí.
   */
  private nombreDeSegmento(parte: string, anterior?: string): string | null {
    const conocido = LayoutComponent.NOMBRE_DE_SEGMENTO[parte];
    if (conocido) {
      return conocido;
    }
    if (/^[0-9a-f-]{16,}$/i.test(parte) || /^\d+$/.test(parte)) {
      return anterior === 'documentos' ? 'Expediente' : null;
    }

    return parte.charAt(0).toUpperCase() + parte.slice(1).replace(/-/g, ' ');
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
    
    // Su propio perfil, por la ruta del autoservicio. Antes se pedía a
    // employees/{id}, que es solo de RR.HH. y Administración: al trabajador
    // le contestaba 403 y el perfil se quedaba con un guion en cada dato,
    // que es justo lo que veía alguien recién dado de alta.
    //
    // Se pide SIEMPRE, tenga ficha o no: las cuentas de Administración y
    // RR.HH. nacen sin trabajador detrás y aun así tienen qué enseñar.
    if (!this.cuentaData) {
      this.empleadoService.miPerfil().subscribe({
        next: (res) => {
          if (res.success) {
            this.empleadoData = res.data.empleado;
            this.cuentaData = res.data.cuenta;
            this.cdr.detectChanges();
          }
        },
        // Sin aviso rojo: el perfil se abre igual con lo que ya se sabe de
        // la sesión (su nombre, su correo y su rol).
        error: () => undefined,
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

    this.limpiarFormularioPassword();
  }

  closePassword(): void {
    this.showPasswordModal = false;
    this.limpiarFormularioPassword();
    this.cdr.detectChanges();
  }

  private limpiarFormularioPassword(): void {
    this.currentPassword = '';
    this.newPassword = '';
    this.newPasswordConfirm = '';
    this.intentoPassword = false;
    this.errorPasswordActual = '';
    this.errorPasswordNuevo = '';
  }

  toggleTheme(): void {
    this.themeService.toggleTheme();
  }

  /**
   * Las mismas reglas que la pantalla de primer ingreso (8 caracteres, letras
   * y números), revisadas aquí para no esperar al rechazo del backend. Lo que
   * solo sabe el backend (la clave actual, los datos personales) vuelve y se
   * muestra debajo de su campo.
   */
  submitPasswordChange(): void {
    if (this.changingPassword) return;

    this.intentoPassword = true;
    this.errorPasswordActual = '';
    this.errorPasswordNuevo = '';
    if (this.faltaPasswordActual || this.passwordNuevaCorta || this.passwordDebil || this.passwordsNoCoinciden) {
      return;
    }

    this.changingPassword = true;
    this.authService.cambiarPassword({
      password_actual: this.currentPassword,
      password_nuevo: this.newPassword,
      password_nuevo_confirmation: this.newPasswordConfirm,
    }).subscribe({
      next: () => {
        this.changingPassword = false;
        this.toastService.success('Contraseña cambiada', 'La próxima vez entras con la nueva.');
        this.closePassword();
      },
      error: (err) => {
        this.changingPassword = false;
        this.errorPasswordActual = err?.error?.errors?.password_actual?.[0] ?? '';
        this.errorPasswordNuevo = err?.error?.errors?.password_nuevo?.[0] ?? '';
        if (!this.errorPasswordActual && !this.errorPasswordNuevo) {
          this.toastService.error('No se cambió la contraseña', mensajeErrorApi(err, 'Intenta de nuevo en un momento.'));
        }
      },
    });
  }
}
