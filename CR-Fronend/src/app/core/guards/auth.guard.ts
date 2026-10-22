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

  // Sin los términos firmados, lo mismo: el backend responde 428 a todo, así
  // que se le lleva directo a firmarlos en vez de cargar una pantalla vacía.
  if (authService.debeFirmarTerminos()) {
    router.navigate(['/terminos']);
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

/** Para /login: si ya hay sesión iniciada, no tiene sentido volver a mostrarlo. */
export const guestGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isLoggedIn()) return true;

  router.navigate([authService.rutaTrasIngresar()]);
  return false;
};

/**
 * Factory: la pantalla se abre solo si el rol tiene ese módulo (lo que se
 * marca en Roles), igual que lo exige el servidor en cada ruta. Antes
 * preguntaba por el nombre del rol, y un rol nuevo como Contabilidad no
 * entraba aunque tuviera el módulo. Si no puede, va a su propio inicio en
 * vez de quedarse en una pantalla que la API le va a rechazar.
 */
export const permisoGuard = (...modulos: string[]): CanActivateFn => {
  return () => {
    const authService = inject(AuthService);
    const router = inject(Router);

    if (!authService.isLoggedIn()) {
      router.navigate(['/login']);
      return false;
    }

    if (authService.puede(...modulos)) return true;

    router.navigate([authService.rutaTrasIngresar()]);
    return false;
  };
};
