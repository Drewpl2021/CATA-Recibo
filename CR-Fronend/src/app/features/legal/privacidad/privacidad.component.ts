import { Component } from '@angular/core';

import { RouterLink } from '@angular/router';
import { IconComponent } from '../../../shared/components/icon/icon.component';

/**
 * «Política de privacidad»: pública (se lee sin iniciar sesión), enlazada
 * desde el inicio de sesión y desde el convenio de entrega digital.
 *
 * Sigue el orden de las políticas de las plataformas educativas grandes
 * (responsable, datos, finalidades, destinatarios, conservación, seguridad,
 * derechos, cookies, cambios) y cubre lo que el art. 18 de la Ley 29733 pide
 * informar: finalidad, destinatarios, banco de datos, identidad y domicilio
 * del titular y de los encargados, datos obligatorios, transferencias,
 * consecuencias de no darlos, conservación y cómo ejercer los derechos.
 *
 * Discreción: una política pública NO describe cómo está armada la
 * seguridad. Antes decía cada cuánto se cierra la sesión, cada cuánto hay
 * copia, cómo se frena a quien prueba claves y qué guarda el navegador con
 * sus nombres; eso es un mapa para quien quiere atacar. Ahora la seguridad
 * se describe en general, como lo hacen ellas.
 *
 * Lo que dice tiene que ser verdad del sistema: si se agrega un servicio de
 * terceros (analítica, fuentes externas, un CDN), cambia la sección de
 * cookies. La firma digital del colegio no se promete hasta que se cargue el
 * certificado.
 */
@Component({
  selector: 'app-privacidad',
  standalone: true,
  imports: [RouterLink, IconComponent],
  templateUrl: './privacidad.component.html',
})
export class PrivacidadComponent {
  readonly actualizada = '22 de octubre de 2026';

  readonly responsable = {
    nombre: 'Asociación Educativa Colegio Adventista Túpac Amaru',
    ruc: '20156630731',
    domicilio: 'Jr. Moquegua N.° 852, Juliaca, Puno, Perú',
    telefono: '051-325096',
  };

  readonly resumen = [
    { icono: 'receipt_long', titulo: 'Solo para tu relación laboral', texto: 'Usamos tus datos para tu planilla, tus boletas y tu expediente. No para publicidad ni para venderlos.' },
    { icono: 'eye_off', titulo: 'Acceso según la función', texto: 'Tú ves lo tuyo. Del personal, solo acceden quienes lo necesitan para su trabajo, y únicamente a lo que necesitan.' },
    { icono: 'shield', titulo: 'Tienes el control', texto: 'Puedes saber qué datos tenemos, corregirlos y ejercer los demás derechos que te da la ley.' },
  ];

  readonly secciones = [
    { id: 'responsable', titulo: 'Quién es responsable' },
    { id: 'datos', titulo: 'Qué datos tratamos' },
    { id: 'finalidades', titulo: 'Para qué y con qué base' },
    { id: 'destinatarios', titulo: 'Con quién los compartimos' },
    { id: 'conservacion', titulo: 'Cuánto tiempo los guardamos' },
    { id: 'seguridad', titulo: 'Cómo los protegemos' },
    { id: 'derechos', titulo: 'Tus derechos' },
    { id: 'ejercer', titulo: 'Cómo ejercerlos' },
    { id: 'cookies', titulo: 'Cookies' },
    { id: 'cambios', titulo: 'Cambios a esta política' },
  ];

  readonly datos = [
    { icono: 'badge', titulo: 'Identificación y contacto', items: ['Nombres, DNI y fecha de nacimiento', 'Dirección, teléfono y correo', 'Contacto de emergencia', 'Foto de perfil, si decides subirla'] },
    { icono: 'domain', titulo: 'Datos laborales', items: ['Cargo, área y sede', 'Contratos, fechas de ingreso y cese', 'Vacaciones', 'Formación académica y hoja de vida'] },
    { icono: 'wallet', titulo: 'Remuneración y pago', items: ['Sueldo, bonificaciones y descuentos', 'Cuenta bancaria y CCI', 'Sistema de pensiones (AFP u ONP) y CUSPP', 'Si tienes hijos, para la asignación familiar'] },
    { icono: 'folder', titulo: 'Documentos y firma', items: ['Boletas, liquidaciones de CTS y gratificación', 'Contratos y documentos de tu expediente', 'Tu firma y la constancia de que recibiste cada documento'] },
  ];

  readonly finalidades = [
    { titulo: 'Gestionar tu relación laboral', texto: 'Calcular tu planilla, pagar tu remuneración y beneficios, y llevar tu expediente.' },
    { titulo: 'Cumplir obligaciones legales', texto: 'Las laborales, tributarias y previsionales que el colegio tiene como empleador.' },
    { titulo: 'Entregarte tus documentos', texto: 'Ponerlos a tu disposición en el sistema, recibir tu conformidad y avisarte por correo cuando hay uno nuevo.' },
    { titulo: 'Darte acceso al sistema', texto: 'Crear tu cuenta y ayudarte a recuperarla si olvidas tu contraseña.' },
  ];

  readonly destinatarios = [
    { icono: 'domain', titulo: 'Entidades públicas', texto: 'SUNAT, EsSalud y la ONP, cuando la ley lo exige y solo con lo que exige.' },
    { icono: 'bank', titulo: 'Entidades financieras y previsionales', texto: 'Tu banco, para depositar tu sueldo y tu CTS, y tu AFP, para tus aportes.' },
    { icono: 'settings', titulo: 'Proveedores de servicios', texto: 'Empresas que dan al colegio servicios técnicos, como el alojamiento del sistema o el envío de correos. Actúan como encargados: solo por encargo del colegio, bajo contrato y para ese fin.' },
    { icono: 'shield', titulo: 'Autoridades', texto: 'Jueces u otras autoridades, cuando lo ordenen conforme a ley.' },
  ];

  readonly cuidados = [
    { icono: 'lock', titulo: 'Acceso restringido', texto: 'Cada cuenta entra con su contraseña y ve solo lo que corresponde a su función. Las contraseñas se guardan de forma que nadie pueda leerlas.' },
    { icono: 'shield', titulo: 'Información protegida', texto: 'La comunicación con el sistema viaja cifrada y los documentos solo se abren para quien tiene derecho a verlos.' },
    { icono: 'search', titulo: 'Registro de cambios', texto: 'Los cambios en datos importantes quedan registrados, con quién los hizo y cuándo.' },
    { icono: 'save', titulo: 'Continuidad', texto: 'Contamos con copias de seguridad para que tu información no se pierda.' },
  ];

  readonly derechos = [
    { icono: 'info', titulo: 'Información', texto: 'Saber cómo y para qué tratamos tus datos.' },
    { icono: 'eye', titulo: 'Acceso', texto: 'Conocer qué datos tuyos tenemos, cómo se obtuvieron y a quién se transfirieron.' },
    { icono: 'edit', titulo: 'Rectificación', texto: 'Corregir un dato inexacto o ponerlo al día.' },
    { icono: 'trash', titulo: 'Cancelación', texto: 'Pedir que se eliminen cuando ya no sean necesarios y la ley no obligue a conservarlos.' },
    { icono: 'pause_circle', titulo: 'Oposición', texto: 'Oponerte a un tratamiento que no sea necesario para tu relación laboral.' },
    { icono: 'check_circle', titulo: 'Revocar tu consentimiento', texto: 'Retirarlo cuando lo hayas dado, por ejemplo para un descuento voluntario.' },
  ];
}
