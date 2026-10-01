# Instalar CATA-Recibo sin Docker

Esta guía es para cuando de verdad no hay Docker disponible y toca armar cada
pieza a mano: PHP, MySQL, Node y nginx, directo en el sistema operativo.

**Si puedes instalar Docker, no uses esta guía.** [INSTALACION.md](INSTALACION.md)
hace lo mismo en una fracción del tiempo, y cada pieza queda exactamente igual
en cualquier máquina — que es justo lo que esta guía no puede garantizar,
porque depende de cómo esté armado el servidor.

Está escrita para **Ubuntu/Debian** (`apt`). Si el servidor es otra
distribución, los pasos son los mismos; cambian los nombres de los paquetes.

---

## 0. Qué arma cada pieza

| Con Docker | Sin Docker, lo reemplaza |
|---|---|
| Contenedor `base` (MySQL) | MySQL o MariaDB instalado en el sistema |
| Contenedor `app` (PHP-FPM) | `php8.3-fpm` instalado, con sus extensiones |
| Contenedor `web` (nginx + Angular compilado) | `nginx` instalado + `ng build` corrido a mano |
| Contenedor `cola` | Un servicio de systemd corriendo `queue:work` sin parar |
| Contenedor `reloj` | Un servicio de systemd corriendo `schedule:work` sin parar |
| Contenedor `respaldo` | Una tarea de cron con `mysqldump` + `tar` |
| `docker compose up -d --build` | Repetir a mano los pasos 4, 6 y 7 cada vez que cambie el código |

---

## 1. Instalar lo que hace falta

```bash
sudo apt update

# PHP 8.3 y las extensiones que usa el sistema
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-gd php8.3-zip \
    php8.3-opcache php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath

# Composer (gestor de paquetes de PHP)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 22 (para compilar Angular — no hace falta para correr el sistema
# después, solo mientras se compila)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL 8.4 (si el VPS no trae ya una base de datos)
sudo apt install -y mysql-server

# nginx
sudo apt install -y nginx

# Git
sudo apt install -y git
```

Comprueba las versiones:

```bash
php -v          # 8.3.x
composer -V     # 2.x
node -v         # v22.x
mysql --version # 8.4.x (o MariaDB 10.11+)
nginx -v
```

---

## 2. Traer el código

```bash
git clone https://github.com/Drewpl2021/CATA-Recibo.git cata-recibo
cd cata-recibo
git checkout PastorDev
```

---

## 3. La base de datos

**Si ya tienes un `.sql` con los catálogos (y quizás el personal) listos** —el
que te dieron para este caso, `cata-recibo-basico.sql`— es más rápido
importarlo directo que migrar y sembrar:

```bash
sudo mysql -u root -e "CREATE DATABASE IF NOT EXISTS colegio_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root colegio_db < cata-recibo-basico.sql
```

Eso ya deja las tablas, los catálogos (áreas, cargos, sedes, conceptos de
pago), el trigger `trg_planilla_historial`, el procedimiento
`sp_resumen_planilla` y —si el `.sql` los trae— el personal.

**Un usuario propio para la aplicación** (no uses `root` para que Laravel se
conecte):

```bash
sudo mysql -u root -e "
CREATE USER 'cata'@'localhost' IDENTIFIED BY 'PON-AQUI-UNA-CONTRASEÑA';
GRANT ALL PRIVILEGES ON colegio_db.* TO 'cata'@'localhost';
FLUSH PRIVILEGES;"
```

> Si en vez del `.sql` vas a sembrar desde cero, salta a los pasos 4 y 5,
> genera el `APP_KEY`, y ahí corre `php artisan migrate --force` y
> `php artisan db:seed --force` en vez de importar el `.sql`. El paso 6
> (permisos) va igual antes de arrancar nada.

