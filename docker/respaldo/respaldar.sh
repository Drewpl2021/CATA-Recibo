#!/usr/bin/env bash
#
# Un respaldo completo del colegio: la base de datos Y los archivos.
#
#   · base_AAAA-MM-DD_HHMM.sql.gz     sueldos, planillas, fichas, firmas
#   · archivos_AAAA-MM-DD_HHMM.tar.gz  los PDF de las boletas y las
#                                      imágenes de firma y huella
#
# Las dos cosas hacen falta: una base sin sus PDF deja boletas "emitidas"
# que no se pueden descargar, y los PDF sin la base no dicen de quién son.
#
# Se guarda en el volumen `respaldos` y se borran solos los de más de
# RESPALDO_DIAS días (30 por defecto).
#
# OJO: esto protege de un error o de una base rota, NO de que se pierda el
# servidor entero — los respaldos viven en el mismo disco. Copia la carpeta
# `respaldos` fuera de vez en cuando (a un Drive, a otro equipo). Ver el
# final de este archivo.

set -euo pipefail

DESTINO=/respaldos
DIAS="${RESPALDO_DIAS:-30}"
FECHA="$(date +%Y-%m-%d_%H%M)"

mkdir -p "$DESTINO"

# La contraseña va en un archivo temporal que solo lee el dueño, y no en la
# línea de comandos: ahí la vería cualquiera que liste los procesos.
CREDENCIALES="$(mktemp)"
chmod 600 "$CREDENCIALES"
trap 'rm -f "$CREDENCIALES"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\n' \
    "${DB_HOST:-base}" "$DB_USERNAME" "$DB_PASSWORD" > "$CREDENCIALES"

echo "[respaldo] $(date '+%F %T') empieza"

# ── La base ────────────────────────────────────────────────────────
# --single-transaction: una foto coherente de todas las tablas sin bloquear
#   a nadie, así se puede respaldar con RR.HH. trabajando.
# --no-tablespaces: el usuario de la aplicación no tiene el permiso PROCESS
#   que pide MySQL 8 para volcar los tablespaces, y no hacen falta.
# Se escribe primero como ".parcial" y se renombra al final: si algo falla a
#   medias, nunca queda un archivo roto con cara de respaldo bueno.
BASE="$DESTINO/base_$FECHA.sql.gz"
mysqldump --defaults-extra-file="$CREDENCIALES" \
    --single-transaction --quick --routines --triggers --no-tablespaces \
    "$DB_DATABASE" | gzip -9 > "$BASE.parcial"
gzip -t "$BASE.parcial"
mv "$BASE.parcial" "$BASE"

# ── Los archivos ───────────────────────────────────────────────────
ARCHIVOS="$DESTINO/archivos_$FECHA.tar.gz"
tar -czf "$ARCHIVOS.parcial" -C /archivos .
gzip -t "$ARCHIVOS.parcial"
mv "$ARCHIVOS.parcial" "$ARCHIVOS"

# ── Los viejos se van ──────────────────────────────────────────────
find "$DESTINO" -maxdepth 1 \( -name 'base_*.sql.gz' -o -name 'archivos_*.tar.gz' \) \
    -mtime +"$DIAS" -print -delete | sed 's/^/[respaldo] borrado por antiguo: /'
find "$DESTINO" -maxdepth 1 -name '*.parcial' -mmin +60 -delete

echo "[respaldo] $(date '+%F %T') listo: $(du -h "$BASE" | cut -f1) de base, $(du -h "$ARCHIVOS" | cut -f1) de archivos"

# ── Copiarlos fuera del servidor ───────────────────────────────────
# Desde el servidor, por ejemplo cada semana:
#   docker compose cp respaldo:/respaldos ./respaldos-copia
# y llevar esa carpeta a otro sitio (un Drive del colegio, un disco externo).
