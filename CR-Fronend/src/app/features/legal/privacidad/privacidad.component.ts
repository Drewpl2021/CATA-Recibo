import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { IconComponent } from '../../../shared/components/icon/icon.component';

/**
 * «Privacidad y cookies»: pública (se lee sin iniciar sesión), enlazada desde
 * el inicio de sesión y desde el convenio de entrega digital.
 *
 * Escrita para el personal del colegio, no para informáticos: qué datos hay,
 * para qué, quién los ve, cómo se cuidan y qué derechos tienes. Antes tenía
 * una tabla con los nombres internos del almacenamiento del navegador y
 * hablaba de «hash»; eso se leía raro.
 *
 * Lo que dice tiene que ser verdad del sistema. Lo que guarda el navegador
 * (la lista de «¿Y qué guarda tu navegador?») sale de:
 *   - auth_token, auth_user, auth_retorno: AuthService
 *   - app-theme: ThemeService
 *   - cata-lista:*: EstadoListadoService (se borra al cerrar sesión)
 *   - recarga-por-version-nueva: app.config.ts
 * Si se agrega algo ahí, o un servicio de terceros, se actualiza aquí.
 * Cookies: el servidor no pone ninguna (la sesión va por token, no por cookie).
 */
@Component({
  selector: 'app-privacidad',
  standalone: true,
  imports: [CommonModule, RouterLink, IconComponent],
  templateUrl: './privacidad.component.html',
})
export class PrivacidadComponent {
  readonly actualizada = '9 de octubre de 2026';

  readonly resumen = [
    { icono: 'receipt_long', titulo: 'Solo para tu trabajo', texto: 'Tus datos sirven para tu planilla, tus boletas y tus documentos laborales. Para nada más.' },
    { icono: 'eye_off', titulo: 'Tú ves lo tuyo', texto: 'Nadie del personal ve tus datos. Solo Recursos Humanos y la Administración, lo que necesitan para su trabajo.' },
    { icono: 'shield', titulo: 'Sin cookies ni publicidad', texto: 'No te rastreamos ni compartimos nada con empresas de publicidad o redes sociales.' },
  ];

  readonly secciones = [
    { id: 'datos', titulo: 'Qué datos tenemos' },
    { id: 'uso', titulo: 'Para qué los usamos' },
    { id: 'quien', titulo: 'Quién los ve' },
    { id: 'cuidado', titulo: 'Cómo los cuidamos' },
    { id: 'derechos', titulo: 'Tus derechos' },
    { id: 'cookies', titulo: 'Cookies' },
  ];

  readonly datos = [
    { icono: 'badge', titulo: 'Quién eres', items: ['DNI, nombres y fecha de nacimiento', 'Teléfono, dirección y correo', 'Tu foto, si la subes'] },
    { icono: 'domain', titulo: 'Tu trabajo', items: ['Cargo, área y sede', 'Tipo de contrato y sus fechas', 'Fecha de ingreso'] },
    { icono: 'wallet', titulo: 'Tu pago', items: ['Sueldo, bonos y descuentos', 'Cuenta bancaria', 'Sistema de pensiones (AFP u ONP)'] },
    { icono: 'folder', titulo: 'Tus documentos', items: ['Boletas de pago', 'Contratos', 'Hoja de vida y otros de tu expediente'] },
  ];

  readonly usosSi = [
    'Calcular tu planilla y emitir tus boletas de pago.',
    'Guardar tus contratos y documentos laborales.',
    'Cumplir con lo que la ley pide al colegio como empleador (SUNAT, EsSalud, tu AFP u ONP).',
    'Avisarte por correo cuando tienes una boleta nueva o tu acceso al sistema.',
  ];

  readonly usosNo = [
    'Publicidad de ningún tipo.',
    'Venderlos o prestarlos a otras empresas.',
    'Cualquier cosa que no tenga que ver con tu trabajo en el colegio.',
  ];

  readonly quienes = [
    { icono: 'person', titulo: 'Tú', texto: 'Ves y descargas todo lo tuyo: tus boletas, tus documentos y tus datos.' },
    { icono: 'people', titulo: 'Recursos Humanos', texto: 'Lo necesario para armar tu planilla, emitir tus boletas y llevar tu expediente.' },
    { icono: 'admin_panel_settings', titulo: 'Administración', texto: 'Lo necesario para configurar el sistema y controlar que todo esté en orden.' },
    { icono: 'domain', titulo: 'Entidades del Estado', texto: 'Solo lo que la ley exige y cuando lo exige: SUNAT, EsSalud, tu AFP u ONP.' },
  ];

  readonly cuidados = [
    { icono: 'lock', titulo: 'Tu contraseña es solo tuya', texto: 'Nadie puede verla, ni siquiera Recursos Humanos. Si la olvidas, te llega a tu correo un enlace para crear otra.' },
    { icono: 'clock', titulo: 'La sesión se cierra sola', texto: 'Si dejas el sistema abierto y no lo usas por 2 horas, se cierra. Y si alguien intenta entrar muchas veces con una clave equivocada, se le frena.' },
    { icono: 'signature', titulo: 'Boletas que no se pueden falsificar', texto: 'Cada boleta lleva la firma digital del colegio y un código QR para comprobar que es auténtica. Si alguien la cambia, la firma deja de valer.' },
    { icono: 'save', titulo: 'Copia de seguridad cada día', texto: 'Todos los días se guarda una copia de la información y de los documentos, para no perder nada.' },
    { icono: 'search', titulo: 'Cada cambio queda anotado', texto: 'Si alguien modifica datos importantes, el sistema registra quién fue y cuándo. Ese registro no se puede borrar.' },
    { icono: 'folder_shared', titulo: 'Documentos bajo llave', texto: 'Tus boletas y documentos no están a la vista en internet: solo se abren para quien tiene derecho a verlos.' },
  ];

  readonly derechos = [
    { icono: 'eye', titulo: 'Saber', texto: 'Qué datos tuyos tiene el colegio y para qué los usa.' },
    { icono: 'edit', titulo: 'Corregir', texto: 'Que se arregle un dato equivocado o se ponga al día uno que cambió.' },
    { icono: 'trash', titulo: 'Pedir que se borren', texto: 'Cuando ya no hagan falta y la ley no obligue a guardarlos.' },
    { icono: 'pause_circle', titulo: 'Oponerte', texto: 'A un uso de tus datos que no sea necesario para tu trabajo.' },
  ];

  /** Lo que guarda el navegador, en palabras (ver la lista técnica arriba). */
  readonly navegador = [
    'Que iniciaste sesión, para no pedirte la clave en cada pantalla. Se borra al cerrar sesión o tras 2 horas sin usar el sistema.',
    'Tu nombre y tu rol, para armar el menú.',
    'Si prefieres el modo claro o el oscuro.',
    'Lo que buscaste y filtraste en cada lista, para que al volver la encuentres igual. Se borra al cerrar sesión.',
    'A qué pantalla volver después de iniciar sesión.',
    'Una marca para no recargar la página dos veces cuando se actualiza el sistema.',
  ];
}