Uno de los dos problemas más comunes de MySQL en un servidor recién instalado:
si crear el trigger falla con **Error 1419** ("You do not have the SUPER
privilege and binary logging is enabled"), corre esto una vez:

```bash
sudo mysql -u root -e "SET GLOBAL log_bin_trust_function_creators = 1;"
```

Y para que sobreviva a un reinicio del servidor, agrégalo también al
`my.cnf` (normalmente `/etc/mysql/mysql.conf.d/mysqld.cnf`), dentro de
`[mysqld]`:

```ini
log_bin_trust_function_creators = 1
```

---

## 4. El backend (Laravel)

```bash
cd CR-Backend
composer install --no-dev --optimize-autoloader
```

Copia la plantilla y rellénala:

```bash
cp .env.example .env
nano .env
```

Lo mínimo que hay que poner (los demás campos de `.env.example` traen
comentarios explicando cada uno):

| Variable | Qué poner |
|---|---|
| `APP_URL` | La dirección por la que va a entrar la gente: `http://LA-IP-DEL-SERVIDOR` (o el dominio, si ya lo tienen) |
| `DB_DATABASE` | `colegio_db` |
| `DB_USERNAME` | `cata` |
| `DB_PASSWORD` | La que pusiste en el paso 3 |
| `DB_HOST` | `127.0.0.1` |
| `MAIL_MAILER` | `log` para empezar (los correos se escriben en el log en vez de enviarse) |

Genera la llave de la aplicación —una sola vez en la vida del sistema—:

```bash
php artisan key:generate
```

Cachea la configuración (Docker lo hace solo al arrancar; aquí toca
repetirlo a mano cada vez que cambie el `.env` o el código — ver el paso 8):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Permisos de las carpetas

PHP-FPM corre como el usuario `www-data`. Necesita poder escribir en:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Y donde se guardan las boletas y los documentos subidos
(`storage/app`) tiene que sobrevivir a cualquier actualización de código:
**no lo borres nunca al hacer `git pull`.**

---

## 6. El frontend (Angular)

Se compila una vez y el resultado son archivos estáticos — no hace falta
Node corriendo en el servidor después de esto.

```bash
cd ../CR-Fronend
npm ci
npm run build -- --configuration production
```

Copia lo que compiló a donde nginx sirve los archivos:

```bash
sudo mkdir -p /var/www/cata-recibo/html
sudo cp -r dist/turecibo-app/browser/. /var/www/cata-recibo/html/
```

Y el `public/` de Laravel, que nginx necesita ver en la misma ruta que
PHP-FPM para poder pasarle las peticiones del API:

```bash
sudo mkdir -p /var/www/cata-recibo/api
sudo cp -r ../CR-Backend/public/. /var/www/cata-recibo/api/
```

---

## 7. nginx

Revisa primero en qué escucha tu `php8.3-fpm` (socket o puerto):

```bash
cat /etc/php/8.3/fpm/pool.d/www.conf | grep ^listen
```

Suele ser un socket unix, algo como
`/run/php/php8.3-fpm.sock`. Úsalo tal cual salga ahí.

Crea `/etc/nginx/sites-available/cata-recibo`:

```nginx
server {
    listen 80;
    server_name _;

    client_max_body_size 14M;

    root /var/www/cata-recibo/html;
    index index.html;

    server_tokens off;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' blob:; worker-src 'self' blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self' blob:; object-src 'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'" always;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    # ── El API ───────────────────────────────────────────────
    location ^~ /api {
        root /var/www/cata-recibo/api;
        try_files $uri @laravel;
    }

    location @laravel {
        root /var/www/cata-recibo/api;

        include fastcgi_params;
        # Cambia esto por lo que dio el "grep ^listen" de arriba.
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;

        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param PATH_INFO $uri;

        fastcgi_read_timeout 180;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # ── Angular ──────────────────────────────────────────────
    location ~* \.(?:js|css|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location = /index.html {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

Actívala y recarga nginx:

```bash
sudo ln -s /etc/nginx/sites-available/cata-recibo /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Ajusta también `php.ini` (busca cuál carga tu `php8.3-fpm` con
`php --ini`, o edita `/etc/php/8.3/fpm/php.ini`):

```ini
upload_max_filesize = 12M
post_max_size = 14M
max_execution_time = 120
memory_limit = 512M
date.timezone = America/Lima
expose_php = Off
display_errors = Off
```

Y reinicia PHP-FPM para que los tome:

```bash
sudo systemctl restart php8.3-fpm
```

**Antes de confiar el sistema a un día de mucha gente a la vez** (todo el
colegio firmando su boleta el mismo día, por ejemplo): revisa cuántas
peticiones puede atender PHP-FPM AL MISMO TIEMPO, porque el valor de
fábrica del paquete de Ubuntu viene pensado para un sitio chico, no para
doscientos docentes entrando juntos.

```bash
cat /etc/php/8.3/fpm/pool.d/www.conf | grep ^pm
```

Si `pm.max_children` sale en **5** (el valor por defecto), el servidor
solo procesa 5 peticiones a la vez — el resto hace fila. No es que falle:
la pantalla se siente trabada, y si doscientas personas entran a firmar
en la misma ventana de minutos (no a lo largo del día, que eso sí lo
aguanta sin drama), la fila se nota.

Para subirlo, edita `/etc/php/8.3/fpm/pool.d/www.conf` y cambia el bloque
`pm`:

```ini
pm = dynamic
pm.max_children = 12
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

El número de `pm.max_children` depende de la RAM del servidor, no es el
mismo para todos. La cuenta:

```
pm.max_children = (RAM que le sobra a PHP después de MySQL, nginx y el sistema) / (RAM por proceso PHP)
```

En este sistema, cada proceso PHP usa entre 40 y 100 MB en uso normal
(`memory_limit = 512M` de arriba es el TECHO de emergencia para armar el
PDF de toda la planilla de un jalón, no lo que gasta una petición
cualquiera). Con esa cuenta: un VPS de 2 GB aguanta unos 10-12, uno de
4 GB unos 20-25, uno de 8 GB 30-40 sin apuro. Si no sabes cuánta RAM
tiene el servidor:

```bash
free -h
```

Reinicia PHP-FPM para que tome el cambio:

```bash
sudo systemctl restart php8.3-fpm
```

Prueba que el API responde:

```bash
curl http://localhost/api/me
```

Debe contestar `{"message":"Unauthenticated."}` — significa que
navegador → nginx → PHP-FPM → Laravel → base está completo.

---

## 8. La cola y el reloj (sin ellos, no llegan los correos ni se purgan los tokens)

Dos servicios de systemd, calcados de lo que hacen los contenedores `cola` y
`reloj`.

`/etc/systemd/system/cata-cola.service`:

```ini
[Unit]
Description=CATA-Recibo — cola de correos
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/ruta/a/cata-recibo/CR-Backend
ExecStart=/usr/bin/php artisan queue:work --tries=3 --sleep=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/cata-reloj.service`:

```ini
[Unit]
Description=CATA-Recibo — tareas diarias
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/ruta/a/cata-recibo/CR-Backend
ExecStart=/usr/bin/php artisan schedule:work
Restart=always

[Install]
WantedBy=multi-user.target
```

Cambia `/ruta/a/cata-recibo` por la ruta real, y activa los dos:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cata-cola cata-reloj
sudo systemctl status cata-cola cata-reloj
```

---

## 9. Entrar

Abre `http://LA-IP-DEL-SERVIDOR` y entra con la cuenta de administrador que
traiga el `.sql` (o las que imprimió `db:seed` si sembraste desde cero).

---

## 10. El día a día — actualizar a una versión nueva

Sin `docker compose up -d --build` haciéndolo todo, toca repetir a mano:

```bash
cd /ruta/a/cata-recibo
git pull

cd CR-Backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.3-fpm       # opcache no relee los archivos solo

cd ../CR-Fronend
npm ci
npm run build -- --configuration production
sudo cp -r dist/turecibo-app/browser/. /var/www/cata-recibo/html/
sudo cp -r ../CR-Backend/public/. /var/www/cata-recibo/api/

sudo systemctl restart cata-cola cata-reloj
```

El `systemctl restart php8.3-fpm` **no es opcional**: `php.ini` tiene
`opcache.validate_timestamps = 0` (código cacheado que nunca se revisa solo,
pensado para cuando Docker reconstruye la imagen entera en cada despliegue).
Sin el reinicio, el servidor sigue sirviendo el PHP viejo aunque el `git
pull` haya traído el nuevo.

---

## 11. Respaldos

Dos cosas, igual que con Docker — **con una sola no se recupera el sistema**.

Un script, `/usr/local/bin/respaldo-cata.sh`:

```bash
#!/bin/bash
set -e
FECHA=$(date +%F)
DESTINO=/var/respaldos/cata-recibo
mkdir -p "$DESTINO"

mysqldump -u root colegio_db | gzip > "$DESTINO/base_$FECHA.sql.gz"
tar czf "$DESTINO/archivos_$FECHA.tar.gz" -C /ruta/a/cata-recibo/CR-Backend/storage/app .

# Se guardan solo los últimos 30 días.
find "$DESTINO" -type f -mtime +30 -delete
```

```bash
sudo chmod +x /usr/local/bin/respaldo-cata.sh
```

Y en el cron de `root` (`sudo crontab -e`), todas las noches:

```cron
0 2 * * * /usr/local/bin/respaldo-cata.sh
```

Copia `/var/respaldos/cata-recibo` fuera del servidor cada cierto tiempo: un
respaldo que vive en el mismo disco no protege de un disco roto.

---

## 12. Si algo no arranca

| Lo que ves | Qué revisar |
|---|---|
| 502 Bad Gateway | `php8.3-fpm` no está corriendo, o el `fastcgi_pass` del nginx.conf no coincide con su socket real (`grep ^listen` en `www.conf`) |
| Error 500 en la pantalla | `tail -f CR-Backend/storage/logs/laravel.log` — con `APP_DEBUG=false` el detalle no sale en el navegador a propósito |
| Recargar en `/inicio/empleados` da 404 de nginx | Falta el `try_files $uri $uri/ /index.html;` del `location /` |
| Los correos no llegan | Con `MAIL_MAILER=log`, es lo esperado — revisa `storage/logs/laravel.log`. Para enviarlos de verdad: `MAIL_MAILER=smtp` y `MAIL_SCHEME=smtp` (puerto 587) o `smtps` (465) |
| El código nuevo no se nota tras un `git pull` | `sudo systemctl restart php8.3-fpm` — ver el paso 10 |
| Error 1419 al crear el trigger | Ver el aviso al final del paso 3 |

---

## 13. Lo que nunca sube al repositorio

El repositorio es **público**. Nunca entran ahí:

- El `.env` (lleva todas las contraseñas)
- Los `.sql` y los respaldos
- Los Excel con datos reales del personal
- Cualquier archivo donde anotes credenciales

Ya están en el `.gitignore`. Basta con no forzarlos con `git add -f`.
