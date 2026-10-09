import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

/**
 * «Privacidad y cookies»: pública (se lee sin iniciar sesión), enlazada desde
 * el inicio de sesión y desde el convenio de entrega digital.
 *
 * Lo que dice tiene que ser verdad del sistema, así que va escrito contra el
 * código: si se agrega algo al almacenamiento del navegador (localStorage,
 * sessionStorage), o un servicio de terceros, se actualiza aquí.
 *   - auth_token, auth_user, auth_retorno: AuthService
 *   - app-theme: ThemeService
 *   - cata-lista:*: EstadoListadoService (se borra al cerrar sesión)
 *   - recarga-por-version-nueva: app.config.ts
 * Cookies: el servidor no pone ninguna (la sesión va por token, no por cookie).
 */
@Component({
  selector: 'app-privacidad',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './privacidad.component.html',
})
export class PrivacidadComponent {
  readonly actualizada = '9 de octubre de 2026';

  readonly almacenamiento = [
    { nombre: 'auth_token', para: 'Mantener tu sesión iniciada.', dura: 'Hasta que cierres sesión. La sesión se cierra sola tras 2 horas sin usarla.' },
    { nombre: 'auth_user', para: 'Tu nombre y tu rol, para armar el menú.', dura: 'Hasta que cierres sesión.' },
    { nombre: 'auth_retorno', para: 'Volver a la pantalla donde estabas después de iniciar sesión.', dura: 'Solo esta pestaña.' },
    { nombre: 'app-theme', para: 'Recordar si prefieres el modo claro o el oscuro.', dura: 'Hasta que lo cambies.' },
    { nombre: 'cata-lista:…', para: 'Recordar la búsqueda, los filtros y la página de cada lista al volver a ella.', dura: 'Solo esta pestaña; se borra al cerrar sesión.' },
    { nombre: 'recarga-por-version-nueva', para: 'Evitar recargas repetidas cuando se actualiza el sistema.', dura: 'Solo esta pestaña.' },
  ];
}
