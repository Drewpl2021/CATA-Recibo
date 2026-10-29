#!/bin/bash
# Arma backend-rrhh.zip y angular-rrhh.zip para el hosting, a partir de Git.
#
# Uso, en Git Bash, desde la raíz del repositorio:
#   bash despliegue/armar-paquetes.sh                 # el commit actual, al Escritorio
#   bash despliegue/armar-paquetes.sh c71774b ~/Desktop
#
# Por qué existe: copiar la carpeta de trabajo (robocopy, Compress-Archive)
# arrastra lo que hay en la computadora —la base SQLite local, las boletas y
# fotos de prueba de storage/, la caché— y deja fuera lo que vive en carpetas
# excluidas a mano. Este script parte del commit: lleva exactamente lo que está
# en Git, más vendor/ instalado para producción, y nada de la computadora.
#
# Necesita: git, php 8.3+, composer, node/npm (con npm install hecho en
# CR-Fronend) y python.

set -euo pipefail

RAIZ="$(git rev-parse --show-toplevel)"
COMMIT="$(git -C "$RAIZ" rev-parse --short "${1:-HEAD}")"
SALIDA="${2:-$HOME/OneDrive/Desktop}"
[ -d "$SALIDA" ] || SALIDA="$HOME/Desktop"
FECHA="$(date +%F)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "== Paquetes del commit $COMMIT → $SALIDA"

# ── Backend ──────────────────────────────────────────────────────────────
echo "== Backend"
git -C "$RAIZ" archive "$COMMIT" CR-Backend | tar -x -C "$TMP"
B="$TMP/CR-Backend"
( cd "$B" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet )
# Lo que está en Git pero no va al servidor.
rm -rf "$B/tests" "$B/postman" "$B/.claude" "$B/phpunit.xml" "$B/boleta_generada.pdf"
[ -d "$B/resources/portal/ejemplos/tipico" ] || { echo "ERROR: faltan resources/portal/ejemplos en $COMMIT."; exit 1; }
[ -f "$B/servidor/despues-de-subir.sh" ] || { echo "ERROR: falta CR-Backend/servidor/ en $COMMIT."; exit 1; }
sed -e "s/{COMMIT}/$COMMIT/" -e "s/{FECHA}/$FECHA/" "$RAIZ/despliegue/LEEME-backend.txt" | sed 's/$/\r/' > "$B/LEEME.txt"
rm -f "$SALIDA/backend-rrhh.zip"
python "$RAIZ/despliegue/comprimir.py" "$B" "$SALIDA/backend-rrhh.zip"

# ── Angular ──────────────────────────────────────────────────────────────
# Se compila en la carpeta de trabajo (ahí están sus node_modules), así que
# tiene que estar exactamente en ese commit y sin cambios.
echo "== Angular"
if [ "$(git -C "$RAIZ" rev-parse --short HEAD)" != "$COMMIT" ] \
   || ! git -C "$RAIZ" diff --quiet HEAD -- CR-Fronend \
   || [ -n "$(git -C "$RAIZ" status --porcelain -- CR-Fronend)" ]; then
  echo "ERROR: CR-Fronend no está limpio en $COMMIT. Haz commit o «git stash» y vuelve a correrlo."
  exit 1
fi
( cd "$RAIZ/CR-Fronend" && MSYS_NO_PATHCONV=1 npx ng build --base-href /rrhh/ --output-path "$TMP/angular-build" > "$TMP/ng.log" 2>&1 ) \
  || { tail -20 "$TMP/ng.log"; echo "ERROR: no compiló."; exit 1; }
A="$TMP/angular"
mkdir -p "$A"
cp -r "$TMP/angular-build/browser/." "$A/"
cp "$RAIZ/despliegue/rrhh/index.php" "$RAIZ/despliegue/rrhh/.htaccess" "$A/"
sed -e "s/{COMMIT}/$COMMIT/" -e "s/{FECHA}/$FECHA/" "$RAIZ/despliegue/LEEME-angular.txt" | sed 's/$/\r/' > "$A/LEEME.txt"
grep -q '<base href="/rrhh/">' "$A/index.html" || { echo "ERROR: el index.html no tiene base /rrhh/."; exit 1; }
rm -f "$SALIDA/angular-rrhh.zip"
python "$RAIZ/despliegue/comprimir.py" "$A" "$SALIDA/angular-rrhh.zip"

echo
echo "Listo: $SALIDA/backend-rrhh.zip y $SALIDA/angular-rrhh.zip (commit $COMMIT)."
