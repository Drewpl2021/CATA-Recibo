# PLAN DE PRÁCTICAS PREPROFESIONALES

**Universidad Peruana Unión — Facultad de Ingeniería y Arquitectura — Escuela Profesional de Sistemas**

| | |
|---|---|
| **Lugar** | Colegio Adventista "Túpac Amaru" — Juliaca, Puno |
| **Área** | Software |
| **Línea de carrera** | Desarrollo de Software |
| **Practicante** | Alessandro Pastor Mamani Mamani |
| **Proyecto** | CATA-Recibo — Sistema de planillas y boletas de pago |
| **Periodo** | `[fecha de inicio]` – `[fecha de término]` · `[N.º de horas]` horas |
| **Supervisor en el centro** | `[nombre y cargo]` |
| **Docente asesor UPeU** | `[nombre]` |
| **Fecha de emisión** | Septiembre 2026 |

---

## 1. INTRODUCCIÓN

El Colegio Adventista "Túpac Amaru" es una institución educativa privada de
Juliaca que opera en cuatro locales —CATA Central, CATA Jerusalén, CATA Osis y
CATA Inicial— con personal docente, administrativo y de servicio distribuido
entre ellos.

El pago mensual de ese personal se venía llevando en hojas de cálculo: un
archivo por mes con los cálculos de sueldo, aportes y descuentos, y un segundo
archivo del que salían las boletas para imprimir. Cada boleta se imprimía por
duplicado, se entregaba en mano, se firmaba en papel y una copia volvía al
archivador de Recursos Humanos. El proceso funciona, pero descansa por
completo en la memoria de una persona y en archivos que se copian entre
computadoras, sin trazabilidad de quién cambió qué ni respaldo confiable.

**CATA-Recibo** es el sistema web que reemplaza ese proceso: registra al
personal y sus contratos, genera la planilla del mes aplicando las reglas
laborales peruanas, emite las boletas en PDF, las pone a disposición del
trabajador para que las revise y las firme electrónicamente, y conserva el
expediente digital de cada persona. El sistema está construido sobre Laravel
(API), Angular (interfaz) y MySQL, empaquetado en contenedores Docker para que
su instalación no dependa de lo que haya instalado en cada máquina.

Las presentes prácticas preprofesionales se desarrollan sobre ese sistema: su
completamiento funcional, su aseguramiento de calidad, su puesta en producción
en el colegio y la capacitación del personal que va a usarlo.

### 1.1. Situación del proyecto a la fecha

El trabajo de construcción se inició en mayo de 2026. Al momento de emitir
este plan, el repositorio registra:

| Indicador | Valor |
|---|---|
| Commits registrados | 83 |
| Módulos funcionales en el menú | 20 |
| Pantallas (rutas del frontend) | 43 |
| Componentes de interfaz | 53 |
| Endpoints del API | 81 |
| Tablas en la base de datos | 30 |
| Migraciones versionadas | 35 |
| Aserciones de prueba automatizadas | 557 |
| Líneas de código propias | ≈ 52 800 |
| Servicios en el despliegue con Docker | 6 |

---

## 2. OBJETIVO

Implantar el sistema CATA-Recibo en el Colegio Adventista "Túpac Amaru",
dejándolo operativo para el proceso mensual de planillas y boletas de pago del
personal de las cuatro sedes, con el personal capacitado para usarlo de forma
autónoma y con la documentación que permita mantenerlo después de concluidas
las prácticas.

### 2.1. Objetivos específicos

1. **Completar los módulos pendientes** del sistema (firma electrónica del
   trabajador, expediente digital, auditoría de cambios y reportes
   gerenciales), aplicando la metodología Scrum en sprints de dos semanas.
2. **Asegurar el cumplimiento de la normativa laboral peruana** en el cálculo
   de la planilla: aportes, retenciones, asignación familiar, gratificaciones,
   CTS y renta de quinta categoría, verificando cada regla contra el proceso
   real del colegio.
3. **Garantizar la calidad del software** mediante una suite de pruebas
   automatizadas sobre el API, ampliando la cobertura existente y ejecutándola
   antes de cada entrega.
4. **Desplegar el sistema en producción** sobre contenedores Docker en el
   servidor del colegio, con respaldo automático diario de la base de datos y
   de los documentos emitidos.
