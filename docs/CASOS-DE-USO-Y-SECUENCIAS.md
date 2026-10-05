# Casos de uso y diagramas de secuencia — CATA-Recibo

Un caso de uso y un diagrama de secuencia por cada proceso del sistema. Los
diagramas están en [Mermaid](https://mermaid.js.org/): GitHub los dibuja solo
al abrir este archivo.

Las rutas y reglas salen del código (`CR-Backend/routes/api.php` y los
controladores). Complementa a [ARQUITECTURA-Y-UML.md](ARQUITECTURA-Y-UML.md),
que trae la arquitectura, las clases y el diagrama general de casos de uso.

## Índice

| # | Proceso | Actor principal |
|---|---------|-----------------|
| 1 | [Iniciar sesión](#1-iniciar-sesión) | Cualquier usuario |
| 2 | [Recuperar la contraseña](#2-recuperar-la-contraseña) | Cualquier usuario |
| 3 | [Aceptar los términos de uso](#3-aceptar-los-términos-de-uso) | Trabajador |
| 4 | [Dar de alta a un empleado](#4-dar-de-alta-a-un-empleado) | RR.HH. |
| 5 | [Importar empleados desde Excel](#5-importar-empleados-desde-excel) | RR.HH. |
| 6 | [Generar la planilla del mes](#6-generar-la-planilla-del-mes) | RR.HH. |
| 7 | [Importar conceptos desde Excel y aplicarlos a un grupo](#7-importar-conceptos-desde-excel-y-aplicarlos-a-un-grupo) | RR.HH. |
| 8 | [Emitir las boletas](#8-emitir-las-boletas) | RR.HH. |
| 9 | [Ver, descargar y firmar una boleta](#9-ver-descargar-y-firmar-una-boleta) | Trabajador |
| 10 | [Pedir y aprobar vacaciones](#10-pedir-y-aprobar-vacaciones) | Trabajador / RR.HH. |
| 11 | [Expediente: hoja de vida y documentos anteriores](#11-expediente-hoja-de-vida-y-documentos-anteriores) | Trabajador / RR.HH. |
| 12 | [Aviso automático de planilla pendiente](#12-aviso-automático-de-planilla-pendiente) | Sistema |
| 13 | [Administración: roles, módulos, valores legales y auditoría](#13-administración-roles-módulos-valores-legales-y-auditoría) | Administrador |

## Actores

| Actor | Quién es | Qué puede |
|-------|----------|-----------|
| **Trabajador** | Rol `empleado` | Lo suyo: boletas, documentos, vacaciones, perfil |
| **RR.HH.** | Rol `rrhh` | Todo lo de personal y planilla, el panel de control |
| **Administrador** | Rol `admin` | Lo de RR.HH. más roles, módulos, valores legales y auditoría |
| **Sistema** | Tareas programadas | Avisos y limpieza, sin que nadie las pida |

Todas las rutas, salvo el inicio de sesión y la recuperación de clave, pasan
por los filtros `auth:sanctum → sesion → clave_nueva → terminos`: sin sesión
vigente, sin haber cambiado la clave provisional o sin haber aceptado los
términos no se llega a ninguna pantalla.

## Casos de uso por actor

```mermaid
flowchart LR
    T(("Trabajador"))
    H(("RR.HH."))
    A(("Administrador"))
    S(("Sistema"))

    subgraph Comunes
        C1["1 Iniciar sesión"]
        C2["2 Recuperar contraseña"]
    end
    subgraph Trabajador_
        direction TB
        T3["3 Aceptar términos"]
        T9["9 Ver, descargar y firmar boleta"]
        T10a["10 Pedir vacaciones"]
        T11a["11 Subir mi hoja de vida"]
    end
    subgraph RRHH_
        direction TB
        H4["4 Alta de empleado"]
        H5["5 Importar empleados"]
        H6["6 Generar planilla"]
        H7["7 Importar conceptos"]
        H8["8 Emitir boletas"]
        H10b["10 Aprobar vacaciones"]
        H11b["11 Expediente y documentos anteriores"]
    end
    subgraph Admin_
        H13["13 Roles, módulos, valores legales, auditoría"]
    end
    subgraph Automatico
        S12["12 Aviso de planilla pendiente"]
    end

    T --> C1 & C2 & T3 & T9 & T10a & T11a
    H --> C1 & C2 & H4 & H5 & H6 & H7 & H8 & H10b & H11b
    A --> H13
    A -.->|"incluye lo de RR.HH."| H
    S --> S12
```

---

## 1. Iniciar sesión

| | |
|---|---|
| **Actor** | Cualquier usuario con cuenta |
| **Precondición** | La cuenta existe y está activa. No hay registro público: las cuentas las crea RR.HH. |
| **Ruta** | `POST /api/login` (limitada por `throttle:ingreso`) |
| **Flujo principal** | 1. Escribe correo y contraseña. 2. El sistema valida. 3. Entrega un token con vencimiento. 4. La pantalla elige la ruta según el rol y el estado de la cuenta. |
| **Flujos alternos** | Demasiados intentos → 429. Correo inexistente, contraseña mala o cuenta inactiva → el mismo 422 «Credenciales incorrectas», y tarda lo mismo (no se puede averiguar qué correos existen). Clave provisional (el DNI) → obligado a cambiarla antes de seguir. Términos sin aceptar → obligado a aceptarlos. |
| **Postcondición** | Sesión abierta; los tokens anteriores de esa cuenta quedan borrados. |

```mermaid
sequenceDiagram
    actor U as Persona
    participant F as Angular (AuthService)
    participant TH as throttle:ingreso
    participant AC as AuthController
    participant DB as MySQL

    U->>F: correo y contraseña
    F->>TH: POST /api/login
    alt demasiados intentos
        TH-->>F: 429
        F->>U: espere un momento
    else dentro del límite
        TH->>AC: login()
        AC->>DB: buscar usuario por correo
        alt no existe, clave mala o cuenta inactiva
            AC->>AC: Hash::make para gastar el mismo tiempo
            AC-->>F: 422 Credenciales incorrectas
            F->>U: mismo mensaje en los tres casos
        else correcto
            AC->>DB: borrar tokens anteriores
            AC->>DB: crear token con vencimiento
            AC-->>F: token, usuario, debe_cambiar_password
            alt debe cambiar la clave
                F->>U: pantalla de cambio de clave
            else debe aceptar términos
                F->>U: pantalla de términos
            else todo al día
                F->>U: inicio según su rol
            end
        end
    end
```

## 2. Recuperar la contraseña

| | |
|---|---|
| **Actor** | Cualquier usuario que olvidó la clave |
| **Precondición** | Tiene acceso a su correo |
| **Rutas** | `POST /api/forgot-password`, `POST /api/reset-password` (limitadas por `throttle:recuperacion`) |
| **Flujo principal** | 1. Escribe su correo. 2. El sistema contesta lo mismo exista o no. 3. Si existe, llega un correo con enlace. 4. Abre el enlace y escribe la clave nueva. 5. El sistema la guarda. |
| **Flujos alternos** | Pidió otro enlace demasiado pronto → se le avisa (es el único caso que se dice, para que no espere un correo que no saldrá). Token vencido o inválido → rechazo. |
| **Postcondición** | Clave nueva; **todas** las sesiones abiertas de esa cuenta se cierran. |

```mermaid
sequenceDiagram
    actor U as Persona
    participant F as Angular
    participant AC as AuthController
    participant PB as Password broker (Laravel)
    participant M as Correo
    participant DB as MySQL

    U->>F: escribe su correo
    F->>AC: POST /forgot-password
    AC->>PB: sendResetLink(email)
    alt el correo existe
        PB->>DB: guardar token de un solo uso
        PB->>M: enviar RestablecerPassword con el enlace
    end
    AC-->>F: mismo mensaje exista o no
    U->>M: abre el correo y sigue el enlace
    U->>F: escribe la clave nueva
    F->>AC: POST /reset-password con token
    AC->>PB: reset(token, clave nueva)
    alt token válido
        PB->>DB: guardar clave nueva
        AC->>DB: borrar todos los tokens de sesión de la cuenta
        AC-->>F: clave restablecida
    else inválido o vencido
        AC-->>F: 422 enlace no válido
    end
```

## 3. Aceptar los términos de uso

Reemplaza a la hoja impresa en la que cada trabajador firmaba que aceptaba
recibir sus boletas por medios digitales.

| | |
|---|---|
| **Actor** | Trabajador (y cualquier usuario con sesión) |
| **Precondición** | Sesión abierta y términos sin aceptar, o aceptada una versión anterior |
| **Rutas** | `GET /api/terms`, `POST /api/terms/accept` |
| **Flujo principal** | 1. El sistema le muestra el documento vigente. 2. Marca que acepta y confirma. 3. Se guarda quién, cuándo, qué versión y desde dónde. |
| **Flujos alternos** | Sin el «sí» explícito no se guarda. Si hay una versión nueva del documento, debe aceptarla de nuevo. |
| **Postcondición** | RR.HH. ve quién firmó y quién no, sin perseguir a nadie. |

```mermaid
sequenceDiagram
    actor T as Trabajador
    participant F as Angular
    participant MW as Middleware terminos
    participant TC as TerminosController
    participant DB as MySQL

    T->>F: intenta entrar a una pantalla
    F->>MW: cualquier petición autenticada
    MW->>DB: versión aceptada vs versión vigente
    alt no coincide
        MW-->>F: 428 debe aceptar los términos
        F->>TC: GET /terms
        TC-->>F: documento y versión vigente
        F->>T: muestra los términos
        T->>F: marca Acepto y confirma
        F->>TC: POST /terms/accept
        TC->>DB: guardar usuario, fecha, versión e IP
        TC-->>F: aceptado
        F->>T: continúa a su pantalla
    else al día
        MW-->>F: pasa
    end
```

## 4. Dar de alta a un empleado

| | |
|---|---|
| **Actor** | RR.HH. |
| **Precondición** | Rol `rrhh` o `admin` |
| **Ruta** | `POST /api/employees` (la lógica vive en `AltaDeEmpleado`, compartida con la importación) |
| **Flujo principal** | 1. Escribe el DNI y pulsa consultar. 2. El sistema trae los nombres del padrón. 3. Completa cargo, área, sede, tipo de contrato, sueldo y datos de AFP u ONP. 4. Guarda. 5. El sistema crea la ficha, la cuenta con el DNI como clave provisional y el contrato inicial. |
| **Flujos alternos** | DNI de 8 cifras o carné de extranjería de 9. DNI ya registrado → rechazo. CUSPP de 12 caracteres, CCI de 20 dígitos. Un contrato que exige fecha de fin sin ella → rechazo. Si falla cualquier paso no queda nada a medias (transacción). |
| **Postcondición** | Empleado + usuario con rol `empleado` + contrato inicial, y el movimiento anotado en Auditoría. |

```mermaid
sequenceDiagram
    actor R as RR.HH.
    participant F as Angular
    participant CD as ConsultaDniController
    participant EC as EmpleadoController
    participant AE as AltaDeEmpleado
    participant DB as MySQL

    R->>F: escribe el DNI
    F->>CD: GET /dni-lookup/{dni}
    CD-->>F: nombres y apellidos del padrón
    R->>F: completa el resto y guarda
    F->>EC: POST /employees
    EC->>AE: reglas() y validar
    alt datos inválidos
        EC-->>F: 422 con el error por campo
    else válidos
        AE->>DB: BEGIN
        AE->>DB: crear empleado
        AE->>DB: crear usuario, clave provisional = DNI, rol empleado
        AE->>DB: crear contrato inicial
        AE->>DB: COMMIT
        EC->>DB: registrar en Auditoría
        EC-->>F: empleado creado
        F->>R: aviso de éxito
    end
```

## 5. Importar empleados desde Excel

El archivo **no sale del navegador**: se lee allí y solo viajan las filas.
Detalle del flujo en `docs/importacion-empleados-secuencia.mmd`.

| | |
|---|---|
| **Actor** | RR.HH. |
| **Precondición** | Tiene el Excel llenado (puede bajar el modelo en `GET /employee-import/template`) |
| **Rutas** | `/employee-import/recognize`, `/preview`, `/apply`, `/resume` |
| **Flujo principal** | 1. Sube el Excel. 2. El sistema reconoce qué campo es cada columna y RR.HH. confirma. 3. Previsualiza: por cada fila busca el DNI y decide ALTA (no existe) o CAMBIO (existe). 4. Si no hay errores, aplica. 5. Se suben las hojas de vida en lote, emparejadas por DNI. |
| **Flujos alternos** | Hay errores en la previsualización → corrige el Excel y repite. Celda vacía = no tocar el dato actual. Falla algo al aplicar → se deshace todo (todo o nada). |
| **Postcondición** | Altas y cambios hechos y anotados en Auditoría. |

```mermaid
sequenceDiagram
    actor H as RR.HH.
    participant N as Navegador (Angular)
    participant API as ImportacionEmpleadosController
    participant DB as MySQL

    H->>N: sube el Excel
    N->>N: lee el archivo en el navegador
    N->>API: POST /recognize con las columnas
    API-->>N: qué campo es cada columna
    H->>N: confirma o corrige el mapeo
    N->>API: POST /preview con columnas y filas
    API->>DB: por cada fila busca el DNI
    API->>API: decide ALTA o CAMBIO, celda vacía no se toca
    API-->>N: tabla con resultado y errores
    alt hay errores
        H->>N: corrige el Excel y repite
    else sin errores
        H->>N: pulsa Aplicar
        N->>API: POST /apply
        API->>API: valida todo de nuevo
        API->>DB: BEGIN
        API->>DB: altas, empleado + usuario + contrato
        API->>DB: cambios, solo lo escrito
        API->>DB: COMMIT, todo o nada
        API->>DB: registrar en Auditoría
        API-->>N: X altas, Y cambios
        loop por cada hoja de vida
            N->>API: POST /resume con DNI y archivo
            API->>DB: buscar empleado por DNI y guardar
        end
        N->>H: resumen final
    end
```

## 6. Generar la planilla del mes

| | |
|---|---|
| **Actor** | RR.HH. |
| **Precondición** | Existe una corrida de planilla (grupo de empleados) y los valores legales del año (UIT, asignación familiar, % de pensión, EsSalud) |
| **Rutas** | `POST /api/payroll-runs/{id}/generate`, o `POST /api/periods/{id}/generate-payroll` |
| **Flujo principal** | 1. Elige la corrida, el mes y el año y pulsa Generar. 2. Por cada empleado se crea su planilla. 3. Se calculan los conceptos automáticos: pensión (ONP o AFP), EsSalud, asignación familiar, gratificación y renta de 5ª categoría. 4. Se suma el total. |
| **Flujos alternos** | El empleado ya tiene planilla de ese mes → se omite. Resultado parcial → el aviso dice cuántas se generaron y cuántas se omitieron. |
| **Postcondición** | Una planilla por empleado con sus líneas; cada cambio de total queda en `planilla_historial` por un trigger. |

```mermaid
sequenceDiagram
    actor R as RR.HH.
    participant F as Angular
    participant PC as PlanillaCorridaController
    participant GL as GeneraPlanillasEnLote
    participant CC as CalculaConceptosPlanilla
    participant PL as Planilla
    participant DB as MySQL

    R->>F: Generar para una corrida y un mes
    F->>PC: POST /payroll-runs/{id}/generate
    PC->>GL: generarLote(empleados, mes, año)
    loop por cada empleado
        GL->>DB: ¿ya tiene planilla de ese mes?
        alt ya tiene
            GL->>GL: omitir
        else no tiene
            GL->>DB: crear planilla
            GL->>CC: generarConceptosAutomaticos()
            CC->>CC: pensión, EsSalud, asignación familiar, gratificación, renta de 5ta
            CC->>DB: crear líneas PayrollDetalle
            GL->>PL: recalcularTotal()
            PL->>DB: UPDATE total
            DB->>DB: trigger guarda planilla_historial
        end
    end
    PC-->>F: generadas y omitidas
    F->>R: aviso de éxito, parcial o error
```

## 7. Importar conceptos desde Excel y aplicarlos a un grupo

Para los montos que no son automáticos: bonos, descuentos, comisiones, horas
extra. Hay dos niveles: por lote desde Excel y por grupo desde el catálogo.

| | |
|---|---|
| **Actor** | RR.HH. |
| **Precondición** | Planilla del mes ya generada (proceso 6) |
| **Rutas** | `/concept-import/recognize`, `/preview`, `/apply`; `POST /payment-concepts/{id}/apply-to-group`; `POST /payrolls/{id}/concepts`; `PUT /payrolls/{id}/recalcular` |
| **Flujo principal** | 1. Sube el Excel. 2. El sistema reconoce las columnas (recuerda los alias ya confirmados). 3. Previsualiza qué se aplicará a quién. 4. Aplica. 5. Se recalcula el total de cada planilla tocada. |
| **Flujos alternos** | Celda vacía = no tocar. Concepto desconocido → se pide asignarlo a uno del catálogo y se recuerda el alias. Para aplicar un mismo concepto a una lista de empleados, `apply-to-group` usa el cálculo y valor del catálogo. |
| **Postcondición** | Líneas creadas o actualizadas y totales al día, anotado en Auditoría. |

```mermaid
sequenceDiagram
    actor R as RR.HH.
    participant N as Angular
    participant IC as ImportacionConceptosController
    participant PCC as PaymentConceptController
    participant PL as Planilla
    participant DB as MySQL

    R->>N: sube el Excel de conceptos
    N->>IC: POST /concept-import/recognize
    IC->>DB: buscar alias recordados
    IC-->>N: columnas y conceptos reconocidos
    R->>N: confirma el mapeo
    N->>IC: POST /concept-import/preview
    IC->>DB: cruzar DNI con planillas del mes
    IC-->>N: qué se creará o cambiará, y errores
    R->>N: Aplicar
    N->>IC: POST /concept-import/apply
    IC->>DB: BEGIN
    loop por cada fila válida
        IC->>DB: crear o actualizar PayrollDetalle
        IC->>PL: recalcularTotal()
        PL->>DB: UPDATE total
    end
    IC->>DB: COMMIT y Auditoría
    IC-->>N: resumen
    opt aplicar un concepto a un grupo
        R->>N: elige concepto y lista de empleados
        N->>PCC: POST /payment-concepts/{id}/apply-to-group
        PCC->>DB: línea por cada planilla del grupo
        PCC-->>N: aplicados y omitidos
    end
```

## 8. Emitir las boletas

| | |
|---|---|
| **Actor** | RR.HH. |
| **Precondición** | Planilla del mes generada y revisada |
| **Rutas** | `GET /api/payslips/{employee_id}/{month}/{year}` (una), `POST /api/payslips/generate-bulk` (todas, o solo las de una planilla) |
| **Flujo principal** | 1. Pulsa Emitir boletas. 2. Por cada planilla se arma el PDF y se guarda una copia en disco privado. 3. Se registra el Documento. 4. Se crea el aviso en la campana del trabajador y se encola el correo. |
| **Flujos alternos** | Boleta ya emitida → no se vuelve a generar (idempotente). Si ya está firmada, el PDF queda congelado. Corregir una boleta emitida exige la clave de RR.HH. (`POST /verify-password`) y no manda un segundo aviso. Boletas de un año anterior son de registro: no avisan. |
| **Postcondición** | Un Documento tipo `boleta` por planilla, con la fecha y el correo del aviso anotados. |

```mermaid
sequenceDiagram
    actor R as RR.HH.
    participant F as Angular
    participant BC as BoletaController
    participant DB as MySQL
    participant DK as Disco privado
    participant Q as Cola de correo
    actor T as Trabajador

    R->>F: Emitir boletas del mes
    F->>BC: POST /payslips/generate-bulk
    BC->>DB: planillas del mes con su empleado, y cuáles ya tienen boleta
    loop por cada planilla sin boleta
        BC->>BC: construirBoleta() calcula y arma el PDF
        BC->>DK: guardar el PDF
        BC->>DB: crear Documento tipo boleta
        BC->>DB: crear Notificacion para el trabajador
        BC->>Q: encolar correo BoletaGenerada
        BC->>DB: registrarAviso(correo, fecha)
    end
    BC-->>F: emitidas y ya existentes
    F->>R: resumen
    Q-->>T: correo Tu boleta ya está lista
```

## 9. Ver, descargar y firmar una boleta

La firma es la firma dibujada del trabajador en pantalla más su contraseña.

| | |
|---|---|
| **Actor** | Trabajador |
| **Precondición** | Boleta emitida (proceso 8); el trabajador tiene su firma registrada (`POST /my-signature`) |
| **Rutas** | `GET /my-documents`, `PATCH /my-documents/{id}/viewed`, `GET /documents/{id}/download`, `POST /my-documents/{id}/sign` |
| **Flujo principal** | 1. Ve el aviso en la campana. 2. Abre la boleta (queda «vista»). 3. La descarga si quiere. 4. Pulsa Firmar y escribe su contraseña. 5. El sistema marca el documento firmado con un código de verificación y regenera el PDF con el sello. |
| **Flujos alternos** | Contraseña incorrecta → hasta 3 intentos; al tercero se cierra la sesión. Ya firmado → rechazo. Boleta «en papel» (de registro, anterior al sistema) → no se firma. Un tipo de documento que no se firma → rechazo (se revisa en el servidor, no solo escondiendo el botón). |
| **Postcondición** | `estado_firma = firmado`, con fecha, nombre y código. |

```mermaid
sequenceDiagram
    actor T as Trabajador
    participant F as Angular
    participant MD as MisDocumentosController
    participant BC as BoletaController
    participant DB as MySQL

    T->>F: abre la campana y elige la boleta
    F->>MD: GET /my-documents
    MD-->>F: sus documentos, solo los suyos
    F->>MD: PATCH /my-documents/{id}/viewed
    MD->>DB: estado_firma = visto
    T->>F: Firmar y escribe su contraseña
    F->>MD: POST /my-documents/{id}/sign
    MD->>DB: comprobar contraseña
    alt contraseña incorrecta
        MD->>DB: contar intento
        alt es el tercero
            MD-->>F: sesión cerrada
        else
            MD-->>F: 422 contraseña incorrecta
        end
    else correcta
        MD->>MD: ¿tipo firmable y no firmado ni en papel?
        MD->>DB: estado_firma = firmado, fecha, firmado_por, codigo_firma
        MD->>BC: construirBoleta() con el sello de firma
        BC->>DB: reemplazar el PDF por el firmado
        MD-->>F: documento firmado
        F->>T: aviso de éxito
    end
```

Estados de la boleta: ver [ARQUITECTURA-Y-UML.md §6](ARQUITECTURA-Y-UML.md).

## 10. Pedir y aprobar vacaciones

Regla del colegio: solo el personal con contrato **indeterminado** tiene
vacaciones; los otros contratos cobran Vacaciones Truncas.

| | |
|---|---|
| **Actor** | Trabajador (pide) y RR.HH. (resuelve) |
| **Precondición** | Su contrato da derecho a descanso |
| **Rutas** | `GET /vacations/balance`, `POST /vacations`, `PUT /vacations/{id}`, `DELETE /vacations/{id}` |
| **Flujo principal** | 1. El trabajador consulta su saldo. 2. Pide fechas de inicio y fin (días de calendario, ambos extremos incluidos). 3. Queda `pendiente`. 4. RR.HH. la aprueba o la rechaza. 5. Queda anotado quién resolvió y cuándo. |
| **Flujos alternos** | Contrato sin derecho → rechazo al pedir y otra vez al aprobar. Fechas que se cruzan con otra solicitud → rechazo. Se pasa del saldo al aprobar (por otras ya aprobadas) → rechazo. El trabajador solo retira la suya y mientras esté pendiente; RR.HH. retira cualquiera. |
| **Postcondición** | Solicitud aprobada o rechazada con `aprobado_por` y `aprobado_at` tomados del token, nunca del cliente. |

```mermaid
sequenceDiagram
    actor T as Trabajador
    participant F as Angular
    participant VC as VacacionController
    participant DB as MySQL
    actor R as RR.HH.

    T->>F: abre Vacaciones
    F->>VC: GET /vacations/balance
    VC-->>F: días ganados, usados y disponibles
    T->>F: elige fechas y envía
    F->>VC: POST /vacations
    VC->>DB: ¿contrato con derecho?
    VC->>DB: ¿se cruza con otra solicitud?
    alt no cumple
        VC-->>F: 422 con el motivo
    else cumple
        VC->>DB: crear solicitud, estado pendiente
        VC-->>F: solicitud registrada
    end
    R->>F: abre las solicitudes pendientes
    F->>VC: GET /vacations
    R->>F: Aprobar o Rechazar
    F->>VC: PUT /vacations/{id}
    opt aprueba
        VC->>DB: revisar derecho y saldo otra vez
    end
    alt todo en orden
        VC->>DB: estado, aprobado_por = usuario del token, aprobado_at = ahora
        VC-->>F: solicitud resuelta
    else ya no cumple
        VC-->>F: 422 con el motivo
    end
```

## 11. Expediente: hoja de vida y documentos anteriores

«Documentos» es el expediente digital de cada trabajador: hoja de vida,
contratos y boletas.

| | |
|---|---|
| **Actor** | Trabajador (su propio CV) y RR.HH. (expediente de todos y PDFs de antes del sistema) |
| **Precondición** | El empleado existe |
| **Rutas** | `POST /my-documents/resume`; `GET /employee-files` y `/employee-files/{id}`; `POST /legacy-documents/preview` y `POST /legacy-documents` |
| **Flujo principal (CV)** | 1. El trabajador sube su hoja de vida. 2. El sistema la guarda en su expediente y da de baja la anterior. |
| **Flujo principal (RR.HH.)** | 1. Abre el expediente y ve qué falta (sin hoja de vida, boletas por firmar, contratos por vencer). 2. Sube en lote los PDF de antes del sistema. 3. El sistema lee el DNI del PDF y lo empareja con el trabajador. 4. Previsualiza. 5. Confirma y se guardan como `boleta_anterior` o `contrato_anterior`. |
| **Flujos alternos** | El empleado sale del token, nunca del cuerpo de la petición (nadie puede colgarle un archivo al expediente de otro). PDF sin DNI legible o con DNI desconocido → queda marcado para corregir. Tipo o periodo no válido → rechazo con motivo. |
| **Postcondición** | Documentos en el expediente del trabajador, con su estado de firma (los de registro quedan «en papel»). |

```mermaid
sequenceDiagram
    actor T as Trabajador
    actor R as RR.HH.
    participant F as Angular
    participant MD as MisDocumentosController
    participant EX as ExpedienteController
    participant DA as DocumentosAnterioresController
    participant DB as MySQL
    participant DK as Disco privado

    T->>F: sube su hoja de vida
    F->>MD: POST /my-documents/resume
    MD->>DB: empleado desde el token
    MD->>DK: guardar el archivo
    MD->>DB: dar de baja el CV anterior y crear el nuevo
    MD-->>F: guardada

    R->>F: abre Documentos
    F->>EX: GET /employee-files
    EX-->>F: personal con lo que le falta
    R->>F: sube PDFs anteriores en lote
    F->>DA: POST /legacy-documents/preview
    DA->>DA: lee el DNI de cada PDF
    DA->>DB: busca al trabajador por DNI
    DA-->>F: tabla con el dueño de cada PDF y los problemas
    R->>F: confirma
    F->>DA: POST /legacy-documents
    DA->>DK: guardar cada PDF
    DA->>DB: crear Documento boleta_anterior o contrato_anterior
    DA-->>F: subidos y omitidos
```

## 12. Aviso automático de planilla pendiente

| | |
|---|---|
| **Actor** | Sistema (programador de tareas de Laravel) |
| **Precondición** | El `schedule:run` corre cada minuto en el servidor |
| **Tarea** | `planillas:avisar-pendientes`, todos los días a las 08:00 |
| **Flujo principal** | 1. El penúltimo día del mes el sistema revisa si falta generar la planilla o emitir las boletas. 2. Si falta algo, deja un aviso en la campana de RR.HH. y Administración. |
| **Flujos alternos** | Cualquier otro día, o si todo está hecho → no avisa. Se programa a las 8 y no a medianoche para que el aviso ya esté cuando llegan a trabajar. |
| **Tarea de limpieza** | `sanctum:prune-expired --hours=48`, a diario: borra los tokens sin uso en 48 horas. |

```mermaid
sequenceDiagram
    participant SC as Scheduler (08:00)
    participant AP as AvisarPlanillaPendiente
    participant DB as MySQL
    actor R as RR.HH. y Administración

    SC->>AP: planillas:avisar-pendientes
    AP->>AP: ¿hoy es el penúltimo día del mes?
    alt no
        AP-->>SC: nada que hacer
    else sí
        AP->>DB: ¿falta generar la planilla o emitir boletas?
        alt falta algo
            AP->>DB: crear Notificacion para cada usuario rrhh y admin
            R->>R: ve el aviso en la campana al entrar
        else todo hecho
            AP-->>SC: sin aviso
        end
    end
```

## 13. Administración: roles, módulos, valores legales y auditoría

| | |
|---|---|
| **Actor** | Administrador |
| **Precondición** | Rol `admin` (las rutas lo exigen con `rol:admin`) |
| **Rutas** | `/roles`, `/module-groups`, `/modules`, `POST /modules/{id}/roles`, `/legal-values`, `PUT /settings`, `GET /audit-log` |
| **Flujo principal** | 1. Administra qué módulos (menús) ve cada rol. 2. Carga los valores legales de cada año (UIT, asignación familiar, % de pensión y EsSalud) que usa el cálculo de la planilla. 3. Ajusta la configuración del sistema. 4. Consulta la auditoría: quién hizo qué y cuándo, con «Ver más» para el detalle completo de un registro. |
| **Flujos alternos** | Un RR.HH. que intente estas rutas recibe 403. Los cambios de menús llegan a cada usuario por `GET /my-modules` en su siguiente carga. |
| **Postcondición** | Cambios aplicados y registrados en Auditoría. |

```mermaid
sequenceDiagram
    actor A as Administrador
    participant F as Angular
    participant MW as Middleware rol:admin
    participant MC as ModuloController
    participant VL as ValorLegalController
    participant AU as AuditoriaController
    participant DB as MySQL
    actor U as Otro usuario

    A->>F: abre Módulos y asigna roles
    F->>MW: POST /modules/{id}/roles
    MW->>MW: ¿rol admin?
    alt no es admin
        MW-->>F: 403
    else es admin
        MW->>MC: asignarRoles()
        MC->>DB: guardar módulos por rol y Auditoría
        MC-->>F: guardado
        U->>F: recarga la pantalla
        F->>MC: GET /my-modules
        MC-->>F: sus menús actualizados
    end
    A->>F: carga los valores legales del año
    F->>VL: POST /legal-values
    VL->>DB: guardar y Auditoría
    A->>F: abre la Auditoría
    F->>AU: GET /audit-log
    AU->>DB: registros paginados
    AU-->>F: quién, qué, cuándo y detalle
```
