import { TestBed } from '@angular/core/testing';
import { Location } from '@angular/common';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { AuthService } from '../../../core/services';
import { RestablecerPasswordComponent } from './restablecer-password.component';

/**
 * El enlace del correo: el token llega después de «#» (o después de «?» en
 * los correos viejos) y se borra de la barra apenas se lee.
 */
describe('RestablecerPasswordComponent: el token del enlace', () => {
  function crear(fragmento: string | null, consulta: Record<string, string> = {}) {
    const location = jasmine.createSpyObj<Location>('Location', ['path', 'replaceState']);
    location.path.and.returnValue('/crear-contrasena' + (fragmento ? '#' + fragmento : ''));
    TestBed.configureTestingModule({
      imports: [RestablecerPasswordComponent],
      providers: [
        { provide: ActivatedRoute, useValue: { snapshot: { fragment: fragmento, queryParamMap: convertToParamMap(consulta), data: { invitacion: true } } } },
        { provide: Location, useValue: location },
        { provide: AuthService, useValue: {} },
        { provide: Router, useValue: jasmine.createSpyObj('Router', ['navigate']) },
      ],
    });
    const c = TestBed.createComponent(RestablecerPasswordComponent).componentInstance;
    c.ngOnInit();
    return { c, location };
  }

  it('lee el token y el correo después de «#» y los borra de la barra', () => {
    const { c, location } = crear('token=abc123&email=usuario%40cata.edu.pe');
    expect(c.token).toBe('abc123');
    expect(c.email).toBe('usuario@cata.edu.pe');
    expect(c.enlaceValido).toBeTrue();
    expect(location.replaceState).toHaveBeenCalledWith('/crear-contrasena');
  });

  it('sigue aceptando los correos viejos, con el token después de «?»', () => {
    const { c, location } = crear(null, { token: 'viejo', email: 'usuario@cata.edu.pe' });
    expect(c.token).toBe('viejo');
    expect(c.enlaceValido).toBeTrue();
    expect(location.replaceState).toHaveBeenCalled();
  });

  it('sin token el enlace no vale y no se toca la barra', () => {
    const { c, location } = crear(null);
    expect(c.enlaceValido).toBeFalse();
    expect(location.replaceState).not.toHaveBeenCalled();
  });
});
