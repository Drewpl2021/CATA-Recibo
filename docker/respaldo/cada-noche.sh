#!/usr/bin/env bash
#
# Lanza el respaldo todos los días a RESPALDO_HORA (02:00 por defecto, hora
# del colegio: el contenedor corre con TZ=America/Lima).
#
# A las dos de la madrugada nadie está emitiendo boletas ni aplicando
# conceptos, así que la foto sale limpia y no le quita recursos a nadie.
#
# Si un respaldo falla no se cae el contenedor: se deja dicho en el log
# (`docker compose logs respaldo`) y se vuelve a intentar la noche siguiente.

set -uo pipefail

HORA="${RESPALDO_HORA:-02:00}"

echo "[respaldo] programado todos los días a las $HORA ($(date +%Z))"

while true; do
    ahora="$(date +%s)"
    objetivo="$(date -d "today $HORA" +%s)"

    if [ "$objetivo" -le "$ahora" ]; then
        objetivo="$(date -d "tomorrow $HORA" +%s)"
    fi

    echo "[respaldo] el próximo: $(date -d "@$objetivo" '+%F %R')"
    sleep "$((objetivo - ahora))"

    if ! bash /respaldo/respaldar.sh; then
        echo "[respaldo] FALLÓ el respaldo del $(date '+%F %T'). Revisa el log de arriba." >&2
    fi
done
