# Sprints y evidencias — CATA-Recibo

Cada sprint del [plan de prácticas](PLAN-DE-PRACTICAS.md#81-plan-de-sprints) con la
evidencia que lo respalda: **commits reales del repositorio**, archivos y pruebas.

> **Cómo se asignó cada commit a un sprint:** por su tema, no por su fecha. El
> historial del repo tiene dos tandas de trabajo (mayo–julio y septiembre 2026) y
> los sprints del plan son de dos semanas, así que las fechas de abajo son las de
> los commits y no coinciden exactamente con las 20 semanas planificadas.
> Para verificar cualquier fila: `git log --oneline --date=short --grep="palabra"`.

Tablero: Trello (una lista por sprint, una tarjeta por historia). Repositorio:
GitHub, rama de trabajo `PastorDev`.

---

## Resumen

| Sprint | Objetivo | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Levantamiento y preparación | ✅ | Plan de prácticas, migraciones iniciales |
| 1 | Personal y contratos | ✅ | CRUD de empleados, contratos, importación de padrón |
| 2 | Planilla del mes | ✅ | Motor de cálculo + 20 pruebas, importación de conceptos |
| 3 | Boletas | ✅ | PDF, correo, emisión masiva |
| 4 | Autoservicio y firma | ✅ | Firma dibujada, límite de intentos |
| 5 | Expediente digital | ✅ | Documentos del personal, anteriores en lote |
| 6 | Seguridad y auditoría | ✅ | OWASP, auditoría, trigger, pruebas de acceso |
| 7 | Reportes y panel | ✅ | Dashboard, exportaciones a Excel |
| 8 | Despliegue | ✅ | Docker Compose, respaldos, `DESPLIEGUE.md` |
| 9 | Capacitación y estabilización | 🟡 | Guía del primer día, Postman 564/564 |
| 10 | Cierre | 🟡 | Modelo de datos, UML, calidad y pruebas |

🟡 = en curso. Marquen como ✅ solo lo que ya tengan entregado y conforme.

---

## Sprint 0 — Levantamiento y preparación
**Objetivo:** proyecto base, ambiente y backlog.

| Fecha | Commit |
|---|---|
| 2026-05-03 | primer commit - proyecto Laravel base |
| 2026-05-03 | migraciones de empleados, vacaciones, planilla y documentos |
| 2026-05-03 | modelos, controllers y rutas API |
| 2026-09-22 | El plan de practicas preprofesionales |

**Evidencia:** `docs/PLAN-DE-PRACTICAS.md`, `CR-Backend/database/migrations/`.

## Sprint 1 — Personal y contratos
| Fecha | Commit |
|---|---|
| 2026-05-11 | CRUD completo + filtros + validacion vacaciones + PDF boleta + logo |
| 2026-06-15 | validaciones de planilla duplicada, DNI 8 digitos, CUSPP 11 caracteres |
| 2026-06-20 | Agregar Sedes, campos de contrato a empleados, sistema de modulos por rol |
| 2026-07-06 | ampliar tipos de contrato… y crear modulo de contratos |
| 2026-09-14 | Importar empleados y conceptos desde Excel, hoja de vida y modelos descargables |

**Evidencia:** `EmpleadoController`, `ContratoController`, `ImportacionEmpleadosController`;
regla de vacaciones probada en `Unit/ModelosDelDominioTest`.

## Sprint 2 — Planilla del mes
| Fecha | Commit |
|---|---|
| 2026-06-14 | calculo automatico AFP/ONP, asignacion familiar, gratificacion, ESSALUD |
| 2026-06-30 | implementar cálculo de 5ta categoría |
| 2026-07-03 | gratificacion proporcional por fecha de ingreso |
| 2026-07-07 | modulo Planillas - lista y formulario de creacion/edicion |
| 2026-09-10 | corridas de planilla, consulta de DNI y firma de terminos de uso |

**Evidencia:** `Traits/CalculaConceptosPlanilla.php`, `Traits/GeneraPlanillasEnLote.php`;
**`tests/Unit/MotorDeCalculoTest.php` (20 pruebas)**.

## Sprint 3 — Boletas
| Fecha | Commit |
|---|---|
| 2026-06-04 | vista de emision de boletas para administrador |
| 2026-06-26 | firma de empleados en boleta PDF |
| 2026-07-01 | correo automatico al generar boleta individual y masiva |
| 2026-09-25 | La boleta sale de la planilla, y los papeles del trabajador son suyos |

**Evidencia:** `BoletaController`, `MiBoletaController`, `Mail/`.

## Sprint 4 — Autoservicio y firma
| Fecha | Commit |
|---|---|
| 2026-06-28 | Firma de boleta implementada y menu actualizado |
| 2026-07-03 | limite de 3 intentos al firmar documentos |
| 2026-09-18 | Firma dibujada en pantalla, con el recuadro de la boleta |
| 2026-09-20 | Primero firmar, despues descargar |

**Evidencia:** `IdentidadFirmaController`, componente `lienzo-firma`.

## Sprint 5 — Expediente digital
| Fecha | Commit |
|---|---|
| 2026-09-15 | Documentos del personal, cabeceras de modal, buscadores |
| 2026-09-17 | Boletas y contratos anteriores en lote, trabajadores cesados |

**Evidencia:** `ExpedienteController`, `DocumentosAnterioresController`;
`identificar-documento.spec.ts` y `documentos.spec.ts` (frontend).

## Sprint 6 — Seguridad y auditoría
| Fecha | Commit |
|---|---|
| 2026-06-23 | Accesos conectados y también el CRUD de Usuario |
| 2026-09-11 | Avanzes de seguridad |
| 2026-09-14 | Seguridad OWASP en autenticacion, vacaciones por contrato y saneado de datos |
| 2026-09-23 | La planilla apunta a su trabajador, y la base lo vigila |
| 2026-09-25 | Historial de planilla con trigger *(pendiente de commit)* |

**Evidencia:** `Traits/Auditable.php`, migración `historial_de_planilla_con_trigger`,
`AutenticacionTest`, `RolesYAccesosTest`, `HistorialDePlanillaTest`.

## Sprint 7 — Reportes y panel
| Fecha | Commit |
|---|---|
| 2026-07-01 | ADMIN/RRHH dashboard principal agregado |
| 2026-09-15 | Rendimiento, manejo de errores sin fugas, exportables en Excel |
| 2026-09-22 | Filtros en las tablas y el panel en Excel |
| 2026-09-24 | Íconos al día, exportaciones con filtros |

**Evidencia:** `DashboardController` (ApexCharts en el frontend).

## Sprint 8 — Despliegue
| Fecha | Commit |
|---|---|
| 2026-09-08 | PastorDev: despliegue con Docker y ajustes varios |
| 2026-09-22 | Una instalacion nueva nace limpia · La semilla de los catalogos, en un .sql versionado |
| 2026-09-25 | Sembrar sin tablas: se avisa en vez de reventar |

**Evidencia:** `compose.yaml`, `DESPLIEGUE.md`, `INSTALACION.md`, `colegio_db_backup.sql`.

## Sprint 9 — Capacitación y estabilización
| Fecha | Commit |
|---|---|
| 2026-09-23 | La coleccion de Postman, en verde: 564 de 564 |
| 2026-09-24 | la guía del primer día |

**Evidencia:** `PrimerosPasosController`, colección Postman en `CR-Backend/postman/`.

## Sprint 10 — Cierre
| Fecha | Commit |
|---|---|
| 2026-09-22 | El modelo de datos, sacado de las migraciones |
| 2026-09-23 | La estructura de la base, en un .sql para Workbench |
| 2026-09-25 | Pruebas, UML y calidad *(pendiente de commit)* |

**Evidencia:** `docs/MODELO-DE-DATOS.md`, `docs/ARQUITECTURA-Y-UML.md`,
`docs/CALIDAD-Y-PRUEBAS.md`, `.github/workflows/pruebas.yml`.

---

## Evidencia de las pruebas

```bash
cd CR-Backend && php vendor/bin/phpunit     # 60 pruebas, 145 aserciones
cd CR-Fronend && npm run test:ci             # 86 pruebas
```

Guarden **una captura de cada salida** y agréguenla al informe. Cuando el CI de
GitHub corra, la pestaña *Actions* también sirve como evidencia.

---

## Tarjetas para Trello

Trello crea **una tarjeta por línea** si pegan varias líneas en "Añadir tarjeta".
Copien cada bloque en la lista de su sprint.

**Sprint 6 — Seguridad y auditoría**
```
Historia: como Admin quiero ver quién cambió qué en la planilla
Trigger de historial de planilla en la base
Pruebas de autenticación y roles
Límite de intentos en el login
```

**Sprint 10 — Cierre**
```
Pruebas automáticas del motor de planilla (backend)
Pruebas de guards, interceptor y servicios (frontend)
Diagramas UML y de arquitectura
Documento de calidad ISO 25010
Integración continua en GitHub Actions
Capturas de evidencia de las pruebas
Informe final de prácticas
```

**Pendientes reales (no cubiertos)**
```
Pruebas de la importación de Excel
Pruebas del PDF de boletas
Pruebas de componentes visuales
Verificar el procedimiento almacenado en MySQL
```
