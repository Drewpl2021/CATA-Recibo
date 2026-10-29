# Contrato del portal: esquema y ejemplos

Copia **sin cambios** de lo que publica el repositorio del portal (`Portal Web`):

| Aquí | Allá |
|---|---|
| `api-contract.schema.json` | `docs/api-contract.schema.json` |
| `ejemplos/` | `PW_Frontend/src/api/mock/` |

Los dos viven en `resources/` y no en `tests/` porque también los usa el servidor de producción: el esquema, para que el panel no guarde nada que rompa el contrato; los ejemplos, para `php artisan portal:importar`, que carga el contenido inicial de una instalación nueva. El paquete de producción no lleva `tests/`: si estuvieran ahí, el comando no los encontraría.

Versión del contrato: **2.13** (2026-10-12).

- El esquema dice qué forma tiene cada respuesta. Lo genera el portal desde sus propias validaciones, así que no se edita a mano.
- `ejemplos/` son respuestas completas y válidas. `tipico/` es el contenido real del colegio; `corto/`, `largo/`, `sin-foto/`, `alt-vacio/` y `vacio/` solo traen los archivos que cambian, y lo demás se toma de `tipico/`.

Las pruebas (`tests/Feature/Portal*Test.php`) cargan un escenario en la base, piden el endpoint y comprueban dos cosas: que cumple el esquema y que es **idéntica** al mock (orden, `null`, claves). Lo segundo atrapa lo que el esquema no ve.

Cuando el portal publique una versión nueva del contrato, se vuelven a copiar los dos y se corren las pruebas.
