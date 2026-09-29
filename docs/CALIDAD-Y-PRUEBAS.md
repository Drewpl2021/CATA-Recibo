# Calidad y pruebas — CATA-Recibo

Cómo se evalúa que el sistema está bien hecho, con criterios de la **ISO/IEC 25010**
y con pruebas automáticas que cualquiera puede correr.

## 1. Cómo correr las pruebas

```bash
# Backend (PHPUnit sobre SQLite en memoria: no toca la base real)
cd CR-Backend
php vendor/bin/phpunit

# Frontend (Karma + Jasmine, Chrome sin ventana)
cd CR-Fronend
npm run test:ci
```

Las dos corren también solas en cada push (`.github/workflows/pruebas.yml`).

## 2. Qué se prueba

### Backend — 77 pruebas

| Archivo | Qué garantiza |
|---|---|
| `Unit/MotorDeCalculoTest` | **El motor de planilla**: ONP 13 %, las cuatro AFP con su comisión, prima y fondo sin cruzarse, asignación familiar, EsSalud 9 %, gratificación completa y prorrateada, la renta de 5ta (tramos 8/14/17/20/30 %, proyección mensual, descuento de lo ya retenido y regularización de diciembre), y el **reparto de días hábiles** (2026-09-28: solo lunes a viernes, decisión del colegio; quien trabajó el mes completo sigue cobrando el 100 %, quien entra en fin de semana empieza a contar el lunes). Los valores esperados están calculados a mano según el Art. 40 del reglamento del IR y contra el calendario real. |
| `Unit/ModelosDelDominioTest` | El neto de la planilla (sueldo + bonificaciones − descuentos − adelantos), que las aportaciones del colegio no se restan, la regla de vacaciones solo para indeterminado, que **eliminar el contrato vigente le quita ese derecho de inmediato** (bug encontrado y corregido el 2026-09-27), y la **auditoría**: cambio con antes/después, campos no vigilados sin rastro, contraseña nunca en claro. |
| `Feature/AutenticacionTest` | Login correcto, **los tres rechazos con el mismo mensaje**, cierre de sesiones anteriores, registro público cerrado, rutas protegidas, bloqueo por clave inicial (423) y por términos sin firmar (428), y el **límite de intentos** (429). |
| `Feature/RolesYAccesosTest` | Un trabajador no entra a RR.HH. (403), solo ve su propia planilla, la auditoría es de solo lectura y solo para Admin. |
| `Feature/HistorialDePlanillaTest` | El **trigger** de la base: registra cambios de neto y estado, incluso un UPDATE hecho a mano sin pasar por Laravel, y el rastro sobrevive al borrado. |
| `Feature/VacacionesTruncasTest` | **Vacaciones Truncas** (la vía de plazo fijo, suplencia y prácticas) se rechaza para un contrato indeterminado — que ya cobra sus vacaciones de verdad—, y se acepta para los otros tres tipos. |
| `Feature/RecalcularPlanillaTest` | **`PUT /payrolls/{id}/recalcular`** (2026-09-28): generar una planilla es una foto del sueldo de ese momento; este endpoint vuelve a prorratear con el sueldo actual de la ficha, regenera pensión/EsSalud/Asignación Familiar/Renta de 5ta, no toca las líneas puestas a mano, se bloquea si la corrida está cerrada, y queda anotado en Auditoría con el antes y el después. |

### Frontend — 112 pruebas

Los buscadores de las tablas, de los selectores de casillas (Áreas/Cargos) y
del selector de personas comparten el mismo bug encontrado el 2026-09-28
—sin forma de limpiar salvo borrando letra por letra— y el mismo arreglo:
botón «×» + Escape en dos pasos. Cuatro archivos lo prueban por separado:

