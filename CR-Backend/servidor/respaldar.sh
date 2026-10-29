#!/bin/bash
# Respaldo de CATA-Recibo: la base de datos y los archivos (el .env y storage/app,
# donde están las boletas, los documentos y las imágenes del portal). Se guardan
# en ~/respaldos y se borran solos los de más de 60 días.
#
# Las imágenes del portal se respaldan JUNTO con la base: una sin la otra no
# sirve (la base dice qué foto va en cada lugar).
#
# A mano:      bash ~/apps/rrhh_backend/servidor/respaldar.sh
# Con el cron: ver servidor/crons.txt (una vez por semana).
# Descarga de vez en cuando los respaldos a otra computadora: un respaldo que
# solo está en el mismo servidor no sirve si el servidor falla.

set -euo pipefail
cd "$(dirname "$0")/.."

# Lee una variable del .env (sin comillas).
valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }

DESTINO="${HOME}/respaldos"
FECHA="$(date +%F-%H%M)"
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

echo "== Base de datos"
HOST="$(valor DB_HOST || true)"
PUERTO="$(valor DB_PORT || true)"
MYSQL_PWD="$(valor DB_PASSWORD || true)" mysqldump \
  -h "${HOST:-localhost}" -P "${PUERTO:-3306}" \
  -u "$(valor DB_USERNAME)" \
  --single-transaction --routines --no-tablespaces \
  "$(valor DB_DATABASE)" | gzip > "$DESTINO/bd-$FECHA.sql.gz"
echo "   $DESTINO/bd-$FECHA.sql.gz"

echo "== Archivos (.env y storage/app)"
tar -czf "$DESTINO/archivos-$FECHA.tar.gz" .env storage/app
echo "   $DESTINO/archivos-$FECHA.tar.gz"

# Los de más de 60 días.
find "$DESTINO" -maxdepth 1 \( -name 'bd-*.sql.gz' -o -name 'archivos-*.tar.gz' \) -mtime +60 -delete

ls -lh "$DESTINO" | tail -n 6
