<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DescuentoController;
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
use App\Http\Controllers\SedeController;
use App\Http\Controllers\MisModulosController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModuloPadreController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\ContratoController;

// Preflight CORS
Route::options('{any}', function () {
    return response()->json([], 200);
})->where('any', '.*');

// ── Públicas ──────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ── Protegidas ────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Empleado autenticado — sus propios datos
    Route::get('mi-planilla',              [MiPlanillaController::class, 'index']);
    Route::get('mis-boletas/{mes}/{anio}', [MiBoletaController::class, 'descargar']);
    Route::get('mis-documentos',           [MisDocumentosController::class, 'index']);
    Route::patch('mis-documentos/{id}/visto',  [MisDocumentosController::class, 'visto']);
    Route::post('mis-documentos/{id}/firmar',  [MisDocumentosController::class, 'firmar']);
    Route::get('mis-modulos', [MisModulosController::class, 'index']);
    Route::put('cambiar-password', [AuthController::class, 'cambiarPassword']);

    // Vacaciones — cualquier autenticado puede ver y solicitar
    Route::get('vacaciones',          [VacacionController::class, 'index']);
    Route::post('vacaciones',         [VacacionController::class, 'store']);
    Route::get('vacaciones/{id}',     [VacacionController::class, 'show']);

    // Boleta individual — cualquier autenticado con empleado vinculado
    Route::get('boleta/{empleado_id}/{mes}/{anio}', [BoletaController::class, 'generar']);

    // ── Solo RRHH y Administrador ───────────────────────────────
    Route::middleware('rol:rrhh,admin,Administrador')->group(function () {
        Route::apiResource('contratos', ContratoController::class);
        
        Route::apiResource('users', UserController::class)->except(['store']);
        Route::post('empleados/{id}/firma', [EmpleadoController::class, 'subirFirma']);
        Route::apiResource('empleados',        EmpleadoController::class);
        Route::apiResource('planilla',         PlanillaController::class);
        Route::apiResource('documentos',       DocumentoController::class);
        Route::apiResource('descuentos',       DescuentoController::class);
        Route::apiResource('areas',            AreaController::class);
        Route::apiResource('cargos',           CargoController::class);
        Route::apiResource('roles',            RolController::class);
        Route::apiResource('periodos',         PeriodoController::class);
        Route::apiResource('payment-concepts', PaymentConceptController::class);
        Route::apiResource('payroll-detalles', PayrollDetalleController::class);
        Route::apiResource('sedes',            SedeController::class);

        Route::put('vacaciones/{id}',    [VacacionController::class, 'update']);
        Route::delete('vacaciones/{id}', [VacacionController::class, 'destroy']);

        Route::post('boletas/generar-masivo', [BoletaController::class, 'generarMasivo']);

        // Configuración de módulos y roles
        Route::apiResource('modulos-padre', ModuloPadreController::class);
        Route::apiResource('modulos', ModuloController::class);
        Route::post('modulos/{id}/roles', [ModuloController::class, 'asignarRoles']);
    });

});