5. **Migrar la información histórica**: el padrón de trabajadores, los
   contratos vigentes y los documentos anteriores al sistema (boletas y
   contratos en PDF de periodos previos).
6. **Capacitar al personal usuario** —Recursos Humanos, Dirección y
   trabajadores— y entregar el manual de uso correspondiente a cada rol.
7. **Documentar el sistema** para su mantenimiento: instalación, despliegue,
   estructura de la base de datos y decisiones de diseño.
8. **Elaborar el plan de firma digital** con DNI electrónico para la
   aprobación de las boletas por la instancia directiva, evaluando su
   viabilidad técnica y económica.

---

## 3. ALCANCE

### 3.1. Alcance funcional — qué comprende

| Módulo | Comprende |
|---|---|
| **Personal** | Ficha completa del trabajador (datos personales, laborales, de planilla y bancarios), alta individual y por importación masiva desde Excel, baja y reactivación |
| **Contratos** | Registro del vínculo laboral por tipo (indeterminado, plazo fijo, suplencia, prácticas), contrato vigente único por trabajador, alerta de vencimientos |
| **Planilla** | Generación masiva por periodo, aplicación de conceptos por grupo, importación de conceptos desde Excel, cálculo de aportes y retenciones |
| **Boletas** | Emisión individual y masiva, generación del PDF, entrega al trabajador, control de estado (emitida, vista, firmada) |
| **Firma electrónica** | Firma del trabajador mediante trazo en pantalla más contraseña; registro de fecha, hora y código de verificación |
| **Expediente digital** | Hoja de vida, contratos, boletas y documentos anteriores al sistema, por trabajador |
| **Vacaciones** | Solicitud por el trabajador, aprobación por RR.HH., saldo de días |
| **Seguridad** | Usuarios, roles, permisos por módulo, control de sesión y auditoría de cambios |
| **Reportes** | Panel de control con indicadores del mes y exportación a Excel del personal, la planilla y el panel |

### 3.2. Alcance organizacional

- **Sedes**: las cuatro (CATA Central, CATA Jerusalén, CATA Osis, CATA Inicial).
- **Usuarios**: Recursos Humanos, Dirección y la totalidad del personal en
  planilla, cada uno con acceso a lo que le corresponde.

### 3.3. Qué NO comprende

Se deja constancia expresa para evitar expectativas fuera de lo acordado:

- Contabilidad general, tesorería, cobranza de pensiones y matrícula de
  alumnos.
- Control de asistencia por marcación biométrica.
- Envío automático de la declaración PLAME a SUNAT. El sistema **prepara** la
  información en el formato exigido; la declaración la presenta el colegio.
- Firma digital con certificado emitido por una Entidad de Certificación
  acreditada. Queda como **plan evaluado** (objetivo específico 8), sujeto a la
  decisión y el presupuesto de la institución.
- Aplicación móvil nativa. El sistema es web y responde en el navegador del
  teléfono.

---

## 4. NORMATIVIDAD / PROCESOS

### 4.1. Normativa laboral aplicable al cálculo

El sistema implementa las reglas vigentes en el régimen laboral de la
actividad privada:

| Norma | Qué regula en el sistema |
|---|---|
| **D.S. 001-98-TR** y modificatorias | Planillas de pago y boletas: contenido obligatorio de la boleta y entrega al trabajador dentro de los tres días hábiles siguientes al pago |
| **D.L. 19990** | Sistema Nacional de Pensiones (ONP): retención del 13 % |
| **D.L. 25897** | Sistema Privado de Pensiones (AFP): aporte obligatorio, prima de seguro y comisión (sobre flujo o mixta) |
| **Ley 26790** | EsSalud: aporte del 9 % a cargo del empleador, que no se descuenta al trabajador |
| **Ley 25129** y su reglamento | Asignación familiar: 10 % de la RMV para quien tiene hijos menores o cursando estudios superiores |
| **Ley 27735** | Gratificaciones de Fiestas Patrias y Navidad |
| **Ley 30334** | Bonificación extraordinaria del 9 % sobre la gratificación, inafecta |
| **D.S. 001-97-TR** | Compensación por Tiempo de Servicios (CTS) |
| **TUO de la Ley del Impuesto a la Renta** | Retención de renta de quinta categoría |
| **D.L. 713** | Descanso vacacional y vacaciones truncas |
| **R.M. 121-2011-TR y normas SUNAT** | T-Registro y PLAME: estructura de la información que se declara |

