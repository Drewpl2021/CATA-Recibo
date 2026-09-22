# Instalar CATA-Recibo en una computadora

Esta guía va de cero a sistema funcionando. La computadora solo necesita
**Docker** y **Git**: ni PHP, ni MySQL, ni Node — todo eso vive dentro de los
contenedores.

Si lo que quieres es *mudar* un sistema que ya está en uso con sus datos, haz
igual los pasos 1 a 5 y después salta al [paso 9](#9-traer-los-datos-de-otra-computadora).

---

## 1. Lo que hace falta

| | |
|---|---|
| **Docker Desktop** (Windows/Mac) o Docker + el plugin `compose` (Linux) | Abierto y diciendo *Engine running* |
| **Git** | Para traer el código |

---

## 2. Traer el código

```bash
git clone https://github.com/Drewpl2021/CATA-Recibo.git cata-recibo
cd cata-recibo
git checkout PastorDev
```

> La rama de trabajo es **PastorDev**. `main` va por detrás.

---

## 3. El archivo `.env`

No viene en el repositorio a propósito: lleva contraseñas. Se copia de la
plantilla:

```bash
copy .env.example .env      # Windows (cmd)
cp .env.example .env        # Linux / Mac
```

Y se rellena. Esto es lo **obligatorio**:

| Línea | Qué poner |
|---|---|
| `APP_URL` | La dirección por la que entra la gente. En tu PC: `http://localhost:8081`. En un servidor: `http://LA-IP`. |
| `PUERTO_HTTP` | El puerto de esa misma dirección: `8081`. **Tiene que coincidir con `APP_URL`.** |
| `APP_KEY` | Se genera en el paso 4. |
| `DB_DATABASE` | `colegio_db` está bien. |
| `DB_USERNAME` | `cata` está bien. |
| `DB_PASSWORD` | **Invéntala y anótala.** Solo letras y números. |
| `DB_ROOT_PASSWORD` | Otra distinta, también letras y números. |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | La cuenta del administrador (ver abajo). |
| `RRHH_EMAIL` / `RRHH_PASSWORD` | La cuenta de Recursos Humanos. |
| `MAIL_MAILER` | `log` para empezar: los correos se escriben en el registro en vez de enviarse. |

Tres advertencias que cuestan una tarde si se pasan por alto:

- **Las contraseñas de la base se graban la primera vez que arranca MySQL.**
  Cambiarlas después no sirve de nada: el contenedor seguirá esperando las
  viejas. Decídelas ahora.
- **Sin comillas, sin espacios alrededor del `=`, y sin `#`, `$` ni `%`**
  dentro del valor: a Docker le confunden.
- En Windows, el Bloc de notas a veces guarda el archivo como **`.env.txt`**.
  Comprueba con `dir /a .env*` que se llame exactamente `.env`.

### Las dos cuentas con las que se entra

El sistema nace con dos cuentas, y **ninguna tiene ficha de trabajador**: son
cuentas para operar el sistema, no personas en planilla. Al personal se le da
de alta después, desde la pantalla de Empleados.

| Cuenta | Para qué | Qué puede hacer |
|---|---|---|
| **Administrador** (`ADMIN_EMAIL`) | La dirección / quien administra el sistema | Todo: configuración, usuarios, roles, permisos de los módulos y la auditoría |
| **Recursos Humanos** (`RRHH_EMAIL`) | El día a día | Personal, contratos, planillas, boletas y vacaciones. No toca roles ni permisos |

Si dejas `ADMIN_PASSWORD` y `RRHH_PASSWORD` **vacías**, al sembrar (paso 6) el
sistema inventa una contraseña larga para cada una, **la enseña una sola vez en
pantalla** y te obliga a cambiarla al entrar. Si las escribes tú en el `.env`,
esas quedan.

> ¿Dónde anoto las mías? En un `ACCESOS.md` dentro de la carpeta del proyecto:
> ese nombre está ignorado por git, así que no se sube nunca. O donde guardes
> las contraseñas del colegio. **Nunca dentro de un archivo que suba al
> repositorio, y nunca por chat.**

---

## 4. La llave de la aplicación

Una sola vez en la vida del sistema:

```bash
docker compose run --rm --no-deps app php artisan key:generate --show
```

La primera vez construye la imagen, así que tarda unos minutos. Copia lo que
imprime (empieza con `base64:`) y pégalo en `APP_KEY=` dentro del `.env`.

---

## 5. Levantar

```bash
docker compose up -d --build
docker compose ps
```

Tienen que quedar **seis piezas** arriba:

| Pieza | Qué hace |
|---|---|
| `base` | MySQL. Debe decir **healthy** |
| `app` | El API (Laravel). Aplica las migraciones al arrancar |
| `web` | nginx: sirve la pantalla y reparte `/api`. Es el único con puerto al exterior |
| `cola` | Manda los correos de aviso |
| `reloj` | Las tareas diarias |
| `respaldo` | La copia de cada noche |

Comprueba que el API responde:

```bash
curl http://localhost:8081/api/me
```

Debe contestar `{"message":"Unauthenticated."}` o similar. Eso significa que el
camino navegador → nginx → PHP → Laravel → base está completo.

---

## 6. Sembrar — **solo en una instalación nueva**

```bash
docker compose exec app php artisan db:seed --force
```

Deja lo indispensable para empezar a trabajar:

- Los **3 roles** (admin, rrhh, empleado) y el **menú** del sistema
- **15 áreas** y **34 cargos**
- **26 conceptos de pago** (sueldo, gratificación, ONP, AFP, EsSalud, descuentos…)
- Las **4 sedes**: CATA Central, CATA Jerusalén, CATA Osis, CATA Inicial
- Las **2 cuentas** del paso 3

**No siembra ningún trabajador**: la plantilla nace vacía y la llenas tú, una
por una desde *Empleados → Nuevo*, o en lote desde *Importar empleados*.

Si dejaste las contraseñas vacías, este comando imprime las que inventó. Cópialas
**ahora**: no se vuelven a mostrar.

> Si la base ya tiene gente dentro, el comando se frena solo y te lo dice:
> volver a sembrar borraría los catálogos y dejaría al personal sin área, sin
> cargo y sin permisos.

### La otra forma: cargar la semilla

En el repositorio va un `.sql` con los mismos catálogos ya listos
(`CR-Backend/database/semilla/instalacion-basica.sql`). Da igual cuál uses en
una instalación nueva:

```bash
docker compose exec app php artisan semilla:importar
docker compose exec app php artisan db:seed --class=CuentasInicialesSeeder --force
```

El primero carga roles, menú, áreas, cargos, sedes y conceptos de pago; el
segundo crea las dos cuentas a partir del `.env`.

Ese archivo **no lleva personas**: ni usuarios, ni trabajadores, ni planillas,
ni boletas. Solo catálogos. Y solo los datos: las tablas las crea `migrate`.

Si cambias los catálogos desde las pantallas (agregas un cargo, una sede) y
quieres que la próxima instalación nazca con ellos, vuelve a generarlo:

```bash
docker compose exec app php artisan semilla:exportar
```

Escribe el archivo, dice cuántas filas salió cada tabla, y ahí sí se sube al
repositorio como un cambio más. **Antes de subirlo, míralo**: si algún día
aparece un nombre o un DNI ahí dentro, ese archivo no se sube.

> `db:seed` y la semilla hacen lo mismo hoy. La diferencia: `db:seed` se
> mantiene al día solo con el código, y la semilla es una foto que hay que
> volver a tomar cuando los catálogos cambien.

---

## 7. Entrar

Abre `http://localhost:8081` (o `http://LA-IP`) y entra con la cuenta de
administrador.

Lo primero, desde *Configuración → Sedes*, ponle dirección y teléfono a las
cuatro sedes. Después, *Empleados → Nuevo* para dar de alta al personal: al
guardar, a cada trabajador se le crea su cuenta de acceso.

---

## 8. Entrar a la base de datos

La base **no asoma al exterior** a propósito: solo se ve desde dentro de la red
de Docker. Hay dos maneras de mirarla.

**a) Desde la consola, sin abrir nada** (la recomendada):

