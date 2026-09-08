import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { ToastService } from './toast.service';
import { Observable, tap } from 'rxjs';
import { AuthUser, CambiarPasswordPayload, RegisterPayload, RestablecerPasswordPayload, SesionData } from '../../models';
import { ApiResponse, END_POINTS } from '../../utils';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly apiUrl = environment.apiUrl;
  private readonly TOKEN_KEY = 'auth_token';
  private readonly USER_KEY = 'auth_user';
  /** Dónde estaba el usuario cuando se le cayó la sesión, para devolverlo ahí. */
  private readonly RETORNO_KEY = 'auth_retorno';

  private toast = inject(ToastService);

  /**
   * Evita avisar dos veces de lo mismo: una pantalla puede lanzar varias
   * peticiones a la vez (forkJoin), y todas responderían 401 a la vez.
   */
  private cerrandoPorExpiracion = false;

  constructor(private http: HttpClient, private router: Router) {}

  login(email: string, password: string): Observable<ApiResponse<SesionData>> {
    return this.http
      .post<ApiResponse<SesionData>>(`${this.apiUrl}/${END_POINTS.auth.login}`, { email, password })
      .pipe(tap((res) => { if (res.success) this.guardarSesion(res.data.user, res.data.token, res.data.debe_cambiar_password === true); }));
  }

  /** Autoregistro (docente sin cuenta creada por RRHH). Requiere correo @cata.edu.pe. */
  register(payload: RegisterPayload): Observable<ApiResponse<SesionData>> {
    return this.http
      .post<ApiResponse<SesionData>>(`${this.apiUrl}/${END_POINTS.auth.register}`, payload)
      .pipe(tap((res) => { if (res.success) this.guardarSesion(res.data.user, res.data.token); }));
  }

  /**
   * Vuelve a leer del backend quién es el usuario y qué rol tiene.
   *
   * El rol vive en localStorage desde el login, así que si un administrador
   * se lo cambia mientras está trabajando, su menú se queda como estaba: o le
   * faltan módulos nuevos, o le sobran unos que la API ya le rechaza con 403.
   * Se refresca al arrancar la app para que lo que ve coincida con lo que
   * puede hacer.
   */
  refrescarUsuario(): Observable<ApiResponse<any>> {
    return this.http.get<ApiResponse<any>>(`${this.apiUrl}/${END_POINTS.auth.me}`).pipe(
      tap((res) => {
        const datos = (res as any)?.data ?? res;
        // /me no devuelve la bandera del cambio obligatorio: se conserva la
        // que ya había, o se perdería el bloqueo al recargar la página.
        if (datos?.id) this.guardarUsuario({ ...datos, debe_cambiar_password: this.debeCambiarPassword() });
      })
    );
  }

  cambiarPassword(payload: CambiarPasswordPayload): Observable<ApiResponse<{ message: string }>> {
    return this.http
      .put<ApiResponse<{ message: string }>>(`${this.apiUrl}/${END_POINTS.auth.cambiarPassword}`, payload)
      // Si estaba obligada a cambiarla, ya no lo está: se levanta el bloqueo
      // acá mismo para no tener que volver a preguntarle al backend.
      .pipe(tap((res) => { if (res.success) this.marcarPasswordAlDia(); }));
  }

  /** Pide al correo el enlace para reponer la contraseña. */
  olvidePassword(email: string): Observable<ApiResponse<{ message: string }>> {
    return this.http.post<ApiResponse<{ message: string }>>(
      `${this.apiUrl}/${END_POINTS.auth.olvidePassword}`,
      { email }
    );
  }

  /** Pone la contraseña nueva con el token que llegó por correo. */
  restablecerPassword(payload: RestablecerPasswordPayload): Observable<ApiResponse<{ message: string }>> {
    return this.http.post<ApiResponse<{ message: string }>>(
      `${this.apiUrl}/${END_POINTS.auth.restablecerPassword}`,
      payload
    );
  }

  /**
   * La cuenta sigue con la contraseña que le dieron.
   *
   * Se sabe por dos vías: el login lo dice de entrada, y el backend responde
   * 423 a cualquier otra petición mientras dure. Se guarda junto al usuario
   * para que el guard pueda decidir sin esperar a que falle una llamada.
   */
  debeCambiarPassword(): boolean {
    return this.getUser()?.debe_cambiar_password === true;
  }

  marcarDebeCambiarPassword(): void {
    const user = this.getUser();
    if (user) this.guardarUsuario({ ...user, debe_cambiar_password: true });
  }

  private marcarPasswordAlDia(): void {
    const user = this.getUser();
    if (user) this.guardarUsuario({ ...user, debe_cambiar_password: false });
  }

  /** A dónde mandar al usuario tras entrar, teniendo en cuenta el bloqueo. */
  rutaTrasIngresar(): string {
    return this.debeCambiarPassword() ? '/cambiar-clave' : this.rutaInicioSegunRol();
  }

  /** Cierre de sesión a petición del usuario. */
  logout(): void {
    this.limpiarSesion();
    sessionStorage.removeItem(this.RETORNO_KEY);
    this.router.navigate(['/login']);
  }

  /**
   * La sesión caducó: el backend respondió 401.
   *
   * Sin esto, el token muerto seguía en localStorage, el guard dejaba pasar
   * y cada pantalla soltaba un "no se pudieron cargar los datos" sin decir
   * el motivo real. Ahora se limpia, se avisa y se recuerda dónde estaba
   * para devolverlo ahí en cuanto vuelva a entrar.
   */
  cerrarPorSesionExpirada(): void {
    if (this.cerrandoPorExpiracion) return;
    this.cerrandoPorExpiracion = true;

    const destino = this.router.url;
    this.limpiarSesion();

    if (destino && !destino.startsWith('/login') && !destino.startsWith('/registro')) {
      sessionStorage.setItem(this.RETORNO_KEY, destino);
    }

    // Las peticiones que quedaron en vuelo también fallarán con 401: se
    // callan para que no tapen el único mensaje que explica lo ocurrido.
    this.toast.silenciarErrores(4000);
    this.toast.error('Tu sesión expiró', 'Vuelve a iniciar sesión para continuar.', true);

    this.router.navigate(['/login']);
  }

  /** A dónde volver tras iniciar sesión, si la sesión se cayó a media faena. */
  consumirRetorno(): string | null {
    const destino = sessionStorage.getItem(this.RETORNO_KEY);
    sessionStorage.removeItem(this.RETORNO_KEY);
    return destino;
  }

  private limpiarSesion(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
  }

  getToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }

  getUser(): AuthUser | null {
    const user = localStorage.getItem(this.USER_KEY);
    return user ? JSON.parse(user) : null;
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  getEmpleadoId(): string | null {
    return this.getUser()?.empleado_id ?? null;
  }

  getRolNombre(): string {
    return (this.getUser()?.rol ?? '').toLowerCase();
  }

  esAdminORrhh(): boolean {
    return ['admin', 'rrhh'].includes(this.getRolNombre());
  }

  /** A dónde mandar a alguien justo después de loguearse, según su rol. */
  rutaInicioSegunRol(): string {
    return this.esAdminORrhh() ? '/inicio/dashboard' : '/inicio/mis-boletas';
  }

  /**
   * El backend manda "rol" como objeto ({id, nombre}) — se normaliza acá,
   * una sola vez, para que el resto de la app siempre reciba un string.
   */
  private guardarSesion(userCrudo: any, token: string, debeCambiarPassword = false): void {
    localStorage.setItem(this.TOKEN_KEY, token);
    this.guardarUsuario({ ...userCrudo, debe_cambiar_password: debeCambiarPassword });
    // Hay sesión nueva: el próximo 401 vuelve a avisar.
    this.cerrandoPorExpiracion = false;
  }

  /** Normaliza y guarda el usuario (sin tocar el token). */
  private guardarUsuario(userCrudo: any): void {
    const rolNombre =
      typeof userCrudo?.rol === 'object' && userCrudo?.rol !== null
        ? (userCrudo.rol.nombre ?? 'empleado')
        : (userCrudo?.rol ?? 'empleado');

    const user: AuthUser = { ...userCrudo, rol: rolNombre };
    localStorage.setItem(this.USER_KEY, JSON.stringify(user));
  }
}
