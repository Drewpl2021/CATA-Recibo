# Módulo Portal

La API pública de solo lectura que lee el portal del colegio (cata.edu.pe), servida por CR-Backend bajo `/api/portal`. Su contrato vive en el repositorio del portal (`Portal Web/docs/API_CONTRACT.md`, v2.12); una copia del esquema y de los ejemplos está en `CR-Backend/tests/Fixtures/portal/`.

## Etapa 1: la API de solo lectura (hecha)

- Los 26 endpoints del contrato 2.12 bajo `/api/portal/v1`, solo GET y sin token, en `routes/portal.php`.
- 25 tablas `portal_*`. Cada lista tiene su `clave` pública, `orden` y `estado` (publicado o borrador). Las imágenes van en `portal_imagenes`, una fila por uso, también dentro de los JSON (`{"imagenId": …}`).
- `php artisan portal:importar {escenario}` carga un escenario de los ejemplos del contrato. Reemplaza todo el contenido del portal, así que no corre sin `--force` si ya hay contenido o si es producción.
- Pruebas: `tests/Feature/Portal*Test.php`. Cada endpoint, en los seis escenarios, contra el JSON Schema y contra el ejemplo. Pasan en SQLite y en MySQL.
- Probada de punta a punta con el portal React (`VITE_API_URL`): todas las páginas, la API apagada y el escenario `vacio`.

### Imágenes subidas: `GET /api/portal/medios/{archivo}`

Sirve las imágenes que se suban desde el panel (disco `portal`, en `storage/app/portal`). Es la dirección que arma `Imagen::url()` para las que no son externas: `config('portal.url_medios')`, que por defecto es `APP_URL` + `/api/portal/medios`.

- `Cache-Control: public, max-age=31536000, immutable`: cada subida tiene su propio nombre y no se reutiliza.
- Se sirve desde el mismo dominio que RR.HH., por eso solo JPEG, PNG, WebP y GIF (nada de SVG, que puede llevar JavaScript), con el tipo tomado de la extensión, `nosniff` y una CSP `sandbox`.
- Solo los archivos registrados en `portal_imagenes`, con nombre plano: `[a-z0-9][a-z0-9_-]*.(jpg|jpeg|png|webp|gif)`. Sin carpetas ni `../`.
- Tiene su propio freno (`PORTAL_LIMITE_MEDIOS_POR_MINUTO`, 600), aparte del de la API.

## Etapa 2: el panel «Portal Web» (en curso, rama `feature/portal-panel`)

Regla de toda la etapa: **solo suma**. No cambia colores, estilos globales, pantallas, componentes compartidos ni el comportamiento de ningún módulo existente; reutiliza los que hay. Si algo existente necesitara cambiar, se pregunta antes.

### Etapa 0: la base (hecha)

- **Una sola puerta:** `Gate::define('editar-portal')` en `AppServiceProvider`, hoy solo el Administrador. En Angular, `soloAdmin` sobre `/inicio/portal-web`. Si otro rol tiene que editar, se cambia ahí y en ningún otro lado.
- **Rutas:** `routes/portal-panel.php`, bajo `/api/web-portal`, con la misma cadena que el sistema protegido (sesión, contraseña al día, términos) más `can:editar-portal`. No van bajo `/api/portal`: ese prefijo tiene el CORS y los errores del portal público. Responden como el sistema (`{ success, data }`).
- **Controlador base** (`Portal\Panel\ListaDelPortalController`): ver, crear (al final y en borrador), editar, ordenar (la lista entera), publicar o pasar a borrador, y eliminar. Cada lista solo declara su modelo, sus reglas y qué respuestas del portal cambian.
- **Validación de salida** (`ValidadorDeContrato`): cada cambio va en una transacción que solo se confirma si las respuestas públicas afectadas, armadas por sus propios controladores, siguen cumpliendo el JSON Schema del contrato (`resources/portal/api-contract.schema.json`). Si no, se deshace y responde 422 con el porqué. Por eso `opis/json-schema` pasó a dependencia de producción.
- **Menú:** grupo «Portal Web» › «Contenido del portal» (`/portal-web`), al final del menú y solo para Admin. Migración `agregar_modulo_portal_web` para las bases instaladas; `ModuloSeeder` para las nuevas.
- **Pantalla índice** (`features/portal-web`): las ocho categorías (General, Inicio, Proyectos, Nosotros, Propuesta Educativa, Matrícula, Logros, Contacto), con lo que se edita en cada una y cómo está hoy (`GET /web-portal/summary`). Usa las clases de Ajustes del sistema; sin estilos propios.

## Pendiente para la etapa 2 (el panel)

1. **Subir imágenes.** La ruta para servirlas ya está; falta la subida. Al subir: comprobar que el contenido sea de verdad una imagen (no basta la extensión), re-codificarla para quitar los datos de ubicación (EXIF), guardarla con un nombre nuevo en minúsculas (por ejemplo, uuid7 + extensión), registrar `ancho` y `alto`, y exigir el `alt`.
2. **`APP_URL` en producción debe ser https** (o `PORTAL_URL_MEDIOS` apuntar a una dirección https). El contrato hace que el portal descarte cualquier respuesta con una URL `http://`. Hoy el `.env.example` de la raíz trae una IP con http.
3. **El panel no debe permitir guardar bloques que rompan el contrato** (tipo desconocido, lista sin ítems, carta sin firma, audio en un formato que el portal no reproduce, organigrama de más de 5 niveles). La API los omite y los anota en el registro (`App\Support\Portal\Bloques`), pero eso es la última red, no la validación.
4. **El contrato 2.13** (§7 del contrato): `GET /v1/navegacion`, `GET /v1/portada/cierre`, `etiquetas` en `/v1/solicitud` y `sitio.nombreEnLineas`, para que el menú, los títulos de sección y el cierre de Inicio salgan del panel. `portal_paginas` ya tiene `grupo` y `nombre` para el menú de Nosotros.
5. **Quién edita.** Los modelos ya registran cada cambio en la auditoría; falta decidir si basta el Administrador o si hace falta un rol nuevo (tipo «TIC»), y dar de alta el módulo en el menú.
