# CATA-Recibo

[![Integración continua](https://github.com/Drewpl2021/CATA-Recibo/actions/workflows/ci.yml/badge.svg)](https://github.com/Drewpl2021/CATA-Recibo/actions/workflows/ci.yml)

Sistema web de planillas y boletas de pago con firma digital para el
Colegio Adventista Túpac Amaru (Juliaca).

| Pieza | Tecnología | Carpeta |
|---|---|---|
| API | Laravel 13 · PHP 8.3 | `CR-Backend/` |
| Interfaz | Angular 19 | `CR-Fronend/` |
| Base de datos | MySQL 8.4 | migraciones en `CR-Backend/database/` |
| Despliegue | Docker Compose (6 servicios) | `compose.yaml`, `docker/` |
| Documentación | UML, modelo de datos, calidad, seguridad | `docs/` |

## Integración y entrega continua

Cada cambio dispara `.github/workflows/ci.yml`:

1. **Backend · PHPUnit (SQLite)** — las pruebas del API.
2. **Backend · PHPUnit (MySQL 8.4)** — las mismas pruebas contra la base real.
3. **Interfaz** — pruebas con Karma y compilación de producción.
4. **Calidad y seguridad** — sintaxis PHP, `composer validate`, `composer audit`, `npm audit`.
5. **Docker** — construye las imágenes, levanta el sistema y comprueba que responde.

Al etiquetar una versión (`v1.0.0`), `.github/workflows/release.yml` publica
las imágenes `cata-recibo-app` y `cata-recibo-web` en GHCR.

## Cómo correr las pruebas

```bash
cd CR-Backend && php vendor/bin/phpunit      # API
cd CR-Fronend && npm run test:ci             # interfaz
```

## Instalar

Ver [DESPLIEGUE.md](DESPLIEGUE.md) (con Docker) o
[INSTALACION-SIN-DOCKER.md](INSTALACION-SIN-DOCKER.md).
