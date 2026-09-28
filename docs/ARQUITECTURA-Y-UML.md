# Arquitectura y UML — CATA-Recibo

Complementa a [MODELO-DE-DATOS.md](MODELO-DE-DATOS.md) (el modelo entidad-relación,
que se genera desde las migraciones). Aquí está el resto del modelado: quién usa
el sistema, cómo está armado por dentro y cómo se conversan sus piezas.

Los diagramas están en Mermaid: GitHub y VS Code los dibujan directamente.

---

## 1. Arquitectura general

Cliente-servidor en capas. El navegador nunca toca la base: todo pasa por el API.

```mermaid
flowchart LR
    subgraph Cliente["Navegador"]
        A["Angular 19<br/>componentes + servicios<br/>guards + interceptor"]
    end
    subgraph Servidor["Servidor"]
        N["nginx<br/>sirve Angular en / y proxy a /api"]
        subgraph L["Laravel 13 (php-fpm)"]
            MW["Middleware<br/>sanctum · sesion · clave_nueva · terminos · rol"]
            C["Controllers<br/>(HTTP, validación)"]
            T["Traits y Support<br/>(motor de cálculo, importación)"]
            S["Services<br/>(AltaDeEmpleado, ConsultaDni)"]
            M["Models Eloquent<br/>(+ trait Auditable)"]
        end
        Q["cola<br/>correos de aviso"]
        R["reloj<br/>tareas diarias"]
    end
    DB[("MySQL 8.4<br/>+ trigger de historial")]

    A -->|"HTTPS + Bearer"| N --> MW --> C
    C --> T --> M
    C --> S --> M
    C --> M --> DB
    M -.->|"jobs"| Q
    R -.-> DB
```

### Patrones que usa el proyecto

| Patrón | Dónde | Para qué |
|---|---|---|
| **MVC** | Laravel: `Models` / `Controllers` / JSON como vista | Separar datos, lógica HTTP y presentación |
| **Service layer** | `app/Services` | Reglas que varios controladores comparten (alta de empleado) |
| **Trait (composición)** | `CalculaConceptosPlanilla`, `GeneraPlanillasEnLote`, `Auditable`, `ListadoPaginado` | Reutilizar comportamiento sin herencia profunda |
| **Observer** | eventos de modelo en `Auditable` (`created`/`updated`/`deleted`) | La auditoría se anota sola, sin tocar cada controlador |
| **Middleware / Chain of Responsibility** | `rol`, `terminos`, `clave_nueva`, `sesion` | Cada capa decide si la petición sigue |
| **Interceptor** | `auth.interceptor.ts` | Token, verbos, 401/423/428 en un solo sitio |
| **Guard** | `auth.guard.ts` | Proteger rutas del frontend por sesión y por rol |
| **Componentes compartidos** | `data-table`, `form-modal`, `wizard` | Una sola tabla y un solo modal para todas las pantallas |
| **Single source of truth** | `ConceptosDePago`, `Meses` | El nombre de un concepto vive en un solo archivo |

---

## 2. Casos de uso

```mermaid
flowchart LR
    T(("Trabajador"))
    H(("RR.HH."))
    A(("Administrador"))

    subgraph Sistema["CATA-Recibo"]
        direction TB
        U1["Iniciar sesión y cambiar clave"]
        U2["Aceptar términos de uso"]
        U3["Ver y descargar mis boletas"]
        U4["Firmar boleta o contrato"]
        U5["Pedir vacaciones"]
        U6["Subir mi hoja de vida"]
        U7["Gestionar empleados y contratos"]
        U8["Importar empleados desde Excel"]
        U9["Generar planilla del mes"]
        U10["Importar conceptos desde Excel"]
        U11["Generar y enviar boletas"]
        U12["Aprobar vacaciones"]
        U13["Ver el panel de control"]
        U14["Administrar roles y usuarios"]
        U15["Consultar la auditoría"]
    end

    T --> U1 & U2 & U3 & U4 & U5 & U6
    H --> U1 & U7 & U8 & U9 & U10 & U11 & U12 & U13
    A --> U14 & U15
    A -.->|"incluye lo de RR.HH."| H
```

---

## 3. Clases del dominio

Solo las clases del negocio y sus relaciones (las columnas completas están en el
modelo de datos).

