# Seguridad y privacidad de CATA-Recibo

Qué hace el sistema para proteger la información del personal, cómo se
comprobó y qué queda pendiente. Revisado el 9 de octubre de 2026.

---

## 1. Inyección SQL

**Estado: protegido y probado.**

- Ninguna consulta se arma pegando lo que escribe el usuario. Todo pasa por
  Eloquent (el ORM de Laravel) con **parámetros enlazados**: el valor viaja
  aparte de la sentencia y la base nunca lo interpreta como SQL.
- Las consultas escritas a mano (35, en el Panel de Control y algunos
  listados) usan solo valores fijos del código o parámetros `?`.
- Los filtros por columna (`estado`, `area_id`, `mes`…) toman el nombre de la
  columna de **listas fijas** del código; el usuario solo aporta el valor, que
  va enlazado.
- Los filtros se validan antes de usarse (por ejemplo, `estado` solo puede ser
  `activo` o `inactivo`, y `mes` un entero de 1 a 12).

**Prueba automática:** `tests/Feature/InyeccionSqlTest.php` envía 10 ataques
clásicos (`' OR '1'='1`, `UNION SELECT`, `DROP TABLE`, `SLEEP`…) al buscador
de 13 listas, a 10 filtros y al inicio de sesión: 416 verificaciones. Ninguno
devuelve datos de más, ninguno produce un error 500 y las tablas siguen
intactas.

## 2. Otros ataques web

| Riesgo | Cómo se cubre |
|---|---|
| **XSS** (inyectar código en la página) | Angular escapa todo lo que muestra. Lo único que se inserta como HTML son los íconos del catálogo interno. La política de contenido (CSP) no permite scripts de otros orígenes ni en línea. |
| **CSRF** | La sesión va por un token en la cabecera `Authorization`, no por cookie: otro sitio no puede hacer pedidos en nombre del usuario. |
| **Clickjacking** | `X-Frame-Options: SAMEORIGIN` y `frame-ancestors 'self'`: el sistema no se puede incrustar en otra página. |
| **Tipos de archivo engañosos** | `X-Content-Type-Options: nosniff`. Las subidas se validan por tipo y tamaño. |
| **Fugas por el navegador** | `Referrer-Policy: strict-origin-when-cross-origin` y `Permissions-Policy` sin cámara, micrófono ni ubicación. |
| **Errores que revelan el sistema** | En producción `APP_DEBUG=false`: el usuario ve un mensaje y un código; el detalle queda en el registro del servidor. |

Las cabeceras están en `docker/web/cabeceras-seguridad.conf`.

## 3. Acceso y sesiones

- Contraseñas guardadas con **hash** (bcrypt): no se pueden leer, ni siquiera
  desde la base.
- El primer acceso llega **por correo, con un enlace personal** (punto 7):
  nunca es el DNI, y la persona crea su propia contraseña.
- **Límite de intentos** en el inicio de sesión y en la recuperación de
  contraseña. En «Firmar aquí», 5 claves equivocadas del certificado bloquean
  15 minutos.
- La sesión **se cierra tras 2 horas sin uso** (`SESION_INACTIVIDAD_MINUTOS`).
- **Roles**: el trabajador ve solo lo suyo; RR.HH. y Administración, lo que
  necesitan. La **auditoría** registra quién cambió qué y no se puede editar.
- Al cerrar sesión se borran del navegador el token, los datos del usuario y
  las búsquedas guardadas de las listas.

## 4. Documentos y firma

- Boletas y documentos se guardan **fuera de la carpeta pública** y solo se
  entregan a quien tiene derecho a verlos.
- Las descargas en .zip usan **enlaces firmados** que vencen en 10 minutos.
- Las boletas llevan **firma digital PAdES** con certificado acreditado (Ley
  27269) y un **QR de verificación**. Cualquier alteración invalida la firma.
- El certificado de quien firma se guarda **cifrado (AES-256-GCM)**, y su clave
  **no se guarda nunca**.
- **Respaldo automático diario** de la base de datos y de los documentos.

## 5. Privacidad y cookies

- **El sistema no usa cookies**: ni propias ni de terceros. Por eso no hace
  falta un aviso de aceptación de cookies.
- En el navegador solo se guarda lo imprescindible para funcionar. La lista
  completa está en la página pública **/privacidad** («Privacidad y cookies»),
  enlazada desde el inicio de sesión y desde el convenio.
- **Sin terceros**: la tipografía Inter se sirve desde el propio servidor
  (`CR-Fronend/public/fuentes`). Antes venía de Google Fonts, y cada visita le
  enviaba a Google la IP de quien entraba. La CSP ya no permite conectarse a
  ningún dominio externo.
- El tratamiento de datos sigue la **Ley 29733** y queda declarado en el
  Convenio de entrega digital (cláusula sexta) y en la página de privacidad.

## 6. Primer acceso: por correo, nunca con el DNI

Antes cada cuenta nacía con el DNI como contraseña. El DNI no es un secreto
(está en la boleta, en las listas, en cualquier trámite): quien supiera el
correo y el DNI de alguien podía entrar a su cuenta antes que él.

- La cuenta nace con una **clave aleatoria que nadie conoce**.
- El trabajador recibe el correo **«Tu acceso a CATA-Recibo»**, con un enlace
  personal que sirve **una sola vez** y **vence en 72 horas**, y crea su
  contraseña en «Crea tu contraseña» (no puede ser su DNI).
- En el alta individual el correo sale solo. Para los importados desde Excel,
  RR.HH. los marca en Empleados y pulsa **«Enviar acceso»**.
- «Restablecer contraseña» (Usuarios) también envía el enlace. Si la cuenta
  no tiene correo, genera una clave temporal aleatoria que se muestra una sola
  vez, para entregarla en persona.
- Los enlaces de acceso van en su propia tabla (`invitaciones_acceso`): uno de
  «olvidé mi contraseña» (60 minutos) no puede usarse como acceso, ni al revés.
- Las cuentas que todavía tenían el DNI quedaron con una clave aleatoria
  (migración `acceso_por_invitacion_en_lugar_del_dni`).

Prueba automática: `tests/Feature/AccesoPorCorreoTest.php`.

## 7. Pendiente (no depende del código)

1. **HTTPS en el servidor.** Sin él, contraseñas y boletas viajan sin cifrar
   por la red. Ver «Sobre entrar por IP y sin HTTPS» en `DESPLIEGUE.md`. Con
   HTTPS activo, conviene agregar la cabecera `Strict-Transport-Security`.
2. **Inscribir el banco de datos personales** del personal ante la Autoridad
   Nacional de Protección de Datos Personales, como pide la Ley 29733.
3. **Revisión legal** del Convenio de entrega digital y de la página de
   privacidad por el asesor del colegio.
4. **Configurar el correo del colegio** en el servidor (`MAIL_MAILER`,
   `MAIL_HOST`… en el `.env`). Sin él no salen los correos de acceso, y los
   trabajadores no pueden crear su contraseña.
5. **Conservar la `APP_KEY`** al cambiar de servidor: de ella dependen los QR ya
   impresos y el cifrado de los certificados.
