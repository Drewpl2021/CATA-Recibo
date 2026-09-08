# ─────────────────────────────────────────────────────────────
#  CATA-Recibo — la mitad de PHP (Laravel)
# ─────────────────────────────────────────────────────────────
#  Sirve para las tres piezas que hablan PHP: el API, el
#  trabajador de la cola y el reloj de tareas. Es la misma imagen
#  en las tres, así que no pueden ir desincronizadas.
#
#  Todas las versiones van clavadas a propósito: la gracia de
#  llevar esto a un VPS con Docker es que la máquina de allá
#  corra exactamente lo mismo que la de acá, hoy y dentro de un
#  año.
# ─────────────────────────────────────────────────────────────

# ── Las dependencias de Composer, en su propia etapa ──────────
# Composer vive SOLO aquí: en el servidor no se instala nada, se
# copia el vendor/ ya resuelto.
FROM composer:2.8 AS dependencias

WORKDIR /app

# Primero solo los dos archivos que definen las dependencias: así
# el vendor/ se rearma únicamente cuando cambian ellos, y no cada
# vez que se toca una línea de código.
COPY CR-Backend/composer.json CR-Backend/composer.lock ./

# --no-scripts: los scripts de Laravel necesitan el código de la
# aplicación, que en este punto todavía no está copiado.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Ahora sí el código, para armar el mapa de clases optimizado.
COPY CR-Backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ── La imagen que corre en el servidor ────────────────────────
FROM php:8.3-fpm-alpine AS aplicacion

# El instalador oficial de extensiones: resuelve solo las
# librerías del sistema que hace falta compilar, las quita
# después, y no se queja si una extensión ya venía dentro.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# pdo_mysql — la base de datos.
# gd         — las imágenes del PDF de la boleta (el escudo, la firma).
# zip        — lo pide Composer para descomprimir paquetes.
# opcache    — sin esto PHP recompila el código en cada petición.
RUN install-php-extensions pdo_mysql gd zip opcache

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/cata.ini

# El vendor primero y el código después: si solo cambia el código,
# Docker reaprovecha la capa pesada de las dependencias.
COPY --from=dependencias /app/vendor ./vendor
COPY CR-Backend/ ./

# www-data es el usuario con el que corren los procesos de php-fpm.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/arranque.sh /usr/local/bin/arranque
# Se quitan los saltos de línea de Windows. No es manía: si el archivo
# llega con CRLF —porque se editó en Windows, o porque git lo convirtió al
# clonar—, el contenedor responde "no such file or directory" señalando a un
# archivo que SÍ está ahí, porque lo que no encuentra es el intérprete del
# shebang. Se pierde un buen rato buscándolo.
RUN sed -i "s/\r$//" /usr/local/bin/arranque \
    && chmod +x /usr/local/bin/arranque

EXPOSE 9000

ENTRYPOINT ["arranque"]
CMD ["php-fpm"]
