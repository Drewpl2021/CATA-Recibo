import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot } from '@angular/router';
import { authGuard, guestGuard, permisoGuard, sesionGuard } from './auth.guard';
import { AuthService } from '../services/sistema/auth.service';

describe('guards de autenticación', () => {
  let auth: jasmine.SpyObj<AuthService>;
  let router: jasmine.SpyObj<Router>;

  const ejecutar = (guard: any) =>
    TestBed.runInInjectionContext(() => guard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot));

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', [
      'isLoggedIn', 'debeCambiarPassword', 'debeFirmarTerminos', 'getRolNombre', 'puede', 'rutaTrasIngresar',
    ]);
    auth.isLoggedIn.and.returnValue(true);
    auth.debeCambiarPassword.and.returnValue(false);
    auth.debeFirmarTerminos.and.returnValue(false);
    auth.rutaTrasIngresar.and.returnValue('/inicio/dashboard');
    router = jasmine.createSpyObj<Router>('Router', ['navigate']);

    TestBed.configureTestingModule({
      providers: [
        { provide: AuthService, useValue: auth },
        { provide: Router, useValue: router },
      ],
    });
  });

  describe('authGuard', () => {
    it('deja pasar a quien tiene todo al día', () => {
      expect(ejecutar(authGuard)).toBeTrue();
      expect(router.navigate).not.toHaveBeenCalled();
    });

    it('sin sesión va al login', () => {
      auth.isLoggedIn.and.returnValue(false);

      expect(ejecutar(authGuard)).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/login']);
    });

    it('con la contraseña inicial va a cambiarla', () => {
      auth.debeCambiarPassword.and.returnValue(true);

      expect(ejecutar(authGuard)).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/cambiar-clave']);
    });

    it('sin términos firmados va a firmarlos', () => {
      auth.debeFirmarTerminos.and.returnValue(true);

      expect(ejecutar(authGuard)).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/terminos']);
    });
  });

  describe('sesionGuard', () => {
    it('pide sesión pero no contraseña al día', () => {
      auth.debeCambiarPassword.and.returnValue(true);
      expect(ejecutar(sesionGuard)).toBeTrue();
    });

    it('sin sesión va al login', () => {
      auth.isLoggedIn.and.returnValue(false);

      expect(ejecutar(sesionGuard)).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/login']);
    });
  });

  describe('guestGuard', () => {
    it('deja ver el login a quien no ha entrado', () => {
      auth.isLoggedIn.and.returnValue(false);
      expect(ejecutar(guestGuard)).toBeTrue();
    });

    it('a quien ya entró lo manda a su inicio', () => {
      expect(ejecutar(guestGuard)).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/inicio/dashboard']);
    });
  });

  describe('permisoGuard', () => {
    it('deja pasar a quien tiene el módulo, se llame como se llame su rol', () => {
      auth.puede.and.returnValue(true);
      expect(ejecutar(permisoGuard('/planillas'))).toBeTrue();
      expect(auth.puede).toHaveBeenCalledWith('/planillas');
    });

    it('a quien no lo tiene lo devuelve a su inicio en vez de una pantalla rota', () => {
      auth.puede.and.returnValue(false);

      expect(ejecutar(permisoGuard('/planillas'))).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/inicio/dashboard']);
    });

    it('sin sesión va al login', () => {
      auth.isLoggedIn.and.returnValue(false);

      expect(ejecutar(permisoGuard('/auditoria'))).toBeFalse();
      expect(router.navigate).toHaveBeenCalledWith(['/login']);
    });
  });
});
