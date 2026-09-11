#!/usr/bin/env bash
#
# Vuelve a poner un respaldo: la base y los archivos de esa misma fecha.
#
#   1. Ver qué respaldos hay:
#        docker compose exec respaldo ls -lh /respaldos
#
#   2. Restaurar uno (PISA lo que hay ahora):
#        docker compose exec -e CONFIRMAR=si respaldo \
#            bash /respaldo/restaurar.sh 2026-09-10_0200
#
# Pide CONFIRMAR=si a propósito: esto borra lo de ahora y deja lo de esa
# fecha, y no debería poder hacerse por equivocación al pegar un comando.
#
# Conviene probarlo de vez en cuando en un equipo aparte: un respaldo que
# nunca se restauró no se sabe si sirve.

set -euo pipefail

FECHA="${1:?Indica qué respaldo, p. ej. 2026-09-10_0200 (los hay en /respaldos)}"
BASE="/respaldos/base_$FECHA.sql.gz"
ARCHIVOS="/respaldos/archivos_$FECHA.tar.gz"

[ -f "$BASE" ]     || { echo "No existe $BASE" >&2; exit 1; }
[ -f "$ARCHIVOS" ] || { echo "No existe $ARCHIVOS" >&2; exit 1; }

if [ "${CONFIRMAR:-}" != "si" ]; then
    echo "Esto REEMPLAZA la base y los archivos de ahora por los del $FECHA." >&2
    echo "Si es lo que quieres, repite el comando con -e CONFIRMAR=si" >&2
    exit 1
fi

CREDENCIALES="$(mktemp)"
chmod 600 "$CREDENCIALES"
trap 'rm -f "$CREDENCIALES"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\n' \
    "${DB_HOST:-base}" "$DB_USERNAME" "$DB_PASSWORD" > "$CREDENCIALES"

# Se comprueban los dos ANTES de tocar nada: mejor enterarse de que un
# archivo está roto con la base de ahora todavía intacta.
gzip -t "$BASE"
gzip -t "$ARCHIVOS"

echo "[restaurar] base del $FECHA…"
gunzip -c "$BASE" | mysql --defaults-extra-file="$CREDENCIALES" "$DB_DATABASE"

echo "[restaurar] archivos del $FECHA…"
find /archivos -mindepth 1 -delete
tar -xzf "$ARCHIVOS" -C /archivos

echo "[restaurar] listo. Reinicia la app para que vacíe su caché:  docker compose restart app cola"