| Archivo | Qué garantiza |
|---|---|
| `confirm-dialog.component.spec` | **Coherencia del ícono con la acción** (2026-09-28): "Sacar de esta planilla" y "Recalcular sueldo" no destruyen nada, pero salían con el tacho rojo de eliminar porque el diálogo mostraba ese ícono siempre que no se le pidiera explícitamente `variante:'default'`. Se invirtió la regla: el tacho ahora es solo para quien pide `'danger'` a propósito, así que olvidarse de la variante sale seguro. |
| `data-table.component.spec` | El buscador de toda tabla: el botón «×» solo aparece con algo escrito, limpia y devuelve el foco; Escape limpia primero y en un segundo toque suelta el foco; limpiar vuelve a mostrar todas las filas. |
| `areas-list.component.spec` / `cargos-list.component.spec` | El mismo botón «×» en el buscador de la rejilla de casillas (qué cargos son propios de un área, y viceversa). |
| `selector-empleados.component.spec` | El «×» del campo solo borra el texto, sin tocar los filtros de área/cargo/sede — el botón "Limpiar" de al lado sigue borrando todo junto. |
| `app.routes.spec` | Toda pantalla de RR.HH./Admin dentro de `/inicio` lleva su `canActivate` — incluida **/dashboard**, que hasta el 2026-09-27 no lo tenía (las migas de pan y el logo apuntan ahí para cualquier rol, así que un trabajador llegaba a la pantalla aunque la API le negara los datos). |
| `auth.guard.spec` | Sin sesión → login; clave inicial → cambiarla; sin términos → firmarlos; guard por rol. |
| `auth.interceptor.spec` | Token en cada petición, PUT/PATCH/DELETE como POST, y la reacción a 401 / 423 / 428. |
| `auth.service.spec` | Login, sesión en `localStorage`, rutas según rol, aviso único de sesión caducada. |
| `identificar-documento.spec` | Lectura de PDFs antiguos: tipo, DNI, mes y año, con tildes, "setiembre", diciembre firmado en enero. |
| `documentos.spec` | Nombres, formatos y estado de firma de los documentos. |
| `utilidades.spec` | Validador de clave, fechas sin desfase de zona horaria y **siempre dd/mm/aaaa** (antes el formato lo ponía el navegador de cada quien), mensajes de error de Laravel. |
| `toast-y-confirm.spec` | Avisos, silencio de errores y el resultado masivo (**cero hechas es un error, no un éxito verde**). |
| `app.component.spec` | La aplicación arranca y monta sus piezas. |

## 3. Criterios de calidad (ISO/IEC 25010)

| Característica | Evidencia en el proyecto |
|---|---|
| **Adecuación funcional** | Planilla, boletas, PLAME, vacaciones y firma cubren el proceso real del colegio; el cálculo está verificado con pruebas. |
| **Eficiencia de desempeño** | Índices para expedientes y contratos, listados paginados (`ListadoPaginado`), el panel se responde en una sola petición. |
| **Compatibilidad** | API REST en JSON consumida por Angular; exporta a Excel y PDF. |
| **Usabilidad** | Guía de primer día, componentes compartidos, temas claro y oscuro, mensajes en lenguaje de RR.HH. |
| **Fiabilidad** | Transacciones en operaciones en lote; la auditoría nunca tumba una operación; respaldos de la base. |
| **Seguridad** | Sanctum con vencimiento por inactividad, límite de intentos, mensaje único de rechazo, roles por middleware, términos firmados, contraseñas con hash. Ver [seguridad](../CR-Backend/README.md). |
| **Mantenibilidad** | Capas y traits; un solo lugar para cada nombre de concepto; comentarios que explican el porqué. |
| **Portabilidad** | `docker compose up` levanta todo; pruebas sobre SQLite y despliegue en MySQL. |

## 4. La base de datos

- **Modelo entidad-relación** generado desde las migraciones: [MODELO-DE-DATOS.md](MODELO-DE-DATOS.md).
- **Trigger** `trg_planilla_historial` (`AFTER UPDATE` sobre `planilla`): guarda el total y el
  estado anteriores en `planilla_historial` aunque el cambio no pase por la aplicación.
- **Procedimiento almacenado** `sp_resumen_planilla(anio, mes)` (MySQL): cantidad de boletas,
  suma, mínimo, máximo y promedio del mes.

  ```sql
  CALL sp_resumen_planilla(2026, 3);
  ```
- Respaldo y recuperación: `mysqldump colegio_db > respaldo.sql` y
  `mysql colegio_db < respaldo.sql`; hay copias en `colegio_db_backup.sql`.

## 5. Lo que no se prueba todavía

Para no dar una idea falsa de la cobertura:

- Los controladores de importación de Excel y la generación de PDF de boletas no tienen prueba propia.
- El frontend prueba lógica, servicios y guards, pero casi ningún componente visual.
- El procedimiento almacenado solo existe en MySQL y no lo cubre la suite (que corre en SQLite).