```bash
docker compose exec base mysql -u root -p colegio_db
```

Te pide la contraseña: es `DB_ROOT_PASSWORD` del `.env`. Con `cata` y
`DB_PASSWORD` entras igual, pero con menos permisos.

Un par de consultas para empezar:

```sql
SHOW TABLES;
SELECT dni, nombre, apellido, estado FROM empleados ORDER BY apellido;
SELECT email, rol_id FROM users;
```

**b) Con un programa de escritorio** (HeidiSQL, DBeaver, Workbench). Hay que
publicar el puerto **temporalmente**. En `compose.yaml`, dentro de `base:`,
agrega:

```yaml
    ports:
      - "127.0.0.1:3307:3306"
```

y `docker compose up -d base`. Luego te conectas a:

| Dato | Valor |
|---|---|
| Servidor | `127.0.0.1` |
| Puerto | `3307` |
| Usuario | `root` (o `cata`) |
| Contraseña | `DB_ROOT_PASSWORD` (o `DB_PASSWORD`) del `.env` |
| Base | `colegio_db` |

El `127.0.0.1:` del principio es importante: deja el puerto visible **solo desde
esa computadora**. Cuando termines, quita esas dos líneas y vuelve a levantar:
una base de planillas no debe quedar escuchando en la red.

---

