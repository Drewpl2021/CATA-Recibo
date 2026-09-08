# ─────────────────────────────────────────────────────────────
#  CATA-Recibo — el portero (Angular ya compilado + nginx)
# ─────────────────────────────────────────────────────────────
#  Node solo aparece aquí, para compilar. La imagen que acaba
#  corriendo en el VPS es nginx con archivos estáticos dentro: ni
#  Node ni node_modules viajan al servidor.
# ─────────────────────────────────────────────────────────────

# ── Compilar la aplicación de Angular ─────────────────────────
FROM node:22-alpine AS compilacion

WORKDIR /app

# npm ci (y no npm install) instala EXACTAMENTE lo que dice
# package-lock.json. Es la diferencia entre "me funciona en mi
# máquina" y que el servidor corra lo mismo.
# El .npmrc va con ellos: lleva el legacy-peer-deps que necesita
# ng-apexcharts, y sin él `npm ci` se planta con ERESOLVE.
COPY CR-Fronend/package.json CR-Fronend/package-lock.json CR-Fronend/.npmrc ./
RUN npm ci

COPY CR-Fronend/ ./
RUN npm run build -- --configuration production

# ── La imagen final ───────────────────────────────────────────
FROM nginx:1.27-alpine AS servidor

# Lo que compiló Angular.
COPY --from=compilacion /app/dist/turecibo-app/browser /usr/share/nginx/html

# El public/ de Laravel: nginx necesita ver index.php en la misma
# ruta que php-fpm para poder pasarle las peticiones del API.
COPY CR-Backend/public /var/www/html/public

COPY docker/web/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
