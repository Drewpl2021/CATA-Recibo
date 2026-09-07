<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\PlanillaController;
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
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:8,1');

// ── Protegidas ────────────────────────────────────────
// 'sesion' empuja la caducidad del token en cada petición: mientras se
// esté trabajando la sesión no se cae. Ver RenovarSesionActiva.
Route::middleware(['auth:sanctum', 'sesion'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Las cifras del Panel de Control. Solo admin y RRHH: son datos de toda
    // la nomina, igual que el modulo que lo muestra en el menu.
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('rol:admin,rrhh');

    // Empleado autenticado — sus propios datos
    Route::get('mi-planilla',              [MiPlanillaController::class, 'index']);
    Route::get('mis-boletas/{mes}/{anio}', [MiBoletaController::class, 'descargar']);
    Route::get('mis-documentos',           [MisDocumentosController::class, 'index']);

    // Los avisos del trabajador (la campana). Cada quien ve solo los suyos:
    // el user_id sale del token, no de la petición.
    Route::get('mis-notificaciones',                    [MisNotificacionesController::class, 'index']);
    Route::patch('mis-notificaciones/{id}/leida',       [MisNotificacionesController::class, 'leida']);
    Route::post('mis-notificaciones/marcar-todas',      [MisNotificacionesController::class, 'marcarTodas']);
    Route::patch('mis-documentos/{id}/visto',  [MisDocumentosController::class, 'visto']);
    Route::post('mis-documentos/{id}/firmar',  [MisDocumentosController::class, 'firmar']);
    Route::get('mis-modulos', [MisModulosController::class, 'index']);
    Route::put('cambiar-password', [AuthController::class, 'cambiarPassword']);
    // El propio empleado registra/actualiza su firma y/o huella (RRHH tiene su
    // propio endpoint equivalente para hacerlo por cualquier empleado, más abajo).
    Route::post('mi-identidad-firma', [IdentidadFirmaController::class, 'subirMia']);

    // Vacaciones — cualquier autenticado puede ver y solicitar
    Route::get('vacaciones',          [VacacionController::class, 'index']);
    Route::post('vacaciones',         [VacacionController::class, 'store']);
    Route::get('vacaciones/{id}',     [VacacionController::class, 'show']);

    // Descarga de un Documento ya generado (boleta, contrato, etc.) — el propio
    // empleado dueño del documento, o RRHH/admin sobre cualquiera. La verificación
    // de propiedad se hace dentro del controller, por eso vive fuera del grupo rol:.
    Route::get('documentos/{id}/descargar', [DocumentoController::class, 'descargar']);

    // ── Solo RRHH y Administrador ───────────────────────────────
    Route::middleware('rol:rrhh,admin')->group(function () {
        // Boleta individual de cualquier empleado — antes vivía fuera de este grupo
        // y cualquier autenticado (incluido un empleado normal) podía descargar la
        // boleta de otro con solo cambiar el empleado_id en la URL. El autoservicio
        // real del empleado es mis-boletas/{mes}/{anio}, que sí valida ownership.
        Route::get('boleta/{empleado_id}/{mes}/{anio}', [BoletaController::class, 'generar']);

        // Solo lectura: RRHH necesita ver los roles para elegir el rol_id
        // al crear un empleado o usuario, pero no puede crear/editar/borrar roles
        // (eso sigue siendo exclusivo de admin, por la escalación de privilegios que ya arreglamos).
        Route::apiResource('roles', RolController::class)->only(['index', 'show']);

        Route::apiResource('contratos', ContratoController::class);

        Route::apiResource('users', UserController::class)->except(['store']);
        Route::post('empleados/{id}/identidad-firma', [IdentidadFirmaController::class, 'subir']);
        Route::apiResource('empleados',        EmpleadoController::class);
        Route::apiResource('planilla',         PlanillaController::class);
        Route::apiResource('documentos',       DocumentoController::class);
        Route::post('documentos/{id}/firmar-empleador', [DocumentoController::class, 'firmarComoEmpleador']);
        Route::apiResource('areas',            AreaController::class);
        Route::apiResource('cargos',           CargoController::class);
        Route::apiResource('periodos',         PeriodoController::class);
        Route::post('periodos/{id}/generar-planilla', [PeriodoController::class, 'generarPlanilla']);
        Route::apiResource('payment-concepts', PaymentConceptController::class);
        Route::post('payment-concepts/{id}/aplicar-a-grupo', [PaymentConceptController::class, 'aplicarAGrupo']);
        Route::apiResource('payroll-detalles', PayrollDetalleController::class);
        Route::apiResource('sedes',            SedeController::class);

        Route::put('vacaciones/{id}',    [VacacionController::class, 'update']);
        Route::delete('vacaciones/{id}', [VacacionController::class, 'destroy']);

        Route::post('boletas/generar-masivo', [BoletaController::class, 'generarMasivo']);
    });

    // ── Solo Administrador: gestión de roles y del sistema de permisos ──
    Route::middleware('rol:admin')->group(function () {
        Route::apiResource('roles', RolController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('modulos-padre', ModuloPadreController::class);
        Route::apiResource('modulos', ModuloController::class);
        Route::post('modulos/{id}/roles', [ModuloController::class, 'asignarRoles']);
    });

});