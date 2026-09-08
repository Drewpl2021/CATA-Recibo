# Llevar CATA-Recibo a un servidor

Todo el sistema levanta con una sola orden. No hace falta instalar PHP, Node,
Composer ni MySQL en el servidor: lo único que se instala es Docker.

---

## Qué se levanta

| Pieza | Qué hace |
|---|---|
| **web** | nginx. Reparte: la aplicación por `/` y el API por `/api`. Es la única que asoma a internet. |
| **app** | Laravel sobre php-fpm. Atiende el API, aplica las migraciones al arrancar. |
| **base** | MySQL 8.4, con su volumen para que los datos sobrevivan. |
| **cola** | Manda los correos de aviso de boleta sin hacer esperar a la pantalla. |
| **reloj** | Las tareas diarias (purgar los tokens de sesión vencidos). Reemplaza al cron. |

Las tres piezas de PHP salen de la **misma imagen**, así que no pueden quedar
desincronizadas entre ellas.

---

## Por qué funcionan las peticiones sin configurar nada

Es la parte que suele dar guerra al desplegar, y aquí está resuelta de raíz:
**todo sale por una sola puerta**.

```
Navegador  ──►  nginx (puerto 80)
                  │
                  ├── /          →  la aplicación de Angular
                  └── /api/…     →  Laravel (php-fpm)
```

El frontend pide a **`/api/login`**, sin nombre de servidor. El navegador
completa esa dirección con la que tenga en la barra: hoy la IP del VPS, mañana
el dominio del colegio. Consecuencias:

- **Cambiar de IP a dominio no obliga a recompilar nada.** Se cambia `APP_URL`
  en el `.env` y se reinicia.
- **No hay CORS que pelear.** Para el navegador es un solo origen.

---

## Puesta en marcha

En el servidor hace falta Docker (con el plugin `compose`), nada más.

```bash
git clone <el-repositorio> cata-recibo
cd cata-recibo

cp .env.example .env
nano .env          # las contraseñas y la dirección — ver abajo
```

**Genera la llave de la aplicación** (una sola vez en la vida del sistema):

```bash
docker compose run --rm --no-deps app php artisan key:generate --show
```

Copia lo que imprime (`base64:…`) en `APP_KEY=` dentro del `.env`.

**Levanta todo:**

```bash
docker compose up -d --build
```

La primera vez tarda unos minutos: compila Angular e instala las dependencias
de PHP. Las siguientes van mucho más rápido.

**Siembra los datos iniciales** (roles, áreas, cargos, sedes, conceptos de pago
y el menú). Esto se hace **una sola vez**, en un sistema recién instalado:

```bash
docker compose exec app php artisan db:seed --force
```

Ya está. Entra en `http://LA-IP-DEL-SERVIDOR`.

> El `--force` hace falta porque en producción Laravel pregunta antes de
> tocar la base, y aquí no hay nadie para contestarle.
>
> ⚠️ **`db:seed` borra los empleados y usuarios existentes** para dejar los de
> ejemplo. No lo corras nunca en un sistema que ya esté en uso.

---

## Lo que hay que rellenar en el `.env`

| Variable | Qué es |
|---|---|
| `APP_URL` | Por dónde entra la gente. Hoy `http://IP-DEL-VPS`. |
| `APP_KEY` | La llave que genera el comando de arriba. |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | **Cámbialas antes del primer arranque.** MySQL las aplica al crear la base; después ya no se cambian solas. |
| `MAIL_*` | Con `MAIL_MAILER=log` los correos no salen, se escriben en el log. Para que salgan de verdad, `smtp` y los datos del buzón del colegio. |

---

## Sobre entrar por IP y sin HTTPS

Es lo que se eligió para arrancar, y funciona, pero conviene tenerlo claro:
**las contraseñas y las boletas viajan sin cifrar**. Cualquiera en la misma red
—o en el camino hasta el servidor— puede leerlas.

Sirve para probar en la red del colegio. Antes de abrirlo a internet de verdad,
hay que ponerle HTTPS. Cuando tengan el dominio apuntando al VPS, el cambio es
pequeño y lo dejo hecho en un rato: se le pone delante un contenedor que saca y
renueva el certificado solo.

---

## El día a día

```bash
# Ver qué está corriendo
docker compose ps

# Los registros (todo, o de una pieza)
docker compose logs -f
docker compose logs -f app

# Actualizar a la última versión del código
git pull
docker compose up -d --build      # las migraciones se aplican solas al arrancar

# Entrar a la consola de Laravel
docker compose exec app php artisan tinker

# Parar todo (los datos se quedan)
docker compose down
```

---

## Respaldos

Hay **dos cosas** que respaldar. Con una sola no se recupera el sistema:

**1. La base de datos**

```bash
docker compose exec base mysqldump -u root -p"$DB_ROOT_PASSWORD" colegio_db \
  > respaldo-$(date +%F).sql
```

**2. Los archivos** — los PDF de las boletas ya firmadas. Están en el volumen
`archivos`; si se pierde, los documentos firmados no se pueden reconstruir
(un PDF firmado se congela tal como se firmó, a propósito).

```bash
docker run --rm \
  -v cata-recibo_archivos:/datos \
  -v "$PWD":/respaldo \
  alpine tar czf /respaldo/archivos-$(date +%F).tar.gz -C /datos .
```

Ponlos los dos en un cron diario, y llévate las copias fuera del servidor.

---

## Si algo no arranca

**La aplicación no responde.** Mira si las piezas están arriba y qué dicen:

```bash
docker compose ps
docker compose logs app
```

**Se queda esperando a la base.** El arranque reintenta durante dos minutos y
luego se rinde diciéndolo. Suele ser una contraseña que no coincide entre el
`.env` y la base ya creada: si cambiaste `DB_PASSWORD` **después** del primer
arranque, MySQL sigue con la vieja. O se cambia dentro de MySQL, o se borra el
volumen y se empieza de cero (`docker compose down -v` — **se lleva los datos**).

**Sale "500" en el API.** `docker compose logs app`. Con `APP_DEBUG=false` el
detalle no se enseña al navegador —a propósito, para no filtrar rutas ni
credenciales— pero sí queda en el registro.

**Los correos no llegan.** Con `MAIL_MAILER=log` es lo esperado: se escriben en
`docker compose logs cola`. Para enviarlos de verdad hay que configurar el SMTP
del colegio.
