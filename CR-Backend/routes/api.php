<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ConsultaDniController;
use App\Http\Controllers\TerminosController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\PlanillaCorridaController;
use App\Http\Controllers\FotoPerfilController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\BoletaController;
use App\Http\Controllers\MiPerfilController;
use App\Http\Controllers\PrimerosPasosController;
use App\Http\Controllers\MiPlanillaController;
use App\Http\Controllers\MiBoletaController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PeriodoController;
use App\Http\Controllers\PaymentConceptController;
use App\Http\Controllers\PayrollDetalleController;
use App\Http\Controllers\MisDocumentosController;
use App\Http\Controllers\MisNotificacionesController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\MisModulosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModuloPadreController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\TipoContratoController;

// Preflight CORS
Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

// ── Públicas ──────────────────────────────────────────
// Con límite de intentos: son las únicas rutas abiertas a internet, y sin
// esto se podía probar contraseñas contra /login sin ningún freno.
// El contador es por IP; al pasarse, Laravel responde 429 con Retry-After.
//
// Cada una lleva un limitador CON NOMBRE (AppServiceProvider) y no un
// 'throttle:5,1' pelado: sin nombre, todas las rutas públicas comparten el
// mismo contador — la clave que arma Laravel para un visitante sin sesión es
// dominio + IP, sin la URL — y errar la contraseña unas veces te dejaba sin
// poder pedir el enlace para reponerla.
// El autorregistro está cerrado. Las cuentas se crean al dar de alta a la
// persona, así que lo único que hacía esto era dejar crear "empleados" nuevos
// desde internet, sin comprobar que el buzón fuera de quien lo escribía.
//
// La respuesta es deliberadamente escueta: esta ruta es pública y sin sesión.
// Antes explicaba quién crea las cuentas y con qué contraseña nacen — dos
// datos que le ahorran la mitad del trabajo a quien quiera entrar sin permiso.
Route::post('/register', fn () => response()->json([
    'success' => false,
    'message' => 'El registro público está deshabilitado. Acceso restringido a personal autorizado. '
        . 'Si requieres asistencia, contacta al administrador del sistema.',
], 403))->middleware('throttle:registro');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:ingreso');

// Lo que abre el QR de la boleta: si es auténtica. Pública y con la dirección
// firmada por el servidor (ver VerificarBoletaController).
// El .zip de boletas por enlace firmado (vence en 10 min): lo baja el
// navegador, sin el token. Ver EmpleadoController::enlaceBoletasEnZip.
Route::get('/payslips-zip/download', [EmpleadoController::class, 'boletasEnZipConEnlace'])
    ->name('boletas.zip')->middleware(['signed:relative', 'throttle:30,1']);

Route::get('/verificar-boleta/{documento}', [\App\Http\Controllers\VerificarBoletaController::class, 'ver'])
    ->name('boleta.verificar')->middleware('throttle:verificacion');

// "Olvide mi contrasena": pedir el enlace y usarlo. Van con freno aparte
// porque son las otras dos puertas abiertas a internet; el broker de Laravel
// ademas no deja pedir dos enlaces seguidos (auth.passwords.users.throttle).
Route::post('/forgot-password',      [AuthController::class, 'olvidePassword'])->middleware('throttle:recuperacion');
Route::post('/reset-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:recuperacion');
// El enlace de «Tu acceso a CATA-Recibo»: crear la contraseña (ver AccesoPorCorreo).
Route::post('/create-password', [AuthController::class, 'crearPassword'])->middleware('throttle:recuperacion');

