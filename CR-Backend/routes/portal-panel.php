<?php

use App\Http\Controllers\Portal\Panel\CifrasController;
use App\Http\Controllers\Portal\Panel\ImagenesController;
use App\Http\Controllers\Portal\Panel\LogrosController;
use App\Http\Controllers\Portal\Panel\RedesController;
use App\Http\Controllers\Portal\Panel\ResumenController;
use App\Http\Controllers\Portal\Panel\SeccionesController;
use App\Http\Controllers\Portal\Panel\SedesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel del portal: editar el contenido de cata.edu.pe desde RR.HH.
|--------------------------------------------------------------------------
|
| Todo aquí cuelga de /api/web-portal (ver bootstrap/app.php) y pasa por la
| MISMA cadena que el resto del sistema protegido —sesión, contraseña al
| día, términos firmados— más una sola puerta: `can:editar-portal`
| (AppServiceProvider). Hoy la abre solo el Administrador; si mañana edita
| otro rol, se cambia ahí y en ningún otro lado.
|
| No va bajo /api/portal: ese prefijo es la API pública del portal, con su
| propio CORS y su propio formato de error. Esto es el sistema de RR.HH. y
| responde como él ({ success, data }).
|
*/

Route::get('summary', ResumenController::class)->name('resumen');

// ── Fichas: el contenido de una sola vez de cada página ──
Route::get('sections/{clave}', [SeccionesController::class, 'ver'])->where('clave', '[a-z-]+')->name('secciones.ver');
Route::put('sections/{clave}', [SeccionesController::class, 'guardar'])->where('clave', '[a-z-]+')->name('secciones.guardar');

// ── General: las redes sociales ──
// order y status van antes del apiResource: si no, «order» se leería como un id.
Route::post('social-networks/order', [RedesController::class, 'ordenar'])->name('redes.ordenar');
Route::patch('social-networks/{id}/status', [RedesController::class, 'estado'])->name('redes.estado');
Route::apiResource('social-networks', RedesController::class)->parameters(['social-networks' => 'id'])->names('redes');

// ── Imágenes: se suben sueltas y después un formulario las usa ──
Route::get('images/uses', [ImagenesController::class, 'usos'])->name('imagenes.usos');
Route::post('images', [ImagenesController::class, 'subir'])->name('imagenes.subir');
Route::patch('images/{id}', [ImagenesController::class, 'editar'])->whereUuid('id')->name('imagenes.editar');

// ── Contacto: las sedes ──
Route::post('campuses/order', [SedesController::class, 'ordenar'])->name('sedes.ordenar');
Route::patch('campuses/{id}/status', [SedesController::class, 'estado'])->name('sedes.estado');
Route::apiResource('campuses', SedesController::class)->parameters(['campuses' => 'id'])->names('sedes');

// ── Cifras (de Inicio, Logros y Propuesta: ?ambito=…) ──
Route::post('stats/order', [CifrasController::class, 'ordenar'])->name('cifras.ordenar');
Route::patch('stats/{id}/status', [CifrasController::class, 'estado'])->name('cifras.estado');
Route::apiResource('stats', CifrasController::class)->parameters(['stats' => 'id'])->names('cifras');

// ── Logros ──
Route::post('achievements/order', [LogrosController::class, 'ordenar'])->name('logros.ordenar');
Route::patch('achievements/{id}/status', [LogrosController::class, 'estado'])->name('logros.estado');
Route::apiResource('achievements', LogrosController::class)->parameters(['achievements' => 'id'])->names('logros');