### 4.2. Normativa sobre datos y firma

| Norma | Implicancia |
|---|---|
| **Ley 29733 — Protección de Datos Personales** y su reglamento (D.S. 003-2013-JUS) | Una planilla contiene datos personales y datos sensibles (remuneración, cuentas bancarias, salud). Obliga a limitar el acceso, resguardar la información y conservarla solo mientras sea necesaria |
| **Ley 27269 — Firmas y Certificados Digitales** y D.S. 052-2008-PCM | Marco de la firma electrónica; distingue la firma electrónica simple (la que hoy implementa el sistema) de la firma digital con certificado acreditado (el plan a evaluar) |

### 4.3. Procesos del colegio que el sistema soporta

1. **Alta de trabajador** → ficha, contrato y creación de su cuenta de acceso.
2. **Cierre de mes** → generación de la planilla del periodo, aplicación de
   conceptos particulares, revisión y cierre.
3. **Emisión de boletas** → generación masiva, notificación al trabajador.
4. **Revisión y firma** → el trabajador abre su boleta, la revisa y la firma;
   recién entonces puede descargarla.
5. **Vacaciones** → solicitud, aprobación y descuento del saldo.
6. **Cese** → baja del trabajador, cálculo de truncos y cierre del expediente.

---

## 5. JUSTIFICACIÓN

**Operativa.** El armado manual de la planilla y la impresión, entrega, firma
y archivo de las boletas consume varios días de trabajo de Recursos Humanos
cada mes. El sistema reduce ese trabajo a la revisión de lo que el cálculo
propone y a una emisión masiva.

**Legal.** La boleta debe entregarse dentro de los tres días hábiles
siguientes al pago y el empleador debe poder acreditar esa entrega. Con la
entrega en papel, la constancia es una firma en una copia archivada que puede
perderse; con el sistema, cada boleta guarda la fecha en que se emitió, en que
el trabajador la vio y en que la firmó.

**De integridad de la información.** Una hoja de cálculo copiada entre
computadoras no registra quién cambió un sueldo ni cuándo. El sistema deja
registro de cada cambio sobre los datos sensibles (sueldos, cuentas, conceptos
y planillas), en una bitácora que solo se lee y no se puede editar.

**De continuidad.** El proceso actual depende de archivos guardados en una
computadora. El sistema centraliza la información en una base de datos con
respaldo automático diario de la base y de los documentos emitidos.

**Académica y profesional.** Las prácticas permiten aplicar en un entorno real
el ciclo completo de desarrollo de software —levantamiento de requerimientos,
análisis, diseño, construcción, pruebas, despliegue, capacitación y
mantenimiento— sobre un sistema en producción con usuarios reales y con
exigencias legales que no admiten aproximaciones.

---

## 6. METODOLOGÍA DE TRABAJO

### 6.1. Marco de trabajo: Scrum adaptado

Se trabaja con **Scrum**, adaptado a un equipo de desarrollo de una sola
persona. La adaptación se declara explícitamente porque Scrum está pensado
para equipos: aquí el practicante asume las responsabilidades de desarrollo y
de facilitación, y el rol de Product Owner lo ejerce el área usuaria.

| Rol Scrum | Quién lo ejerce | Responsabilidad |
|---|---|---|
| **Product Owner** | Jefatura de Recursos Humanos del colegio | Prioriza el Product Backlog, valida el incremento, decide qué entra en cada sprint |
| **Scrum Master** | El practicante | Facilita las ceremonias, retira impedimentos, cuida que el proceso se cumpla |
| **Equipo de Desarrollo** | El practicante | Analiza, diseña, construye, prueba, documenta y despliega |
| **Interesados** | Dirección, Consejo Directivo y personal del colegio | Retroalimentan en la revisión de sprint |

### 6.2. Sprints y ceremonias

Sprints de **dos semanas**. Las ceremonias se adaptan en duración, no se
eliminan:

| Ceremonia | Cuándo | Duración | Participantes |
|---|---|---|---|
| **Planificación** | Lunes de inicio de sprint | 1 h | Practicante + RR.HH. |
| **Seguimiento diario** | Diario | 10 min | Practicante (registro escrito de avance, plan del día e impedimentos) |
| **Revisión de sprint** | Viernes de cierre | 1 h | Practicante + RR.HH. + Dirección cuando corresponda |
| **Retrospectiva** | Viernes de cierre | 30 min | Practicante (registro de qué funcionó, qué no y qué se cambia) |
| **Refinamiento** | Mitad de sprint | 45 min | Practicante + RR.HH. |

