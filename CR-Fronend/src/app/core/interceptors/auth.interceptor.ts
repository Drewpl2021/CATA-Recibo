import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/sistema/auth.service';

/** Rutas donde un 401 NO significa que la sesión caducó. */
const RUTAS_SIN_SESION = ['/login', '/register'];

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const token = localStorage.getItem('auth_token');
  const headers: Record<string, string> = {
    Accept: 'application/json'
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const method = req.method.toUpperCase();
  // Evitar error en PHP 8.2 local (XAMPP) convirtiendo PUT, PATCH, DELETE a POST con el parámetro ?_method
  let peticion = req.clone({ setHeaders: headers });
  if (['PUT', 'PATCH', 'DELETE'].includes(method)) {
    const separator = req.url.includes('?') ? '&' : '?';
    peticion = req.clone({
      method: 'POST',
      url: `${req.url}${separator}_method=${method}`,
      setHeaders: headers,
    });
  }

  return next(peticion).pipe(
    catchError((error: HttpErrorResponse) => {
      /*
       * Un 401 teniendo sesión iniciada significa que el token ya no vale:
       * caducó (Sanctum los emite por 24 h) o se cerró desde otro sitio.
       *
       * Sin esto, el token muerto se quedaba en localStorage, el guard dejaba
       * entrar igual, y cada pantalla soltaba un "no se pudieron cargar los
       * datos" que no decía nada del problema real. Ahora se limpia la sesión,
       * se avisa UNA vez y se manda al login recordando dónde estaba.
       */
      const esRutaDeAcceso = RUTAS_SIN_SESION.some((ruta) => req.url.includes(ruta));

      if (error.status === 401 && !esRutaDeAcceso && authService.isLoggedIn()) {
        authService.cerrarPorSesionExpirada();
      }

      /*
       * 423: la sesión es buena, pero la cuenta está trabada porque todavía
       * usa la contraseña que le dieron (el DNI). El backend lo responde a
       * todo salvo /me, /logout y /cambiar-password.
       *
       * Pasa aunque el login no lo hubiera avisado: por ejemplo si RR.HH. le
       * repone la contraseña mientras la persona está trabajando.
       */
      if (error.status === 423 && authService.isLoggedIn()) {
        authService.marcarDebeCambiarPassword();
        if (!router.url.startsWith('/cambiar-clave')) {
          router.navigate(['/cambiar-clave']);
        }
      }

      return throwError(() => error);
    })
  );
};
