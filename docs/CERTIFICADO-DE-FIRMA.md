# Cómo guardar el certificado de firma digital (.pfx / .p12)

El certificado digital es **la firma del colegio**. Quien tenga el archivo **y** su clave puede firmar documentos en nombre del colegio: boletas, constancias y cualquier otro PDF. Por eso se cuida como la llave de la caja fuerte. No se cuida como un archivo cualquiera.

Esta guía tiene tres partes:

1. Lo que **ya hace el sistema** para protegerlo.
2. Lo que **debe hacer la persona** que tiene el certificado.
3. Lo que **debe cuidar quien administra el servidor**.

---

## 1. Lo que ya hace CATA-Recibo

| Protección | Detalle |
|---|---|
| **El archivo se guarda cifrado** | Al subirlo en *Ajustes → Boletas* (**Poner mi certificado**), el `.pfx` se cifra con **AES-256-GCM** antes de entrar a la base de datos. Aunque alguien copie la base, ve un bloque ilegible. |
| **La clave del certificado NO se guarda** | Se usa para comprobar el archivo al subirlo y se olvida. Para firmar, el sistema la pide **cada vez**. |
| **El archivo nunca sale del servidor** | Ninguna pantalla ni respuesta de la API lo devuelve, ni siquiera cifrado. |
| **Contra quien prueba claves** | Tras 5 claves equivocadas, la firma se bloquea 15 minutos. |
| **Solo certificados válidos** | Se rechazan los vencidos, los hechos en casa y los que no sirven para firmar. Si la cuenta tiene DNI, el certificado tiene que ser de esa misma persona. |
| **Al quitarlo, se borra** | Al quitar o cambiar el certificado, el archivo cifrado se elimina de la base. Solo queda el registro de que existió. |
| **Queda registro** | Poner, quitar y usar el certificado queda anotado en *Auditoría*, con quién y cuándo. |
| **No puede subirse al repositorio** | El `.gitignore` bloquea `*.pfx`, `*.p12`, `*.pem`, `*.key` y `*.crt`, porque el repositorio es público. |

> El cifrado usa una llave que se deriva de `APP_KEY` (en el `.env` del servidor). Por eso la parte 3 importa tanto.

---

## 2. Para la persona que tiene el certificado

### Dónde guardar el archivo original

- ✅ En una **memoria USB solo para esto**, con contraseña (BitLocker To Go en Windows), guardada **bajo llave**.
- ✅ Una **segunda copia** en otro lugar seguro, por ejemplo la caja fuerte de Dirección, por si la primera se daña o se pierde.
- ❌ **Nunca** en la carpeta *Descargas* ni en el *Escritorio*.
- ❌ **Nunca** en Google Drive, OneDrive, WhatsApp, Telegram ni en el correo, aunque sea «para mí mismo».
- ❌ **Nunca** en una computadora compartida o pública, como la de la sala de profesores o una cabina.

### La clave del certificado

- Guárdala **separada del archivo**. Si alguien encuentra el USB, que no encuentre también la clave.
- Lo mejor es un **gestor de contraseñas** (por ejemplo Bitwarden o KeePass). Si no, en papel, en un sobre cerrado y en otro lugar bajo llave.
- **No se la dictes a nadie**, tampoco al soporte técnico ni a RR.HH. El sistema nunca te la va a pedir por correo ni por teléfono.
- Nada de pegarla en notas del celular, en un post-it ni en un archivo `clave.txt` junto al certificado.

### Al subirlo al sistema

1. Hazlo desde **tu computadora**, con el antivirus al día.
2. Entra a *Ajustes → Boletas*, sección *Tu certificado para firmar desde el sistema*, y usa **Poner mi certificado**.
3. Después, **borra la copia que quedó en la computadora**, si la copiaste del USB, y **vacía la papelera**. El original vive en el USB, no en la PC.
4. Cierra sesión al terminar.

### Al firmar

- El sistema te pedirá la clave cada vez. Si alguien más está mirando la pantalla, espera.
- Si ves una firma que **tú no hiciste**, revisa *Auditoría* y avisa de inmediato (ver «Si se pierde o lo roban»).

---

## 3. Para quien administra el servidor

- **`APP_KEY` no se cambia nunca** después de instalar. Si cambia, el certificado guardado ya no se puede descifrar, y hay que volver a subirlo.
- **Guarda una copia del `.env`** del servidor en un lugar seguro y **distinto** de los respaldos de la base. Si alguien obtiene el respaldo **y** el `.env`, tendría el archivo del certificado. Aun así le faltaría la clave, pero no hay que regalarle la mitad.
- **Permisos del `.env`** en el servidor: solo el usuario que corre Docker puede leerlo (`chmod 600 .env`).
- **Acceso al servidor** solo por SSH con llave, nunca con contraseña, y solo para quien administra.
- **Los respaldos** (servicio `respaldo`) contienen la base con el certificado **cifrado**. Guárdalos en un lugar con acceso restringido y no los subas a carpetas compartidas.
- **HTTPS obligatorio** en producción (`APP_URL` con `https://`), para que la clave que se escribe al firmar no viaje en claro.

---

## Si se pierde o lo roban

Actúa **el mismo día**:

1. **Pide la revocación** a la entidad que lo emitió, por ejemplo RENIEC (ECEP) o la empresa certificadora. Un certificado revocado deja de servir para firmar, aunque alguien tenga el archivo y la clave.
2. **Quítalo del sistema:** en *Ajustes → Boletas*, botón **Quitar** de tu certificado. El sistema borra el archivo cifrado y solo deja el registro; las boletas ya firmadas siguen siendo válidas.
3. **Revisa *Auditoría*** para ver si se firmó algo que no corresponde.
4. **Saca un certificado nuevo** y súbelo siguiendo esta guía.
5. Si sospechas que el servidor fue comprometido, avisa a quien lo administra para **cambiar las contraseñas** y revisar los accesos.

## Cuando vence

El certificado tiene fecha de vencimiento. El sistema avisa en *Ajustes* y deja de firmar con uno vencido. Renuévalo con la entidad **antes** de que venza y sube el nuevo con **Poner mi certificado**. El viejo se desactiva y su archivo se borra solo.

---

## Lista rápida

- [ ] El `.pfx` original está en un USB con contraseña, bajo llave.
- [ ] Hay una segunda copia en otro lugar seguro.
- [ ] La clave está en un gestor de contraseñas o en un sobre aparte, **no** junto al archivo.
- [ ] No hay copias en Descargas, Escritorio, Drive, correo ni WhatsApp.
- [ ] Se subió al sistema y se borró la copia de la PC (papelera vacía).
- [ ] El `.env` del servidor tiene copia segura y `APP_KEY` no se toca.
- [ ] El sistema funciona con HTTPS.
- [ ] Anotada la fecha de vencimiento para renovarlo a tiempo.