### 6.3. Artefactos

- **Product Backlog**: historias de usuario priorizadas por RR.HH., escritas
  en el formato *Como [rol] quiero [acción] para [beneficio]*, con criterios
  de aceptación.
- **Sprint Backlog**: las historias comprometidas del sprint, descompuestas en
  tareas técnicas.
- **Incremento**: software funcionando, desplegado y verificado al cierre de
  cada sprint.
- **Tablero**: columnas *Por hacer · En curso · En prueba · Terminado*.

### 6.4. Definición de Terminado (DoD)

Una historia se considera terminada solo cuando cumple **todo** lo siguiente:

1. La funcionalidad cumple sus criterios de aceptación.
2. Tiene pruebas automatizadas en la colección del API y la suite completa
   pasa.
3. Se verificó en pantalla, en tema claro y oscuro, y en ancho de teléfono.
4. El código está comentado explicando **por qué** se hizo así, no solo qué
   hace.
5. Está subido al repositorio con un mensaje que explique el cambio.
6. Si cambió la forma de instalar o de operar, la documentación se actualizó.

### 6.5. El ciclo de desarrollo dentro de cada sprint

| Fase | Actividad | Técnica / herramienta |
|---|---|---|
| **1. Levantamiento** | Entrevista con RR.HH., observación del proceso real, revisión de los formatos que ya usan (planilla y boleta en Excel) | Entrevista, observación directa, análisis documental |
| **2. Análisis** | Historias de usuario con criterios de aceptación; reglas de cálculo contrastadas con la norma | Historias de usuario, reglas de negocio documentadas |
| **3. Diseño** | Modelo de datos (migraciones versionadas), diseño de la API y de la interfaz sobre el sistema de diseño del proyecto | Modelo entidad-relación, prototipos de pantalla, componentes reutilizables |
| **4. Construcción** | Backend en Laravel, frontend en Angular, cada cambio en su rama | PHP 8.3 / Laravel 13, TypeScript / Angular 19, MySQL 8.4 |
| **5. Pruebas** | Pruebas automatizadas del API; verificación funcional en pantalla; pruebas de aceptación con RR.HH. sobre datos reales | Postman + Newman (557 aserciones), verificación manual guiada |
| **6. Despliegue** | Construcción de imágenes y publicación en el servidor; migraciones automáticas al arrancar | Docker Compose (6 servicios), nginx |
| **7. Capacitación y cierre** | Demostración al área usuaria, manual por rol, acta de conformidad | Sesiones presenciales, manual de usuario |
| **8. Mantenimiento** | Atención de incidencias reportadas, corrección y nueva entrega | Registro de incidencias, control de versiones |

### 6.6. Control de versiones y trazabilidad

Repositorio Git alojado en GitHub, con una rama por desarrollador y
consolidación en la rama principal. Cada commit explica el problema que
resuelve; el historial del repositorio es, en la práctica, la bitácora técnica
del proyecto y la fuente de las evidencias de avance.

### 6.7. Herramientas

| Categoría | Herramienta |
|---|---|
| Desarrollo | Visual Studio Code, PHP 8.3, Composer, Node.js, Angular CLI |
| Base de datos | MySQL 8.4 |
| Pruebas | Postman, Newman |
| Despliegue | Docker, Docker Compose, nginx |
| Control de versiones | Git, GitHub |
| Gestión | Tablero Scrum, actas de reunión |

---

## 7. EQUIPO DE TRABAJO

| N.º | Integrante | Rol | Responsabilidad en el proyecto | Dedicación |
|---|---|---|---|---|
| 1 | Alessandro Pastor Mamani Mamani | Practicante — Desarrollador / Scrum Master | Análisis, diseño, construcción, pruebas, despliegue, documentación y capacitación | `[N.º]` h/semana |
| 2 | `[nombre]` | Jefatura de Recursos Humanos — Product Owner | Define y prioriza requerimientos, valida entregables, aporta el conocimiento del proceso y de la norma | Según ceremonias |
| 3 | `[nombre]` | Supervisor del centro de prácticas | Supervisa el cumplimiento del plan, firma la conformidad | Semanal |
| 4 | `[nombre]` | Docente asesor UPeU | Orienta metodológicamente y evalúa el informe | Quincenal |
| 5 | Dirección / Consejo Directivo | Interesados | Aprueban la puesta en producción y las decisiones de alcance | Por hito |

