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
use App\Http\Controllers\IdentidadFirmaController;

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

// "Olvide mi contrasena": pedir el enlace y usarlo. Van con freno aparte
// porque son las otras dos puertas abiertas a internet; el broker de Laravel
// ademas no deja pedir dos enlaces seguidos (auth.passwords.users.throttle).
Route::post('/forgot-password',      [AuthController::class, 'olvidePassword'])->middleware('throttle:recuperacion');
Route::post('/reset-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:recuperacion');

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

    // Las cifras del Panel de Control. Solo admin y RRHH: son datos de toda
    // la nomina, igual que el modulo que lo muestra en el menu.
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('rol:admin,rrhh');
    // El mismo panel en Excel, con el filtro puesto.
    Route::get('/dashboard/export', [DashboardController::class, 'exportar'])->middleware('rol:admin,rrhh');

    // Empleado autenticado — sus propios datos
    Route::get('my-payroll',              [MiPlanillaController::class, 'index']);
    Route::get('my-payslips/{month}/{year}', [MiBoletaController::class, 'descargar']);
    Route::get('my-documents',           [MisDocumentosController::class, 'index']);

    // Los avisos del trabajador (la campana). Cada quien ve solo los suyos:
    // el user_id sale del token, no de la petición.
    Route::get('my-notifications',                    [MisNotificacionesController::class, 'index']);
    Route::patch('my-notifications/{id}/read',       [MisNotificacionesController::class, 'leida']);
    Route::post('my-notifications/mark-all',      [MisNotificacionesController::class, 'marcarTodas']);
    Route::patch('my-documents/{id}/viewed',  [MisDocumentosController::class, 'visto']);
    Route::post('my-documents/{id}/sign',  [MisDocumentosController::class, 'firmar']);
    // El trabajador sube su propio CV; el empleado sale del token.
    Route::post('my-documents/resume', [MisDocumentosController::class, 'subirHojaDeVida']);
    Route::get('my-modules', [MisModulosController::class, 'index']);
    Route::put('change-password', [AuthController::class, 'cambiarPassword']);

    // Los términos de uso: el papel que antes se firmaba a mano. Los firma
    // cada trabajador desde su cuenta, así que van entre las rutas de
    // cualquier autenticado y no en el grupo de RR.HH.
    Route::get('terms',          [TerminosController::class, 'mostrar']);
    Route::post('terms/accept', [TerminosController::class, 'aceptar']);
    // El propio empleado registra/actualiza su firma y/o huella (RRHH tiene su
    // propio endpoint equivalente para hacerlo por cualquier empleado, más abajo).
    Route::post('my-signature', [IdentidadFirmaController::class, 'subirMia']);
    // La imagen guardada, para poder enseñársela a quien la dibujó. Va por su
    // propia ruta porque está en el disco privado; el permiso se revisa dentro.
    Route::get('my-signature-image',             [IdentidadFirmaController::class, 'ver']);
    Route::get('employees/{id}/signature-image', [IdentidadFirmaController::class, 'ver']);

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
    // de propiedad se hace dentro del controller, por eso vive fuera del grupo rol:.
    // Abrirlo en pantalla (siempre) y bajárselo (el suyo, ya firmado).
    Route::get('documents/{id}/view',       [DocumentoController::class, 'ver']);
    Route::get('documents/{id}/download', [DocumentoController::class, 'descargar']);

    // ── Solo RRHH y Administrador ───────────────────────────────
    Route::middleware('rol:rrhh,admin')->group(function () {
        // Boleta individual de cualquier empleado — antes vivía fuera de este grupo
        // y cualquier autenticado (incluido un empleado normal) podía descargar la
        // boleta de otro con solo cambiar el empleado_id en la URL. El autoservicio
        // real del empleado es mis-boletas/{mes}/{anio}, que sí valida ownership.
        Route::get('payslips/{employee_id}/{month}/{year}', [BoletaController::class, 'generar']);

        // Solo lectura: RRHH necesita ver los roles para elegir el rol_id
        // al crear un empleado o usuario, pero no puede crear/editar/borrar roles
        // (eso sigue siendo exclusivo de admin, por la escalación de privilegios que ya arreglamos).
        Route::apiResource('roles', RolController::class)->only(['index', 'show']);

        Route::apiResource('contracts', ContratoController::class);

        Route::apiResource('users', UserController::class)->except(['store']);
        // RR.HH. le repone la contrasena a quien se quedo fuera. Queda obligado
        // a cambiarla al entrar, y se le cierran las sesiones abiertas.
        Route::post('users/{id}/reset-password', [UserController::class, 'restablecerPassword']);
        // Buscar a la persona por su DNI antes de darla de alta. Va limitada
        // porque cada consulta que no esté en la base propia se le paga a
        // Decolecta: sin freno, un script podría vaciar el saldo.
        Route::get('dni-lookup/{dni}', ConsultaDniController::class)
            ->middleware('throttle:consulta_dni');

        Route::post('employees/{id}/signature', [IdentidadFirmaController::class, 'subir']);
        // La lista del personal en CSV. Va ANTES del apiResource, igual que
        // la de planilla: si no, "exportar" entraría por show({id}).
        Route::get('employees/export',       [EmpleadoController::class, 'exportar']);
        Route::apiResource('employees',        EmpleadoController::class);

        // La planilla completa en CSV. Va ANTES del apiResource: si no,
        // "exportar" entraría por show({id}) y devolvería un 404 buscando
        // una planilla con ese id.
        Route::get('payrolls/export',        [PlanillaController::class, 'exportar']);
        Route::apiResource('payrolls',         PlanillaController::class);

        // Las corridas: la planilla con nombre que agrupa a un grupo de gente
        // ("Planilla TIC — Septiembre 2026"). Las rutas sueltas van ANTES del
        // apiResource: si no, "sacar" entraría por show({id}) y devolvería un
        // 404 buscando una corrida con ese id.
        Route::post('payroll-runs/detach',           [PlanillaCorridaController::class, 'sacar']);
        Route::post('payroll-runs/{id}/generate',    [PlanillaCorridaController::class, 'generar']);
        Route::post('payroll-runs/{id}/move',      [PlanillaCorridaController::class, 'mover']);
        Route::apiResource('payroll-runs', PlanillaCorridaController::class);
        // Documentos del personal: el expediente de cada trabajador, ordenado
        // por persona (hoja de vida, contratos, boletas). Ver ExpedienteController.
        Route::get('employee-files',              [\App\Http\Controllers\ExpedienteController::class, 'index']);
        Route::get('employee-files/{employeeId}', [\App\Http\Controllers\ExpedienteController::class, 'show']);
        // Subir un archivo que llega de fuera (hoja de vida, contrato
        // escaneado). Va ANTES del apiResource: si no, "subir" entraría por
        // show({id}) y devolvería un 404 buscando un documento con ese id.
        Route::post('documents/upload',        [DocumentoController::class, 'subir']);
        Route::apiResource('documents',       DocumentoController::class);
        Route::post('documents/{id}/sign-as-employer', [DocumentoController::class, 'firmarComoEmpleador']);
        Route::apiResource('areas',            AreaController::class);
        Route::apiResource('positions',           CargoController::class);
        Route::apiResource('periods',         PeriodoController::class);
        Route::post('periods/{id}/generate-payroll', [PeriodoController::class, 'generarPlanilla']);
        Route::apiResource('payment-concepts', PaymentConceptController::class);
        Route::post('payment-concepts/{id}/apply-to-group', [PaymentConceptController::class, 'aplicarAGrupo']);
        // Deja las líneas de UNA planilla como diga la pantalla, de una vez.
        // Va antes del apiResource de planilla por el mismo motivo de siempre:
        // con un segmento más, no choca con show({id}).
        Route::post('payrolls/{id}/concepts', [PlanillaController::class, 'sincronizarConceptos']);
        // Importar conceptos desde el Excel de RR.HH.: todas las planillas del
        // mes de una vez. Tres pasos; el Excel se lee en el navegador y solo
        // llegan sus celdas. Ver ImportacionConceptosController. "modelo" es
        // el Excel para llenar, con la gente del mes y una columna por concepto.
        Route::get('concept-import/template',         [\App\Http\Controllers\ImportacionConceptosController::class, 'modelo']);
        Route::post('concept-import/recognize',    [\App\Http\Controllers\ImportacionConceptosController::class, 'reconocer']);
        Route::post('concept-import/preview', [\App\Http\Controllers\ImportacionConceptosController::class, 'previsualizar']);
        Route::post('concept-import/apply',       [\App\Http\Controllers\ImportacionConceptosController::class, 'aplicar']);
        // Importar empleados desde Excel (altas y actualizaciones por DNI) y sus
        // hojas de vida en lote, con el DNI en el nombre de cada archivo.
        Route::get('employee-import/template',         [\App\Http\Controllers\ImportacionEmpleadosController::class, 'modelo']);
        Route::post('employee-import/recognize',    [\App\Http\Controllers\ImportacionEmpleadosController::class, 'reconocer']);
        Route::post('employee-import/preview', [\App\Http\Controllers\ImportacionEmpleadosController::class, 'previsualizar']);
        Route::post('employee-import/apply',       [\App\Http\Controllers\ImportacionEmpleadosController::class, 'aplicar']);
        Route::post('employee-import/resume',  [\App\Http\Controllers\ImportacionEmpleadosController::class, 'hojaDeVida']);
        // Boletas y contratos de antes del sistema, en lote: el navegador lee
        // cada PDF y aquí se decide de quién es. Ver DocumentosAnterioresController.
        Route::post('legacy-documents/preview', [\App\Http\Controllers\DocumentosAnterioresController::class, 'previsualizar']);
        Route::post('legacy-documents',               [\App\Http\Controllers\DocumentosAnterioresController::class, 'subir']);
        Route::apiResource('payroll-details', PayrollDetalleController::class);
        Route::apiResource('campuses',            SedeController::class);

        // Aprobar o rechazar: solo RR.HH. y Administración.
        Route::put('vacations/{id}',    [VacacionController::class, 'update']);

        Route::post('payslips/generate-bulk', [BoletaController::class, 'generarMasivo']);
    });

    // ── Solo Administrador: gestión de roles y del sistema de permisos ──
    Route::middleware('rol:admin')->group(function () {
        // La auditoría: quién cambió qué. Solo lectura, y solo Admin —es
        // justamente donde se ve lo que hizo RR.HH.—.
        Route::get('audit-log', [AuditoriaController::class, 'index']);

        Route::apiResource('roles', RolController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('module-groups', ModuloPadreController::class);
        Route::apiResource('modules', ModuloController::class);
        Route::post('modules/{id}/roles', [ModuloController::class, 'asignarRoles']);
    });

});