// ── Protegidas ────────────────────────────────────────
// 'sesion' empuja la caducidad del token en cada petición: mientras se
// esté trabajando la sesión no se cae. Ver RenovarSesionActiva.
// 'clave_nueva' traba a quien sigue con la contrasena que le dieron: solo le
// deja /me, /logout y /cambiar-password hasta que ponga una suya.
// 'terminos' traba a quien no ha firmado los términos de uso. Va DESPUÉS de
// 'clave_nueva': quien además debe cambiar la contraseña firma en esa pantalla.
Route::middleware(['auth:sanctum', 'sesion', 'clave_nueva', 'terminos'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);


    // Empleado autenticado — sus propios datos
    // Su ficha, para la tarjeta de "Mi perfil". Va acá y no en el grupo de
    // RR.HH. porque cada quien mira LO SUYO: pedirla a employees/{id} le
    // daba 403 al trabajador y su perfil salía en blanco.
    Route::get('my-profile',              [MiPerfilController::class, 'ver']);
    // La guía de qué hacer primero, según su rol. El progreso se calcula
    // de los datos; lo único que se guarda es si ya se le abrió sola.
    Route::get('my-first-steps',          [PrimerosPasosController::class, 'ver']);
    Route::post('my-first-steps/seen',    [PrimerosPasosController::class, 'marcarVista']);
    Route::get('my-payroll',              [MiPlanillaController::class, 'index']);
    Route::get('my-payslips/{month}/{year}', [MiBoletaController::class, 'descargar']);
    Route::get('my-documents',           [MisDocumentosController::class, 'index']);

    // Los avisos del trabajador (la campana). Cada quien ve solo los suyos:
    // el user_id sale del token, no de la petición.
    Route::get('my-notifications',                    [MisNotificacionesController::class, 'index']);
    Route::patch('my-notifications/{id}/read',       [MisNotificacionesController::class, 'leida']);
    Route::post('my-notifications/mark-all',      [MisNotificacionesController::class, 'marcarTodas']);
    Route::patch('my-documents/{id}/viewed',  [MisDocumentosController::class, 'visto']);
    Route::post('my-documents/{id}/sign',  [MisDocumentosController::class, 'firmar'])->middleware('throttle:clave');
    // El trabajador sube su propio CV; el empleado sale del token.
    Route::post('my-documents/resume', [MisDocumentosController::class, 'subirHojaDeVida']);
    Route::get('my-modules', [MisModulosController::class, 'index']);
    Route::put('change-password', [AuthController::class, 'cambiarPassword'])->middleware('throttle:clave');
    // Confirma la CLAVE de quien está en la sesión, sin cambiar nada. La usa
    // Emisión de Boletas para destrabar la edición de una boleta ya emitida.
    Route::post('verify-password', [AuthController::class, 'verificarPassword'])->middleware('throttle:clave');

    // Cuánto lleva un proceso largo que pidió esta misma cuenta (ver App\Support\Progreso).
    Route::get('progress/{id}', [\App\Http\Controllers\ProgresoController::class, 'ver'])->where('id', '[A-Za-z0-9-]{8,64}');

    // Los términos de uso: el papel que antes se firmaba a mano. Los firma
    // cada trabajador desde su cuenta, así que van entre las rutas de
    // cualquier autenticado y no en el grupo de RR.HH.
    Route::get('terms',          [TerminosController::class, 'mostrar']);
    Route::post('terms/accept', [TerminosController::class, 'aceptar']);
    // La foto de perfil. Cada quien sube y quita LA SUYA; la imagen se sirve
    // por su propia ruta porque vive en el disco privado y hay que comprobar
    // antes quién la pide. "users/{id}/foto" no choca con el apiResource de
    // users: tiene un segmento más.
    Route::post('my-photo',        [FotoPerfilController::class, 'subirMia']);
    Route::delete('my-photo',      [FotoPerfilController::class, 'quitarMia']);
    Route::get('users/{id}/photo', [FotoPerfilController::class, 'ver']);

    // Vacaciones — el trabajador ve y pide LAS SUYAS; RR.HH. ve las de todos.
    // El recorte se hace dentro del controlador, con el empleado del token.
    // 'saldo' va antes que '{id}' o la ruta con parametro se lo comeria.
    Route::get('vacations/balance',    [VacacionController::class, 'saldo']);
    Route::get('vacations',          [VacacionController::class, 'index']);
    Route::post('vacations',         [VacacionController::class, 'store']);
    Route::get('vacations/{id}',     [VacacionController::class, 'show']);
    // Retirar una solicitud: RR.HH. cualquiera; el trabajador solo la suya y
    // mientras siga pendiente (se comprueba dentro del controlador).
    Route::delete('vacations/{id}',  [VacacionController::class, 'destroy']);

    // Descarga de un Documento ya generado (boleta, contrato, etc.) — el propio
    // empleado dueño del documento, o RRHH/admin sobre cualquiera. La verificación
    // de propiedad se hace dentro del controller, por eso no pide un módulo.
    // Abrirlo en pantalla (siempre) y bajárselo (el suyo, ya firmado).
    Route::get('documents/{id}/view',       [DocumentoController::class, 'ver']);
    Route::get('documents/{id}/download', [DocumentoController::class, 'descargar']);

    // ══ Permisos por módulo ══════════════════════════════════════
    //
    // Cada grupo pide el módulo de su pantalla («permiso:/planillas»): entra
    // quien lo tenga marcado en su rol (Configuración → Roles), o el
    // superadministrador. Antes se preguntaba por el NOMBRE del rol
    // («rol:rrhh,admin») y un rol nuevo, como Contabilidad, quedaba fuera
    // aunque tuviera el módulo en el menú. Ver App\Support\Permisos.
    //
    // «@gestion» = cualquier módulo de trabajo (todo menos Mi Espacio): para
    // lo que leen muchas pantallas, como los catálogos o la lista del personal.

    // ── Panel de Control ──
    Route::middleware('permiso:/dashboard')->group(function () {
        Route::get('/dashboard',        [DashboardController::class, 'index']);
        Route::get('/dashboard/export', [DashboardController::class, 'exportar']);
    });

    // ── Empleados ──
    Route::middleware('permiso:/empleados')->group(function () {
        // La foto de perfil del trabajador, puesta por RR.HH. desde su ficha.
        Route::post('employees/{id}/photo',   [FotoPerfilController::class, 'subirDeEmpleado']);
        Route::delete('employees/{id}/photo', [FotoPerfilController::class, 'quitarDeEmpleado']);
        // La lista del personal en Excel (los marcados van en el cuerpo).
        Route::get('employees/export',       [EmpleadoController::class, 'exportar']);
        Route::post('employees/export',      [EmpleadoController::class, 'exportar']);
        // «Enviar acceso por correo» a los marcados.
        Route::post('employees/send-access', [EmpleadoController::class, 'enviarAcceso'])->middleware('throttle:30,1');
        // Activar o dar de baja a varios marcados en la lista, de una vez.
        Route::post('employees/status', [EmpleadoController::class, 'cambiarEstado']);
        Route::apiResource('employees', EmpleadoController::class)->except(['index', 'show']);
        // Importar empleados desde Excel (altas y actualizaciones por DNI) y sus
        // hojas de vida en lote, con el DNI en el nombre de cada archivo.
        Route::get('employee-import/template',  [\App\Http\Controllers\ImportacionEmpleadosController::class, 'modelo']);
        Route::post('employee-import/recognize', [\App\Http\Controllers\ImportacionEmpleadosController::class, 'reconocer']);
        Route::post('employee-import/preview',   [\App\Http\Controllers\ImportacionEmpleadosController::class, 'previsualizar']);
        Route::post('employee-import/apply',     [\App\Http\Controllers\ImportacionEmpleadosController::class, 'aplicar']);
        Route::post('employee-import/resume',    [\App\Http\Controllers\ImportacionEmpleadosController::class, 'hojaDeVida']);
    });

    // Buscar a la persona por su DNI antes de darla de alta (Empleados o
    // Usuarios). Va limitada: cada consulta fuera de la base propia se le
    // paga a Decolecta, y sin freno un script podría vaciar el saldo.
    Route::get('dni-lookup/{dni}', ConsultaDniController::class)
        ->middleware(['permiso:/empleados,/usuarios', 'throttle:consulta_dni']);

    // ── Contratos (también se crean desde la ficha del empleado) ──
    Route::middleware('permiso:/contratos,/empleados,/documentos')->group(function () {
        Route::apiResource('contracts', ContratoController::class);
    });

    // ── Planillas ──
    Route::middleware('permiso:/planillas')->group(function () {
        // La planilla completa en Excel. Va ANTES del apiResource: si no,
        // "exportar" entraría por show({id}).
        Route::get('payrolls/export',  [PlanillaController::class, 'exportar']);
        Route::post('payrolls/export', [PlanillaController::class, 'exportar']);
        // La Renta de 5ta del mes sin guardar nada, para la vista previa.
        Route::get('payrolls/renta-5ta', [PlanillaController::class, 'rentaQuinta']);
        Route::apiResource('payrolls', PlanillaController::class)->except(['index', 'show']);
        // Las corridas: la planilla con nombre que agrupa a un grupo de gente.
        // Las rutas sueltas van ANTES del apiResource.
        Route::post('payroll-runs/detach',        [PlanillaCorridaController::class, 'sacar']);
        Route::post('payroll-runs/{id}/generate', [PlanillaCorridaController::class, 'generar']);
        Route::post('payroll-runs/{id}/move',     [PlanillaCorridaController::class, 'mover']);
        Route::apiResource('payroll-runs', PlanillaCorridaController::class);
        Route::post('periods/{id}/generate-payroll', [PeriodoController::class, 'generarPlanilla']);
        // Deja las líneas de UNA planilla como diga la pantalla, de una vez.
        Route::post('payrolls/{id}/concepts',  [PlanillaController::class, 'sincronizarConceptos']);
        // Vuelve a leer el sueldo de la ficha y lo prorratea de nuevo.
        Route::put('payrolls/{id}/recalcular', [PlanillaController::class, 'recalcular']);
        Route::apiResource('payroll-details', PayrollDetalleController::class);
        // Importar conceptos desde el Excel de RR.HH. y el PLAME del mes.
        Route::get('concept-import/template',   [\App\Http\Controllers\ImportacionConceptosController::class, 'modelo']);
        Route::post('concept-import/recognize', [\App\Http\Controllers\ImportacionConceptosController::class, 'reconocer']);
        Route::post('concept-import/preview',   [\App\Http\Controllers\ImportacionConceptosController::class, 'previsualizar']);
        Route::post('concept-import/apply',     [\App\Http\Controllers\ImportacionConceptosController::class, 'aplicar']);
        Route::post('plame-import/preview', [\App\Http\Controllers\ImportacionPlameController::class, 'previsualizar']);
        Route::post('plame-import/apply',   [\App\Http\Controllers\ImportacionPlameController::class, 'aplicar']);
    });
    // Las planillas de cada mes también las leen Boletas, la 5ta, la CTS y la gratificación.
    Route::middleware('permiso:/planillas,/boletas,/renta-5ta,/cts,/gratificaciones,/dashboard')->group(function () {
        Route::apiResource('payrolls', PlanillaController::class)->only(['index', 'show']);
    });
    // Aplicar un concepto a un grupo: desde Planillas o desde Conceptos de Pago.
    Route::post('payment-concepts/{id}/apply-to-group', [PaymentConceptController::class, 'aplicarAGrupo'])
        ->middleware('permiso:/planillas,/conceptos-pago');

    // ── Boletas (emisión, firma digital del colegio y constancia) ──
    Route::middleware('permiso:/boletas')->group(function () {
        Route::post('payslips/generate-bulk', [BoletaController::class, 'generarMasivo']);
        // Las boletas YA EMITIDAS del mes, todas en un .zip.
        Route::get('employees/payslips-zip', [EmpleadoController::class, 'boletasEnZip']);
        Route::match(['get', 'post'], 'employees/payslips-zip/link', [EmpleadoController::class, 'enlaceBoletasEnZip']);
        Route::get('payslips/signed/summary',    [\App\Http\Controllers\BoletasFirmadasController::class, 'resumen']);
        Route::post('payslips/signed/check',     [\App\Http\Controllers\BoletasFirmadasController::class, 'revisar']);
        Route::post('payslips/signed',           [\App\Http\Controllers\BoletasFirmadasController::class, 'guardar']);
        // «Firmar aquí», con el certificado de quien lo pide, por tandas.
        Route::post('payslips/signed/sign-here', [\App\Http\Controllers\BoletasFirmadasController::class, 'firmarAqui'])
            ->middleware('throttle:120,1');
        // Mi certificado digital para firmar desde el sistema (cada uno el suyo).
        Route::get('my-signing-certificate',    [\App\Http\Controllers\CertificadoDeFirmaController::class, 'mostrar']);
        Route::post('my-signing-certificate',   [\App\Http\Controllers\CertificadoDeFirmaController::class, 'guardar'])
            ->middleware('throttle:10,1');
        Route::delete('my-signing-certificate', [\App\Http\Controllers\CertificadoDeFirmaController::class, 'quitar']);
        Route::post('payslips/{documento}/void', [\App\Http\Controllers\BoletasFirmadasController::class, 'anular'])->whereUuid('documento');
        Route::get('payslips/delivery-record',   [\App\Http\Controllers\BoletasFirmadasController::class, 'constancia']);
    });
    // La boleta de cualquier empleado: Boletas, Planillas y su expediente. El
    // trabajador tiene la suya en my-payslips, que sí comprueba que sea suya.
    Route::get('payslips/{employee_id}/{month}/{year}', [BoletaController::class, 'generar'])
        ->middleware('permiso:/boletas,/planillas,/documentos');

    // ── Documentos: el expediente de cada trabajador ──
    Route::middleware('permiso:/documentos')->group(function () {
        Route::get('employee-files',              [\App\Http\Controllers\ExpedienteController::class, 'index']);
        Route::get('employee-files/{employeeId}', [\App\Http\Controllers\ExpedienteController::class, 'show']);
        // Boletas y contratos de antes del sistema, en lote.
        Route::post('legacy-documents/preview', [\App\Http\Controllers\DocumentosAnterioresController::class, 'previsualizar']);
        Route::post('legacy-documents',         [\App\Http\Controllers\DocumentosAnterioresController::class, 'subir']);
    });
    // Subir y ver documentos: desde el expediente, la ficha del empleado o Contratos.
    Route::middleware('permiso:/documentos,/empleados,/contratos')->group(function () {
        // Va ANTES del apiResource: si no, "subir" entraría por show({id}).
        Route::post('documents/upload', [DocumentoController::class, 'subir']);
        Route::apiResource('documents', DocumentoController::class);
    });

    // ── Vacaciones: aprobar o rechazar ──
    Route::put('vacations/{id}', [VacacionController::class, 'update'])->middleware('permiso:/vacaciones');

    // ── Renta de 5ta (y enero y febrero de antes del sistema, que se cargan en Ajustes) ──
    Route::middleware('permiso:/renta-5ta')->group(function () {
        Route::get('income-tax',                    [\App\Http\Controllers\Renta5taController::class, 'index']);
        Route::get('income-tax/history/template',   [\App\Http\Controllers\Renta5taController::class, 'modeloHistorial']);
        Route::post('income-tax/history',           [\App\Http\Controllers\Renta5taController::class, 'cargarHistorial']);
        Route::post('income-tax/recalculate',       [\App\Http\Controllers\Renta5taController::class, 'recalcular']);
        Route::get('income-tax/export',             [\App\Http\Controllers\Renta5taController::class, 'exportar']);
        Route::get('income-tax/{empleado}',         [\App\Http\Controllers\Renta5taController::class, 'show'])->whereUuid('empleado');
        Route::put('income-tax/{empleado}/history', [\App\Http\Controllers\Renta5taController::class, 'guardarHistorial'])->whereUuid('empleado');
    });
    Route::middleware('permiso:/renta-5ta,/ajustes')->group(function () {
        Route::get('renta-5ta/previous',  [\App\Http\Controllers\RentaQuintaPreviaController::class, 'index']);
        Route::post('renta-5ta/previous', [\App\Http\Controllers\RentaQuintaPreviaController::class, 'store']);
    });

    // ── CTS ── (las rutas fijas van antes de {liquidacion})
    Route::middleware('permiso:/cts')->group(function () {
        Route::get('cts',                          [\App\Http\Controllers\CtsController::class, 'index']);
        Route::post('cts/calculate',               [\App\Http\Controllers\CtsController::class, 'calcular']);
        Route::post('cts/deposit',                 [\App\Http\Controllers\CtsController::class, 'depositar']);
        Route::delete('cts/{liquidacion}/deposit', [\App\Http\Controllers\CtsController::class, 'anularDeposito'])->whereUuid('liquidacion');
        Route::get('cts/{liquidacion}/sheet',      [\App\Http\Controllers\CtsController::class, 'hoja'])->whereUuid('liquidacion');
        Route::get('cts/{empleado}/history',       [\App\Http\Controllers\CtsController::class, 'historial'])->whereUuid('empleado');
    });

    // ── Gratificaciones ──
    Route::middleware('permiso:/gratificaciones')->group(function () {
        Route::get('bonuses',                    [\App\Http\Controllers\GratificacionController::class, 'index']);
        Route::post('bonuses/deliver',           [\App\Http\Controllers\GratificacionController::class, 'entregar']);
        Route::get('bonuses/{empleado}/history', [\App\Http\Controllers\GratificacionController::class, 'historial'])->whereUuid('empleado');
        Route::get('bonuses/{empleado}/sheet',   [\App\Http\Controllers\GratificacionController::class, 'hoja'])->whereUuid('empleado');
    });

    // ── Los catálogos de Configuración: cambiarlos pide su módulo ──
    Route::apiResource('areas',          AreaController::class)->except(['index', 'show'])->middleware('permiso:/areas');
    Route::apiResource('positions',      CargoController::class)->except(['index', 'show'])->middleware('permiso:/cargos');
    Route::apiResource('contract-types', TipoContratoController::class)->except(['index', 'show'])->middleware('permiso:/tipos-contrato');
    Route::apiResource('campuses',       SedeController::class)->except(['index', 'show'])->middleware('permiso:/sedes');
    Route::apiResource('periods',        PeriodoController::class)->except(['index', 'show'])->middleware('permiso:/periodos');
    Route::apiResource('payment-concepts', PaymentConceptController::class)->except(['index', 'show'])->middleware('permiso:/conceptos-pago');

    // ── Ajustes del sistema y montos de ley (cada cambio queda en la Auditoría) ──
    Route::middleware('permiso:/ajustes')->group(function () {
        Route::put('settings', [\App\Http\Controllers\ConfiguracionController::class, 'update']);
        Route::post('legal-values',       [\App\Http\Controllers\ValorLegalController::class, 'store']);
        Route::put('legal-values/{anio}', [\App\Http\Controllers\ValorLegalController::class, 'update'])->whereNumber('anio');
    });

    // ── Usuarios (las cuentas de acceso) ──
    Route::middleware('permiso:/usuarios,/empleados')->group(function () {
        Route::apiResource('users', UserController::class)->except(['store']);
        // Reponerle la contraseña a quien se quedó fuera. Queda obligado a
        // cambiarla al entrar, y se le cierran las sesiones abiertas.
        Route::post('users/{id}/reset-password', [UserController::class, 'restablecerPassword']);
    });

    // ── Auditoría ──
    Route::middleware('permiso:/auditoria')->group(function () {
        Route::get('audit-log',      [AuditoriaController::class, 'index']);
        Route::get('audit-log/{id}', [AuditoriaController::class, 'show'])->whereNumber('id');
    });

    // ── Roles y módulos: el sistema de permisos mismo ──
    Route::apiResource('roles', RolController::class)->only(['store', 'update', 'destroy'])->middleware('permiso:/roles');
    Route::middleware('permiso:/modulos,/modulos-padre,/roles')->group(function () {
        Route::apiResource('module-groups', ModuloPadreController::class)->only(['index', 'show']);
        Route::apiResource('modules', ModuloController::class)->only(['index', 'show']);
    });
    Route::apiResource('module-groups', ModuloPadreController::class)->except(['index', 'show'])->middleware('permiso:/modulos-padre');
    Route::apiResource('modules', ModuloController::class)->except(['index', 'show'])->middleware('permiso:/modulos');
    // Qué roles ven un módulo: es dar o quitar permisos.
    Route::post('modules/{id}/roles', [ModuloController::class, 'asignarRoles'])->middleware('permiso:/modulos,/roles');

    // ── Lo que leen muchas pantallas ── (al final: después de las rutas fijas como employees/export)
    Route::middleware('permiso:@gestion')->group(function () {
        // La lista del personal: la usan los buscadores y filtros de casi
        // todas las pantallas. Cambiarla pide Empleados (más abajo).
        Route::apiResource('employees', EmpleadoController::class)->only(['index', 'show']);
        // Los catálogos, para los desplegables. Cambiarlos pide su módulo.
        Route::apiResource('areas',          AreaController::class)->only(['index', 'show']);
        Route::apiResource('positions',      CargoController::class)->only(['index', 'show']);
        Route::apiResource('contract-types', TipoContratoController::class)->only(['index', 'show']);
        Route::apiResource('campuses',       SedeController::class)->only(['index', 'show']);
        Route::apiResource('periods',        PeriodoController::class)->only(['index', 'show']);
        Route::apiResource('payment-concepts', PaymentConceptController::class)->only(['index', 'show']);
        // Solo lectura: para elegir el rol al crear un empleado o un usuario.
        // Crear, editar o borrar roles pide el módulo Roles.
        Route::apiResource('roles', RolController::class)->only(['index', 'show']);
        // Los ajustes y los montos de ley los consultan la planilla, la 5ta y la CTS.
        Route::get('settings', [\App\Http\Controllers\ConfiguracionController::class, 'index']);
        Route::get('legal-values',        [\App\Http\Controllers\ValorLegalController::class, 'index']);
        Route::get('legal-values/{anio}', [\App\Http\Controllers\ValorLegalController::class, 'show'])->whereNumber('anio');
    });

});