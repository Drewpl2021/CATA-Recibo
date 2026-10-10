<?php

use App\Http\Controllers\Portal\ContactoController;
use App\Http\Controllers\Portal\InicioController;
use App\Http\Controllers\Portal\MatriculaController;
use App\Http\Controllers\Portal\NosotrosController;
use App\Http\Controllers\Portal\PropuestaController;
use App\Http\Controllers\Portal\ProyectosController;
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
| En el orden del resumen de endpoints del contrato (§2).
|
*/

Route::get('sitio', [SitioController::class, 'ver'])->name('sitio');

Route::get('portada/banners',  [InicioController::class, 'banners'])->name('portada.banners');
Route::get('quienes-somos',    [InicioController::class, 'quienesSomos'])->name('quienes-somos');
Route::get('niveles',          [InicioController::class, 'niveles'])->name('niveles');
Route::get('sedes',            [InicioController::class, 'sedes'])->name('sedes');
Route::get('noticias',         [InicioController::class, 'noticias'])->name('noticias');
Route::get('grados',           [MatriculaController::class, 'grados'])->name('grados');
Route::get('propuesta-educativa/cabecera', [PropuestaController::class, 'cabecera'])->name('propuesta.cabecera');
Route::get('portada/cifras',   [InicioController::class, 'cifras'])->name('portada.cifras');

Route::get('matricula/cabecera',  [MatriculaController::class, 'cabecera'])->name('matricula.cabecera');
Route::get('matricula/procesos',  [MatriculaController::class, 'procesos'])->name('matricula.procesos');
Route::get('matricula/vacantes',  [MatriculaController::class, 'vacantes'])->name('matricula.vacantes');
Route::get('matricula/fechas',    [MatriculaController::class, 'fechas'])->name('matricula.fechas');
Route::get('matricula/preguntas', [MatriculaController::class, 'preguntas'])->name('matricula.preguntas');
Route::get('solicitud',         [ContactoController::class, 'solicitud'])->name('solicitud');
Route::get('contacto',          [ContactoController::class, 'contacto'])->name('contacto');

Route::get('docentes',          [NosotrosController::class, 'docentes'])->name('docentes');
Route::get('logros',            [NosotrosController::class, 'logros'])->name('logros');

Route::get('propuesta-educativa/pilares',     [PropuestaController::class, 'pilares'])->name('propuesta.pilares');
Route::get('propuesta-educativa/niveles',     [PropuestaController::class, 'niveles'])->name('propuesta.niveles');
Route::get('propuesta-educativa/academia',    [PropuestaController::class, 'academia'])->name('propuesta.academia');
Route::get('propuesta-educativa/plataformas', [PropuestaController::class, 'plataformas'])->name('propuesta.plataformas');

Route::get('proyectos',        [ProyectosController::class, 'index'])->name('proyectos');
Route::get('proyectos/{slug}', [ProyectosController::class, 'ver'])->where('slug', '[a-z0-9-]+')->name('proyectos.ver');
Route::get('nosotros',         [NosotrosController::class, 'indice'])->name('nosotros');
Route::get('nosotros/paginas/{slug}', [NosotrosController::class, 'pagina'])->where('slug', '[a-z0-9-]+')->name('nosotros.pagina');
