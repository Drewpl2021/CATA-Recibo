<?php

use App\Http\Controllers\Portal\Panel\ResumenController;
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
