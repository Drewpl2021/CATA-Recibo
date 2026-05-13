<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\BoletaController;

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

    Route::middleware('rol:admin,rrhh')->group(function () {
        Route::apiResource('empleados',  EmpleadoController::class);
        Route::apiResource('planilla',   PlanillaController::class);
        Route::apiResource('documentos', DocumentoController::class);
        Route::apiResource('descuentos', DescuentoController::class);
    });

    Route::get('vacaciones',             [VacacionController::class, 'index']);
    Route::post('vacaciones',            [VacacionController::class, 'store']);
    Route::get('vacaciones/{id}',        [VacacionController::class, 'show']);

    Route::middleware('rol:admin,rrhh')->group(function () {
        Route::put('vacaciones/{id}',    [VacacionController::class, 'update']);
        Route::delete('vacaciones/{id}', [VacacionController::class, 'destroy']);
    });

    // Boleta de pago — admin/rrhh pueden generar cualquier boleta
    Route::get('boleta/{empleado_id}/{mes}/{anio}', [BoletaController::class, 'generar']);
});