> El proyecto tiene antecedentes de trabajo en equipo durante su construcción
> inicial; durante el periodo de prácticas la ejecución técnica está a cargo
> del practicante, bajo la supervisión indicada.

---

## 8. ACTIVIDADES, CRONOGRAMA Y PRESUPUESTO

### 8.1. Plan de sprints

Diez sprints de dos semanas (20 semanas). Las fechas se ajustan a la
aprobación del plan.

| Sprint | Semanas | Objetivo del sprint | Entregable |
|---|---|---|---|
| **0** | 1 | Levantamiento y preparación: entrevistas, revisión del proceso real, backlog inicial priorizado, ambiente de trabajo | Product Backlog, acta de inicio |
| **1** | 2-3 | Personal y contratos: ficha completa, importación masiva del padrón, contratos vigentes | Módulo de personal operativo con el padrón real cargado |
| **2** | 4-5 | Planilla del mes: generación masiva, conceptos por grupo, importación de conceptos desde Excel | Planilla del mes calculada y contrastada con el Excel del colegio |
| **3** | 6-7 | Boletas: emisión individual y masiva, PDF, notificación al trabajador | Boletas del primer mes emitidas en paralelo al proceso manual |
| **4** | 8-9 | Autoservicio del trabajador: ver, firmar y descargar su boleta; firma por trazo en pantalla | Firma electrónica en funcionamiento |
| **5** | 10-11 | Expediente digital: hoja de vida, contratos, documentos anteriores en lote | Expediente por trabajador con los documentos históricos cargados |
| **6** | 12-13 | Seguridad y auditoría: roles, permisos por módulo, bitácora de cambios, control de sesión | Módulo de seguridad y auditoría verificado |
| **7** | 14-15 | Reportes y panel de control: indicadores del mes, exportación a Excel | Panel y reportes en uso por RR.HH. y Dirección |
| **8** | 16-17 | Despliegue en producción: servidor, contenedores, respaldos automáticos, migración de datos | Sistema en producción con respaldo diario |
| **9** | 18-19 | Capacitación y estabilización: sesiones por rol, manual de usuario, atención de incidencias | Personal capacitado, manual entregado, incidencias cerradas |
| **10** | 20 | Cierre: informe final, documentación técnica, plan de firma digital con DNIe, acta de conformidad | Informe de prácticas y entrega formal |

### 8.2. Cronograma

| Actividad | S1 | S2-3 | S4-5 | S6-7 | S8-9 | S10-11 | S12-13 | S14-15 | S16-17 | S18-19 | S20 |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Levantamiento y análisis | ██ | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | | | |
| Diseño y construcción | | ██ | ██ | ██ | ██ | ██ | ██ | ██ | ▒ | | |
| Pruebas automatizadas | | ██ | ██ | ██ | ██ | ██ | ██ | ██ | ██ | ▒ | |
| Migración de datos | | ██ | | | | ██ | | | ██ | | |
| Despliegue e infraestructura | | | | | | | | ▒ | ██ | ▒ | |
| Capacitación | | | | | | | | | ▒ | ██ | ▒ |
| Documentación | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | ▒ | ██ | ██ |
| Cierre e informe | | | | | | | | | | ▒ | ██ |

`██` actividad principal · `▒` actividad de apoyo

### 8.3. Resumen del presupuesto

| Rubro | Monto estimado (S/) |
|---|---|
| Infraestructura (servidor y dominio, 12 meses) | 660.00 |
| Equipamiento para firma digital (opcional, a evaluar) | 1 070.00 |
| Recursos del practicante (equipo, internet, movilidad) | 1 240.00 |
| Materiales de capacitación | 120.00 |
| **Total estimado** | **3 090.00** |

El detalle se presenta en la sección 9. Los montos son referenciales a
septiembre de 2026 y el rubro de firma digital está sujeto a aprobación de la
institución.

---

## 9. DETALLE DEL PRESUPUESTO

### 9.1. Infraestructura

