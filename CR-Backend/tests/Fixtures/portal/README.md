# Contrato del portal, copiado para las pruebas

Copia **sin cambios** de lo que publica el repositorio del portal (`Portal Web`):

| Aquí | Allá |
|---|---|
| `api-contract.schema.json` | `docs/api-contract.schema.json` |
| `mock/` | `PW_Frontend/src/api/mock/` |

Versión del contrato: **2.12** (2026-10-09).

- El esquema dice qué forma tiene cada respuesta. Lo genera el portal desde sus propias validaciones, así que no se edita a mano.
- `mock/` son respuestas completas y válidas. `tipico/` es el contenido real del colegio; `corto/`, `largo/`, `sin-foto/`, `alt-vacio/` y `vacio/` solo traen los archivos que cambian, y lo demás se toma de `tipico/`.

Las pruebas (`tests/Feature/Portal*Test.php`) cargan un escenario en la base, piden el endpoint y comprueban dos cosas: que cumple el esquema y que es **idéntica** al mock (orden, `null`, claves). Lo segundo atrapa lo que el esquema no ve.

Cuando el portal publique una versión nueva del contrato, se vuelven a copiar los dos y se corren las pruebas.
