import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/sistema/auth.service';

/**
 * Antes de esto, cualquiera (sin sesión) podía navegar directo a
 * /inicio/empleados, /inicio/planillas, etc. — recién fallaba cuando el
 * componente intentaba pedirle datos a la API y esta respondía 401.
 * Con este guard, ni siquiera se llega a cargar la pantalla.
 */
export const authGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isLoggedIn()) {
    router.navigate(['/login']);
    return false;
  }

  // Cuenta trabada por contraseña inicial: no entra a ninguna pantalla hasta
  // cambiarla. El backend responde 423 igual, esto solo evita cargar una
  // pantalla que se quedaría vacía.
  if (authService.debeCambiarPassword()) {
    router.navigate(['/cambiar-clave']);
    return false;
  }

  return true;
};

/**
 * Para /cambiar-clave: hace falta sesión, pero NO hace falta tener la
 * contraseña al día — es justo la pantalla donde se arregla eso.
 */
export const sesionGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.isLoggedIn()) return true;

  router.navigate(['/login']);
  return false;
};

/** Para /login y /registro: si ya hay sesión iniciada, no tiene sentido volver a mostrarlas. */
export const guestGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isLoggedIn()) return true;

  router.navigate([authService.rutaTrasIngresar()]);
  return false;
};

/**
 * Factory: guarda una ruta solo para ciertos roles (ej. Áreas/Cargos/Sedes
 * son solo RRHH/Admin). Si el rol no matchea, redirige a su propio inicio
 * en vez de dejarlo en una pantalla rota que le va a rechazar la API.
 */
export const roleGuard = (rolesPermitidos: string[]): CanActivateFn => {
  return () => {
    const authService = inject(AuthService);
    const router = inject(Router);

    if (!authService.isLoggedIn()) {
      router.navigate(['/login']);
      return false;
    }

    if (rolesPermitidos.includes(authService.getRolNombre())) return true;

    router.navigate([authService.rutaTrasIngresar()]);
    return false;
  };
};