```mermaid
classDiagram
    class Empleado {
        +uuid id
        +string dni
        +decimal sueldo_base
        +string sistema_pensiones
        +string afp
        +bool tiene_hijos
        +puedeTomarVacaciones() bool
        +tipoContratoVigente() string
        +quitarAcceso()
    }
    class Contrato { +string tipo_contrato +string estado +date fecha_inicio }
    class User { +string email +bool debe_cambiar_password +terminosAlDia() bool }
    class Rol { +string nombre }
    class Planilla {
        +int mes
        +int anio
        +decimal sueldo_base
        +decimal total
        +recalcularTotal() float
    }
    class PayrollDetalle { +decimal monto_calculado +string calculo }
    class PaymentConcept { +string nombre +string tipo +bool aplica_a_todos }
    class PlanillaCorrida { +string nombre }
    class Periodo { +int anio }
    class Documento { +string tipo +string estado_firma }
    class Vacacion { +date desde +date hasta +string estado }
    class Auditoria { +string accion +string entidad +json cambios }
    class PlanillaHistorial { +decimal total_anterior +decimal total_nuevo }

    Empleado "1" --> "*" Contrato
    Empleado "1" --> "0..1" User
    User "*" --> "1" Rol
    Empleado "1" --> "*" Planilla
    Empleado "1" --> "*" Documento
    Empleado "1" --> "*" Vacacion
    Planilla "1" --> "*" PayrollDetalle
    PayrollDetalle "*" --> "1" PaymentConcept
    PlanillaCorrida "1" --> "*" Planilla
    Periodo "1" --> "*" Planilla
    Planilla ..> PlanillaHistorial : trigger de la BD
    User ..> Auditoria : anota (trait Auditable)
    Empleado ..> Auditoria : anota (trait Auditable)
```

---

## 4. Secuencia: iniciar sesión

Es la puerta abierta a internet, por eso los tres rechazos contestan igual y
gastan el mismo tiempo (OWASP: no enumerar usuarios).

```mermaid
sequenceDiagram
    actor U as Persona
    participant F as Angular (AuthService)
    participant TH as throttle:ingreso
    participant AC as AuthController
    participant DB as MySQL

    U->>F: correo + contraseña
    F->>TH: POST /api/login
    alt demasiados intentos
        TH-->>F: 429
    else
        TH->>AC: login()
        AC->>DB: buscar usuario por correo
        alt no existe / clave mala / cuenta inactiva
            AC-->>F: 422 "Credenciales incorrectas" (mismo texto)
        else correcto
            AC->>DB: borrar tokens anteriores
            AC->>DB: crear token con vencimiento
            AC-->>F: token + usuario + debe_cambiar_password
            F->>F: guardar sesión, elegir ruta según rol y estado
        end
    end
```

## 5. Secuencia: generar la planilla de un mes

```mermaid
sequenceDiagram
    actor R as RR.HH.
    participant F as Angular
    participant PC as PlanillaCorridaController
    participant GL as GeneraPlanillasEnLote
    participant CC as CalculaConceptosPlanilla
    participant PL as Planilla
    participant DB as MySQL

    R->>F: "Generar" para una corrida y un mes
    F->>PC: POST /api/payroll-runs/{id}/generate
    PC->>GL: generarLote(empleados, mes, año)
    loop por cada empleado
        GL->>DB: crear planilla
        GL->>CC: generarConceptosAutomaticos()
        CC->>CC: pensión (ONP/AFP), EsSalud, asignación familiar,<br/>gratificación, renta de 5ta
        CC->>DB: crear líneas (PayrollDetalle)
        GL->>PL: recalcularTotal()
        PL->>DB: UPDATE total
        DB->>DB: trigger → planilla_historial
    end
    PC-->>F: generadas / omitidas
    F->>R: aviso según resultado (éxito, parcial o error)
```

## 6. Estados de una boleta

```mermaid
stateDiagram-v2
    [*] --> Generada: RR.HH. genera la planilla
    Generada --> Avisada: correo al trabajador
    Avisada --> Vista: el trabajador la abre
    Vista --> Descargada
    Vista --> Firmada: firma dibujada + contraseña
    Descargada --> Firmada
    Firmada --> [*]
```

## 7. Despliegue

```mermaid
flowchart TB
    subgraph Host["docker compose up -d --build"]
        web["web<br/>nginx"] --> app["app<br/>php-fpm (Laravel)"]
        cola["cola<br/>queue:work"] --> base
        reloj["reloj<br/>schedule:work"] --> base
        app --> base[("base<br/>MySQL 8.4<br/>volumen datos-mysql")]
    end
    Usuario(("Navegador")) -->|"80/443"| web
```

Las tres piezas de PHP salen de la **misma imagen**, así que no pueden quedar
desincronizadas entre ellas. Detalle en [DESPLIEGUE.md](../DESPLIEGUE.md).
