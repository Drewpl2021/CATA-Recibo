import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * RoleGuard - Verifica que el usuario tenga uno de los roles requeridos para la ruta.
 * Si no tiene el rol necesario:
 *  - Si es empleado, lo redirige a /inicio/mis-boletas
 *  - Si es admin/rrhh, lo redirige a /inicio/dashboard
 */
export const roleGuard: CanActivateFn = (route, state): boolean | UrlTree => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isLoggedIn()) {
    return router.createUrlTree(['/login']);
  }

  const expectedRoles = route.data?.['roles'] as Array<string> | undefined;
  if (!expectedRoles || expectedRoles.length === 0) {
    return true;
  }

  const user = authService.getUser();
  let userRol = '';
  if (typeof user?.rol === 'string') {
    userRol = user.rol;
  } else if (user?.rol && typeof user.rol === 'object') {
    userRol = (user.rol as any).nombre || '';
  }
  userRol = userRol.toLowerCase();

  const hasRole = expectedRoles.some(r => r.toLowerCase() === userRol);
  if (hasRole) {
    return true;
  }

  // Redirección segura según el rol del usuario para evitar pantallas no autorizadas
  if (userRol === 'empleado') {
    return router.createUrlTree(['/inicio/mis-boletas']);
  }

  return router.createUrlTree(['/inicio/dashboard']);
};
