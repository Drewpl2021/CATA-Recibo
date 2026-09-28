import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { AuthService } from './auth.service';
import { ToastService } from './toast.service';

describe('AuthService', () => {
  let servicio: AuthService;
  let http: HttpTestingController;
  let router: jasmine.SpyObj<Router>;

  const guardar = (usuario: Record<string, unknown> | null, token: string | null = 'tok') => {
    token ? localStorage.setItem('auth_token', token) : localStorage.removeItem('auth_token');
    usuario ? localStorage.setItem('auth_user', JSON.stringify(usuario)) : localStorage.removeItem('auth_user');
  };

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    router = jasmine.createSpyObj<Router>('Router', ['navigate'], { url: '/inicio/dashboard' });

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: Router, useValue: router },
        {
          provide: ToastService,
          useValue: jasmine.createSpyObj('ToastService', ['error', 'success', 'silenciarErrores']),
        },
      ],
    });
    servicio = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
    sessionStorage.clear();
  });

  it('sin token no hay sesión', () => {
    expect(servicio.isLoggedIn()).toBeFalse();
    expect(servicio.getUser()).toBeNull();
  });

  it('el login correcto guarda token y usuario, y normaliza el rol', () => {
    servicio.login('a@b.co', 'x').subscribe();

    const req = http.expectOne((r) => r.url.includes('login'));
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ email: 'a@b.co', password: 'x' });
    req.flush({
      success: true,
      data: { user: { id: 1, name: 'Ana', rol: { nombre: 'RRHH' } }, token: 'abc', debe_cambiar_password: false },
    });

    expect(servicio.isLoggedIn()).toBeTrue();
    expect(servicio.getToken()).toBe('abc');
    expect(servicio.getRolNombre()).toBe('rrhh');
  });

  it('un login fallido no deja nada guardado', () => {
    servicio.login('a@b.co', 'mala').subscribe({ error: () => undefined });

    http.expectOne((r) => r.url.includes('login')).flush({ message: 'x' }, { status: 422, statusText: 'x' });

    expect(servicio.isLoggedIn()).toBeFalse();
  });

  it('admin y rrhh entran al panel; el trabajador a sus boletas', () => {
    guardar({ rol: 'admin' });
    expect(servicio.esAdminORrhh()).toBeTrue();
    expect(servicio.rutaInicioSegunRol()).toBe('/inicio/dashboard');

    guardar({ rol: 'RRHH' });
    expect(servicio.esAdminORrhh()).toBeTrue();

    guardar({ rol: 'empleado' });
    expect(servicio.esAdminORrhh()).toBeFalse();
    expect(servicio.rutaInicioSegunRol()).toBe('/inicio/mis-boletas');
  });

  it('la contraseña por cambiar tiene prioridad sobre los términos al ingresar', () => {
    guardar({ rol: 'admin', debe_cambiar_password: true, terminos_estado: 'pendiente' });
    expect(servicio.rutaTrasIngresar()).toBe('/cambiar-clave');

    guardar({ rol: 'admin', debe_cambiar_password: false, terminos_estado: 'pendiente' });
    expect(servicio.rutaTrasIngresar()).toBe('/terminos');

    guardar({ rol: 'admin', debe_cambiar_password: false, terminos_estado: 'firmado' });
    expect(servicio.rutaTrasIngresar()).toBe('/inicio/dashboard');
  });

  it('los términos desactualizados también obligan a firmar', () => {
    guardar({ rol: 'admin', terminos_estado: 'desactualizado' });
    expect(servicio.debeFirmarTerminos()).toBeTrue();

    guardar({ rol: 'admin', terminos_estado: 'firmado' });
    expect(servicio.debeFirmarTerminos()).toBeFalse();
  });

  it('marcarDebeCambiarPassword lo deja anotado en la sesión', () => {
    guardar({ rol: 'admin', debe_cambiar_password: false });

    servicio.marcarDebeCambiarPassword();

    expect(servicio.debeCambiarPassword()).toBeTrue();
  });

  it('el logout borra la sesión y manda al login', () => {
    guardar({ rol: 'admin' });

    servicio.logout();

    expect(servicio.isLoggedIn()).toBeFalse();
    expect(localStorage.getItem('auth_user')).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/login']);
  });

  it('una sesión caducada avisa UNA sola vez aunque lleguen varios 401', () => {
    guardar({ rol: 'admin' });
    const toast = TestBed.inject(ToastService) as jasmine.SpyObj<ToastService>;

    servicio.cerrarPorSesionExpirada();
    servicio.cerrarPorSesionExpirada();
    servicio.cerrarPorSesionExpirada();

    expect(toast.error).toHaveBeenCalledTimes(1);
    expect(servicio.isLoggedIn()).toBeFalse();
  });
});
