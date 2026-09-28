import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from '../services/sistema/auth.service';

describe('authInterceptor', () => {
  let http: HttpClient;
  let control: HttpTestingController;
  let auth: jasmine.SpyObj<AuthService>;
  let router: { navigate: jasmine.Spy; url: string };

  beforeEach(() => {
    localStorage.clear();
    auth = jasmine.createSpyObj<AuthService>('AuthService', [
      'isLoggedIn', 'cerrarPorSesionExpirada', 'marcarDebeCambiarPassword', 'marcarDebeFirmarTerminos',
    ]);
    auth.isLoggedIn.and.returnValue(true);
    router = { navigate: jasmine.createSpy('navigate'), url: '/inicio/dashboard' };

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
        { provide: Router, useValue: router },
      ],
    });
    http = TestBed.inject(HttpClient);
    control = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    control.verify();
    localStorage.clear();
  });

  it('pide siempre JSON y agrega el token si lo hay', () => {
    localStorage.setItem('auth_token', 'abc');

    http.get('/api/employees').subscribe();

    const req = control.expectOne('/api/employees');
    expect(req.request.headers.get('Authorization')).toBe('Bearer abc');
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush({});
  });

  it('sin token no manda Authorization', () => {
    http.get('/api/x').subscribe();

    const req = control.expectOne('/api/x');
    expect(req.request.headers.has('Authorization')).toBeFalse();
    req.flush({});
  });

  it('convierte PUT, PATCH y DELETE en POST con ?_method', () => {
    http.put('/api/employees/1', {}).subscribe();
    http.delete('/api/employees/2').subscribe();
    http.patch('/api/x?a=1', {}).subscribe();

    const put = control.expectOne('/api/employees/1?_method=PUT');
    const del = control.expectOne('/api/employees/2?_method=DELETE');
    const patch = control.expectOne('/api/x?a=1&_method=PATCH');
    for (const r of [put, del, patch]) {
      expect(r.request.method).toBe('POST');
      r.flush({});
    }
  });

  it('un 401 con sesión cierra la sesión por caducidad', () => {
    http.get('/api/employees').subscribe({ error: () => undefined });

    control.expectOne('/api/employees').flush({}, { status: 401, statusText: 'x' });

    expect(auth.cerrarPorSesionExpirada).toHaveBeenCalled();
  });

  it('un 401 en el login NO es una sesión caducada', () => {
    http.post('/api/login', {}).subscribe({ error: () => undefined });

    control.expectOne('/api/login').flush({}, { status: 401, statusText: 'x' });

    expect(auth.cerrarPorSesionExpirada).not.toHaveBeenCalled();
  });

  it('un 423 manda a cambiar la contraseña', () => {
    http.get('/api/employees').subscribe({ error: () => undefined });

    control.expectOne('/api/employees').flush({}, { status: 423, statusText: 'x' });

    expect(auth.marcarDebeCambiarPassword).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/cambiar-clave']);
  });

  it('un 423 estando ya en esa pantalla no vuelve a navegar', () => {
    router.url = '/cambiar-clave';
    http.get('/api/employees').subscribe({ error: () => undefined });

    control.expectOne('/api/employees').flush({}, { status: 423, statusText: 'x' });

    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('un 428 manda a firmar los términos', () => {
    http.get('/api/employees').subscribe({ error: () => undefined });

    control.expectOne('/api/employees').flush({}, { status: 428, statusText: 'x' });

    expect(auth.marcarDebeFirmarTerminos).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/terminos']);
  });

  it('el error se sigue propagando al componente', () => {
    let estado = 0;
    http.get('/api/x').subscribe({ error: (e) => (estado = e.status) });

    control.expectOne('/api/x').flush({}, { status: 500, statusText: 'x' });

    expect(estado).toBe(500);
  });
});