## 9. Traer los datos de otra computadora

Nada de esto viaja por git: el código va por el repositorio, los datos van
aparte.

**En la computadora que tiene los datos:**

```bash
docker compose exec respaldo bash /respaldo/respaldar.sh
docker compose exec respaldo ls -lh /respaldos        # anota la fecha, p. ej. 2026-09-22_0200
docker compose cp respaldo:/respaldos/base_FECHA.sql.gz .
docker compose cp respaldo:/respaldos/archivos_FECHA.tar.gz .
```

Pasa esos **dos** archivos por USB (uno es la base y el otro los PDF de boletas
y las firmas: van juntos o no sirve de nada).

**En la computadora nueva**, ya levantada:

```bash
docker compose cp base_FECHA.sql.gz respaldo:/respaldos/
docker compose cp archivos_FECHA.tar.gz respaldo:/respaldos/
docker compose exec -e CONFIRMAR=si respaldo bash /respaldo/restaurar.sh FECHA
```

Eso **reemplaza** lo que haya (por eso pide `CONFIRMAR=si`). Si restauras, no
siembres: los datos restaurados ya traen sus usuarios, y las contraseñas son las
de la máquina de origen.

El `.env` también hay que llevarlo a mano si quieres las mismas credenciales.
Copia el archivo por USB, nunca por el repositorio.

---

## 10. El día a día

```bash
docker compose ps                  # qué está corriendo
docker compose logs -f app         # qué dice el API
docker compose logs -f cola        # los correos (con MAIL_MAILER=log salen acá)
docker compose down                # parar; los datos se quedan

git pull                           # traer lo nuevo del repositorio
docker compose up -d --build       # y aplicarlo: las migraciones corren solas
```

Los respaldos salen solos cada noche a las 02:00 y se guardan los últimos 30
días, en el mismo disco. Cópialos fuera cada cierto tiempo: un respaldo que vive
junto al original no protege de un disco roto.

---

## 11. Si algo no arranca

| Lo que ves | Qué pasa |
|---|---|
| `base` queda **unhealthy** y el log dice *"Database is uninitialized and password option is not specified"* | `DB_ROOT_PASSWORD` llegó vacío. Revisa que el `.env` se llame así (no `.env.txt`) y que tenga las dos contraseñas. Compruébalo con `docker compose config` |
| *"Ports are not available … 8081"* | Ese puerto está ocupado por otro programa. Mira quién con `netstat -ano \| findstr :8081`, o cambia `PUERTO_HTTP` **y** `APP_URL` a otro número |
| *"Se queda esperando a la base"* | Cambiaste `DB_PASSWORD` después del primer arranque. O la cambias dentro de MySQL, o empiezas de cero con `docker compose down -v` (⚠️ **borra los datos**) |
| La pantalla carga pero da error 500 | `docker compose logs app`. El detalle no se enseña en el navegador a propósito, pero queda en el registro |
| Los correos no llegan | Con `MAIL_MAILER=log` es lo esperado. Para enviarlos de verdad: `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp` (puerto 587) o `smtps` (465), y los datos del buzón del colegio |

---

## 12. Lo que nunca sube al repositorio

El repositorio es **público**. Nunca entran ahí:

- El `.env` (lleva todas las contraseñas)
- Los `.sql` y los respaldos
- Los Excel con datos reales del personal
- El `ACCESOS.md` donde anotes las credenciales

Ya están en el `.gitignore`. Basta con no forzarlos con `git add -f`.