| Ítem | Descripción | Cantidad | Costo unitario (S/) | Total (S/) |
|---|---|---|---|---|
| Servidor VPS | 2 vCPU, 4 GB RAM, 80 GB SSD | 12 meses | 45.00 | 540.00 |
| Dominio | `.edu.pe` o `.com` | 1 año | 120.00 | 120.00 |
| Certificado SSL | Let's Encrypt | 1 | 0.00 | 0.00 |
| **Subtotal** | | | | **660.00** |

> No se presupuesta licencia de base de datos ni de servidor de aplicaciones:
> todo el software utilizado es libre (MySQL Community, PHP, nginx, Docker).

### 9.2. Firma digital con DNI electrónico *(opcional — a evaluar)*

| Ítem | Descripción | Cantidad | Costo unitario (S/) | Total (S/) |
|---|---|---|---|---|
| Lector de DNI electrónico | ACR39U o equivalente | 1 | 120.00 | 120.00 |
| Certificado digital de persona jurídica | Emitido por entidad acreditada, vigencia 1 año | 1 | 800.00 | 800.00 |
| Sellado de tiempo | Servicio anual | 1 | 150.00 | 150.00 |
| **Subtotal** | | | | **1 070.00** |

### 9.3. Recursos del practicante

| Ítem | Descripción | Cantidad | Costo unitario (S/) | Total (S/) |
|---|---|---|---|---|
| Equipo de cómputo | Depreciación del periodo | 5 meses | 80.00 | 400.00 |
| Servicio de internet | Conexión domiciliaria | 5 meses | 90.00 | 450.00 |
| Movilidad | Visitas al centro de prácticas | 40 viajes | 6.00 | 240.00 |
| Energía eléctrica | Proporcional | 5 meses | 30.00 | 150.00 |
| **Subtotal** | | | | **1 240.00** |

### 9.4. Capacitación

| Ítem | Descripción | Cantidad | Costo unitario (S/) | Total (S/) |
|---|---|---|---|---|
| Impresión de manuales | Manual por rol | 15 | 6.00 | 90.00 |
| Materiales de sesión | Útiles de escritorio | 1 | 30.00 | 30.00 |
| **Subtotal** | | | | **120.00** |

### 9.5. Financiamiento

| Rubro | Asume |
|---|---|
| Infraestructura y firma digital | Colegio Adventista "Túpac Amaru" |
| Recursos del practicante | Practicante |
| Capacitación | Colegio Adventista "Túpac Amaru" |

---

## 10. ESTRATEGIAS POR IMPLEMENTAR

### 10.1. Estrategia de calidad

- **Pruebas automatizadas como red de seguridad.** La colección del API
  (557 aserciones) se ejecuta completa antes de cada entrega. Una regla de
  cálculo que se rompa —por ejemplo, el 13 % de ONP— hace fallar la prueba
  antes de que llegue a una boleta.
- **Verificación sobre datos reales.** Cada mes de prueba se calcula en
  paralelo al proceso manual del colegio y se contrastan los resultados
  trabajador por trabajador hasta que coincidan al céntimo.
- **Revisión de código por comentario.** Todo lo que no sea evidente se
  comenta explicando la razón, no el mecanismo, para que quien mantenga el
  sistema después entienda por qué está así.

### 10.2. Estrategia de seguridad y protección de datos

- Acceso por rol: el trabajador solo ve lo suyo; RR.HH. no administra roles ni
  permisos; la bitácora de auditoría solo se lee.
- Contraseñas almacenadas con función de hash; sesión con cierre por
  inactividad; mensaje único ante credenciales inválidas para no revelar qué
  cuentas existen.
- Los documentos (boletas, firmas) se guardan fuera de la carpeta pública: se
  sirven solo a quien tiene derecho a verlos.
- Ningún dato real del personal en el repositorio: los archivos de datos y las
  credenciales quedan excluidos por configuración.
- Respaldo automático diario de la base y de los documentos, con retención de
  30 días.

### 10.3. Estrategia de adopción

- **Marcha en paralelo**: durante dos meses el sistema emite las boletas junto
  al proceso en Excel, hasta que RR.HH. confíe en los resultados.
- **Capacitación por rol y no general**: una sesión para RR.HH. (proceso
  completo), una para Dirección (reportes y aprobación) y una breve para el
  personal (ver y firmar su boleta), con material impreso de una página.
- **Acompañamiento en el primer cierre de mes** hecho íntegramente en el
  sistema.

