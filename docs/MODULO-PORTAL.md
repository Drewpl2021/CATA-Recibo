# Módulo Portal

La API pública de solo lectura que lee el portal del colegio (cata.edu.pe), servida por CR-Backend bajo `/api/portal/v1`. Su contrato vive en el repositorio del portal (`Portal Web/docs/API_CONTRACT.md`, v2.12); una copia del esquema y de los ejemplos está en `CR-Backend/tests/Fixtures/portal/`.

## Etapa 1: la API de solo lectura (hecha)

- Los 26 endpoints del contrato 2.12, solo GET y sin token, en `routes/portal.php`.
- 25 tablas `portal_*`. Cada lista tiene su `clave` pública, `orden` y `estado` (publicado o borrador). Las imágenes van en `portal_imagenes`, una fila por uso, también dentro de los JSON (`{"imagenId": …}`).
- `php artisan portal:importar {escenario}` carga un escenario de los ejemplos del contrato. Reemplaza todo el contenido del portal, así que no corre sin `--force` si ya hay contenido o si es producción.
- Pruebas: `tests/Feature/Portal*Test.php`. Cada endpoint, en los seis escenarios, contra el JSON Schema y contra el ejemplo. Pasan en SQLite y en MySQL.

## Pendiente para la etapa 2 (el panel)

1. **La ruta `/api/portal/medios/…`** que sirve las imágenes subidas desde el panel (disco `portal`, nombres uuid, `Cache-Control: public, max-age=31536000, immutable`). Hoy no existe: todo el contenido importado usa imágenes externas, pero la primera imagen subida saldría con una URL que da 404. `Imagen::url()` ya arma esa dirección con `config('portal.url_medios')`.
2. **`APP_URL` en producción debe ser https.** `portal.url_medios` sale de ahí, y el contrato hace que el portal descarte cualquier respuesta con una URL `http://`. Hoy el `.env.example` de la raíz trae una IP con http.
3. **El panel no debe permitir guardar bloques que rompan el contrato** (tipo desconocido, lista sin ítems, carta sin firma, audio en un formato que el portal no reproduce, organigrama de más de 5 niveles). La API los omite y los anota en el registro (`App\Support\Portal\Bloques`), pero eso es la última red, no la validación.
4. **El contrato 2.13** (§7 del contrato): `GET /v1/navegacion`, `GET /v1/portada/cierre`, `etiquetas` en `/v1/solicitud` y `sitio.nombreEnLineas`, para que el menú, los títulos de sección y el cierre de Inicio salgan del panel. `portal_paginas` ya tiene `grupo` y `nombre` para el menú de Nosotros.
5. **Quién edita.** Los modelos ya registran cada cambio en la auditoría; falta decidir si basta el Administrador o si hace falta un rol nuevo (tipo «TIC»), y dar de alta el módulo en el menú.
