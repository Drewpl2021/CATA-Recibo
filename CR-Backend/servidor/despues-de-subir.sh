#!/bin/bash
# Después de descomprimir backend-rrhh.zip en /apps/rrhh_backend y de completar
# el .env: revisa PHP y el .env, crea la carpeta de las imágenes del portal,
# ajusta los permisos, corre las migraciones y rehace las cachés.
#
# Uso, desde la Terminal de cPanel:
#   bash ~/apps/rrhh_backend/servidor/despues-de-subir.sh
# Si «php» no es la versión 8.3:
#   PHP=/opt/cpanel/ea-php83/root/usr/bin/php bash ~/apps/rrhh_backend/servidor/despues-de-subir.sh
#
# Se puede correr más de una vez: lo que ya está hecho no se repite.

set -euo pipefail
cd "$(dirname "$0")/.."
PHP="${PHP:-php}"

echo "== Revisando PHP"
if ! "$PHP" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
  echo "ERROR: este php es $("$PHP" -r 'echo PHP_VERSION;'); hace falta 8.3 o más."
  echo "       Busca el de 8.3 con: ls /opt/cpanel/ea-php*/root/usr/bin/php"
  echo "       y vuelve a correr así: PHP=/opt/cpanel/ea-php83/root/usr/bin/php bash $0"
  exit 1
fi
"$PHP" -r 'echo "   PHP ", PHP_VERSION, PHP_EOL;'
if ! "$PHP" -r 'exit(function_exists("imagewebp") ? 0 : 1);'; then
  echo "AVISO: PHP no tiene GD con WebP: el panel no podrá guardar fotos del portal."
  echo "       Actívalo en cPanel › Seleccionar versión de PHP › Extensiones › gd."
fi
if ! "$PHP" -r 'exit(function_exists("exif_read_data") ? 0 : 1);'; then
  echo "AVISO: PHP no tiene exif: las fotos de celular tomadas de costado se verán giradas."
  echo "       Actívala en cPanel › Seleccionar versión de PHP › Extensiones › exif."
fi

echo "== Revisando el código subido"
if [ -d resources/portal/ejemplos/tipico ]; then
  echo "   resources/portal/ejemplos: están (los usa portal:importar en una instalación nueva)"
else
  echo "AVISO: faltan los ejemplos del portal (resources/portal/ejemplos)."
  echo "       El backend no se subió completo: vuelve a extraer backend-rrhh.zip."
fi

echo "== Revisando el .env"
[ -f .env ] || { echo "ERROR: no hay .env en $(pwd)."; exit 1; }
falta=0
for variable in PORTAL_ORIGENES PORTAL_URL_MEDIOS; do
  grep -qE "^${variable}=.+" .env || { echo "ERROR: falta ${variable} en el .env (ver servidor/env-portal.ejemplo)."; falta=1; }
done
[ "$falta" -eq 0 ] || exit 1
grep -qE '^PORTAL_URL_MEDIOS=https://' .env || { echo "ERROR: PORTAL_URL_MEDIOS tiene que empezar con https://."; exit 1; }
grep -qE '^APP_URL=https://' .env || echo "AVISO: APP_URL no empieza con https://."
grep -qE '^APP_DEBUG=(false|0)' .env || echo "AVISO: APP_DEBUG no está en false: en producción mostraría datos internos ante un error."

echo "== Carpetas y permisos"
mkdir -p storage/app/portal
# La carpeta temporal de los PDF (boletas, CTS, gratificaciones): sin ella, en
# el hosting, la boleta salía sin el escudo, sin la marca de agua y sin el QR.
mkdir -p storage/app/dompdf
find storage bootstrap/cache -type d -exec chmod 755 {} \;
find storage bootstrap/cache -type f -exec chmod 644 {} \;
echo "   storage/ y bootstrap/cache/: carpetas 755, archivos 644"
if [ -w storage/app/dompdf ]; then
  echo "   storage/app/dompdf: lista para armar los PDF"
else
  echo "ERROR: PHP no puede escribir en storage/app/dompdf: las boletas saldrían sin escudo."; exit 1
fi

echo "== Migraciones"
"$PHP" artisan migrate --force

echo "== Cachés"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache

echo
echo "Listo. Sigue con la guía (subir angular-rrhh.zip)."
