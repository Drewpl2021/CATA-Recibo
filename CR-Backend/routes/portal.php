<?php

use App\Http\Controllers\Portal\SitioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública del portal del colegio (cata.edu.pe)
|--------------------------------------------------------------------------
|
| Todo aquí cuelga de /api/portal/v1 y lleva el grupo 'portal' (ver
| bootstrap/app.php). Las reglas, que no se negocian:
|
|   - Solo GET. Ningún endpoint cambia datos; un POST responde 404.
|   - Sin token: no hay auth:sanctum, y no debe haberlo. Por lo mismo, aquí
|     no va nada que no sea contenido que el colegio decidió publicar: ni
|     datos del personal ni de alumnos.
|   - Las rutas y la forma de cada respuesta las fija el contrato del portal
|     (docs/API_CONTRACT.md en su repositorio, v2.12). Un cambio de nombre o
|     de tipo es una versión nueva del contrato, acordada con su equipo.
|
| Va en su propio archivo, y no en routes/api.php, para que nunca quede por
| error dentro del grupo protegido ni herede sus middleware.
|
*/

Route::get('sitio', [SitioController::class, 'ver'])->name('sitio');