### 10.4. Estrategia de continuidad

- Documentación de instalación y despliegue que permite levantar el sistema en
  una máquina nueva sin conocimiento previo del proyecto.
- Toda la configuración propia de cada instalación (contraseñas, dominio,
  correo) fuera del código, en archivos de configuración del despliegue.
- Semilla versionada de los catálogos del sistema, para que una instalación
  nueva arranque con áreas, cargos, sedes y conceptos ya cargados.

### 10.5. Gestión de riesgos

| Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|
| Diferencias entre el cálculo del sistema y el proceso manual | Media | Alto | Marcha en paralelo y contraste mensual trabajador por trabajador |
| Resistencia del personal a la boleta digital | Media | Medio | Capacitación breve por rol y acompañamiento; la boleta sigue pudiendo imprimirse |
| Indisponibilidad del servidor en fecha de pago | Baja | Alto | Respaldo diario y procedimiento de restauración probado; posibilidad de emitir desde una instalación local |
| Cambio normativo (tasas, RMV) durante el periodo | Media | Medio | Tasas y montos configurables desde el catálogo de conceptos, no escritos en el código |
| Pérdida o filtración de datos personales | Baja | Muy alto | Acceso por rol, documentos fuera de la carpeta pública, respaldos, repositorio privado |
| Disponibilidad limitada del área usuaria para las ceremonias | Media | Medio | Ceremonias cortas y agendadas con anticipación; refinamiento asíncrono por escrito |

---

## 11. EVIDENCIAS QUE GENERAR

### 11.1. Evidencias de proceso (metodología)

| Evidencia | Frecuencia | Soporte |
|---|---|---|
| Product Backlog priorizado | Continuo | Tablero + archivo versionado |
| Acta de planificación de sprint | Por sprint (10) | Documento firmado por RR.HH. |
| Registro de seguimiento diario | Diario | Bitácora escrita |
| Acta de revisión de sprint con conformidad del Product Owner | Por sprint (10) | Documento firmado |
| Registro de retrospectiva | Por sprint (10) | Bitácora |
| Gráfico de avance del sprint | Por sprint | Captura del tablero |

### 11.2. Evidencias de producto (software)

| Evidencia | Descripción |
|---|---|
| Repositorio de código | Historial de commits con el detalle de cada cambio |
| Incremento desplegado | Sistema accesible y funcionando al cierre de cada sprint |
| Colección de pruebas automatizadas | Suite del API con su reporte de ejecución |
| Reporte de ejecución de pruebas | Salida de la suite antes de cada entrega |
| Capturas de pantalla | Por módulo, en tema claro y oscuro |
| Boletas emitidas de prueba | PDF generados durante la marcha en paralelo |
| Respaldos generados | Listado de respaldos diarios del periodo |

### 11.3. Evidencias documentales

| Documento | Contenido |
|---|---|
| Documento de requerimientos | Historias de usuario con criterios de aceptación |
| Modelo de datos | Diagrama entidad-relación y diccionario de datos ([MODELO-DE-DATOS.md](MODELO-DE-DATOS.md)) |
| Manual de instalación y despliegue | Procedimiento completo, del clonado a la puesta en marcha |
| Manual de usuario por rol | RR.HH., Dirección y trabajador |
| Plan de firma digital con DNIe | Análisis técnico, normativo y económico |
| Informe final de prácticas | Resultados obtenidos frente a los objetivos planteados |

### 11.4. Evidencias institucionales

| Documento | Emite |
|---|---|
| Acta de inicio de prácticas | Colegio |
| Registro de asistencia / control de horas | Colegio |
| Acta de capacitación con firma de asistentes | Colegio |
| Acta de conformidad de la puesta en producción | Dirección |
| Constancia de prácticas preprofesionales | Colegio |

---

## Datos por completar antes de presentar

| Dato | Dónde aparece |
|---|---|
| Fecha de inicio y de término de las prácticas | Cabecera y sección 8 |
| Número total de horas exigido por la Escuela | Cabecera |
| Horas semanales de dedicación | Sección 7 |
| Nombre y cargo del supervisor del centro | Cabecera y sección 7 |
| Nombre del docente asesor de la UPeU | Cabecera y sección 7 |
| Nombre de quien ejerce la jefatura de RR.HH. | Sección 7 |
| Confirmación de los montos del presupuesto | Sección 9 |
