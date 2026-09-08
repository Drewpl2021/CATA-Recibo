#!/bin/sh
# ─────────────────────────────────────────────────────────────
#  Lo que pasa cada vez que arranca un contenedor de PHP
# ─────────────────────────────────────────────────────────────
#  Corre en los tres servicios de PHP, y en cada uno hace lo
#  suyo:
#
#    php-fpm + ROL=api   →  espera la base, migra y cachea
#    la cola y el reloj  →  solo esperan a la base
#    cualquier otra cosa →  nada, se ejecuta y ya
#
#  Ese último caso importa: `docker compose run app php artisan
#  key:generate` se lanza ANTES de que exista la base. Si el
#  arranque se pusiera a esperarla, esa orden colgaría dos
#  minutos y acabaría fallando.
#
#  Y las migraciones las lanza SOLO el API: si las tres piezas lo
#  intentaran a la vez, se pisarían entre ellas.
# ─────────────────────────────────────────────────────────────
set -e

esperar_a_la_base() {
    echo "→ esperando a la base de datos en ${DB_HOST}…"
    intentos=0
    # Se prueba con PDO y no con el cliente de mysql: es exactamente la
    # conexión que va a usar Laravel, así que si esto pasa, pasa de verdad —
    # y evita meter otro paquete en la imagen.
    until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 60 ]; then
            echo "✗ la base no respondió en dos minutos. Revisa DB_HOST, DB_USERNAME y DB_PASSWORD en el .env."
            exit 1
        fi
        sleep 2
    done
    echo "✓ la base responde"
}

preparar_aplicacion() {
    if [ -z "${APP_KEY}" ]; then
        echo "✗ falta APP_KEY. Genérala una vez con:"
        echo "    docker compose run --rm --no-deps app php artisan key:generate --show"
        echo "  y pégala en APP_KEY= dentro del .env."
        exit 1
    fi

    echo "→ aplicando migraciones…"
    php artisan migrate --force

    # La configuración cacheada es lo que evita que Laravel ande leyendo
    # archivos en cada petición. Se rehace en cada arranque porque las
    # variables de entorno pueden haber cambiado desde la última vez.
    echo "→ cacheando configuración, rutas y vistas…"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "✓ aplicación lista"
}

# Los permisos se ajustan al arrancar porque storage/app es un volumen: lo
# crea Docker, no la imagen, y nace siendo de root.
arreglar_permisos() {
    chown -R www-data:www-data storage bootstrap/cache
}

case "$1" in
    php-fpm)
        arreglar_permisos
        esperar_a_la_base
        [ "${ROL:-api}" = "api" ] && preparar_aplicacion
        ;;
    php)
        # La cola y el reloj corren `php artisan …` y necesitan la base;
        # una orden suelta lanzada a mano, no.
        if [ "${ROL}" = "trabajador" ] || [ "${ROL}" = "reloj" ]; then
            arreglar_permisos
            esperar_a_la_base
        fi
        ;;
esac

exec "$@"
