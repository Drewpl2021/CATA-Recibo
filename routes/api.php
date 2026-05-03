<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\VacacionController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\DocumentoController;

Route::apiResource('empleados', EmpleadoController::class);
Route::apiResource('vacaciones', VacacionController::class);
Route::apiResource('planilla', PlanillaController::class);
Route::apiResource('documentos', DocumentoController::class);