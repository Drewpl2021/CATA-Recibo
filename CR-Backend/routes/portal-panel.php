<?php

use App\Http\Controllers\Portal\Panel\BannersController;
use App\Http\Controllers\Portal\Panel\CategoriasProyectoController;
use App\Http\Controllers\Portal\Panel\CiclosController;
use App\Http\Controllers\Portal\Panel\CifrasController;
use App\Http\Controllers\Portal\Panel\DocentesController;
use App\Http\Controllers\Portal\Panel\FechasMatriculaController;
use App\Http\Controllers\Portal\Panel\GradosController;
use App\Http\Controllers\Portal\Panel\ImagenesController;
use App\Http\Controllers\Portal\Panel\LogrosController;
use App\Http\Controllers\Portal\Panel\NivelesController;
use App\Http\Controllers\Portal\Panel\NivelesDocentesController;
use App\Http\Controllers\Portal\Panel\NivelesPropuestaController;
use App\Http\Controllers\Portal\Panel\NoticiasController;
use App\Http\Controllers\Portal\Panel\PaginasController;
use App\Http\Controllers\Portal\Panel\PilaresController;
use App\Http\Controllers\Portal\Panel\PlataformasController;
use App\Http\Controllers\Portal\Panel\PreguntasController;
use App\Http\Controllers\Portal\Panel\ProcesosMatriculaController;
use App\Http\Controllers\Portal\Panel\ProyectosController;
use App\Http\Controllers\Portal\Panel\RedesController;
use App\Http\Controllers\Portal\Panel\ResumenController;
use App\Http\Controllers\Portal\Panel\SeccionesController;
use App\Http\Controllers\Portal\Panel\SedesController;
use App\Http\Controllers\Portal\Panel\UniversidadesController;
use App\Http\Controllers\Portal\Panel\VacantesController;
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

// ── Matrícula ──
Route::post('enrollment-processes/order', [ProcesosMatriculaController::class, 'ordenar'])->name('procesos.ordenar');
Route::patch('enrollment-processes/{id}/status', [ProcesosMatriculaController::class, 'estado'])->name('procesos.estado');
Route::apiResource('enrollment-processes', ProcesosMatriculaController::class)->parameters(['enrollment-processes' => 'id'])->names('procesos');

Route::post('vacancies/order', [VacantesController::class, 'ordenar'])->name('vacantes.ordenar');
Route::patch('vacancies/{id}/status', [VacantesController::class, 'estado'])->name('vacantes.estado');
Route::apiResource('vacancies', VacantesController::class)->parameters(['vacancies' => 'id'])->names('vacantes');

// Sin ruta de orden: el cronograma va siempre por fecha.
Route::patch('enrollment-dates/{id}/status', [FechasMatriculaController::class, 'estado'])->name('fechas.estado');
Route::apiResource('enrollment-dates', FechasMatriculaController::class)->parameters(['enrollment-dates' => 'id'])->names('fechas');

Route::post('faqs/order', [PreguntasController::class, 'ordenar'])->name('preguntas.ordenar');
Route::patch('faqs/{id}/status', [PreguntasController::class, 'estado'])->name('preguntas.estado');
Route::apiResource('faqs', PreguntasController::class)->parameters(['faqs' => 'id'])->names('preguntas');

Route::post('grade-options/order', [GradosController::class, 'ordenar'])->name('grados.ordenar');
Route::patch('grade-options/{id}/status', [GradosController::class, 'estado'])->name('grados.estado');
Route::apiResource('grade-options', GradosController::class)->parameters(['grade-options' => 'id'])->names('grados');

// ── Inicio ──
// Lo nuevo entra arriba: el carrusel va del más reciente al más antiguo.
Route::post('banners/order', [BannersController::class, 'ordenar'])->name('banners.ordenar');
Route::patch('banners/{id}/status', [BannersController::class, 'estado'])->name('banners.estado');
Route::apiResource('banners', BannersController::class)->parameters(['banners' => 'id'])->names('banners');

Route::post('home-levels/order', [NivelesController::class, 'ordenar'])->name('niveles.ordenar');
Route::patch('home-levels/{id}/status', [NivelesController::class, 'estado'])->name('niveles.estado');
Route::apiResource('home-levels', NivelesController::class)->parameters(['home-levels' => 'id'])->names('niveles');

// Sin ruta de orden: van siempre por fecha.
Route::patch('news/{id}/status', [NoticiasController::class, 'estado'])->name('noticias.estado');
Route::apiResource('news', NoticiasController::class)->parameters(['news' => 'id'])->names('noticias');

// ── Proyectos ──
// Las categorías no tienen borrador (no hay ruta de estado).
Route::post('project-categories/order', [CategoriasProyectoController::class, 'ordenar'])->name('categorias-proyecto.ordenar');
Route::apiResource('project-categories', CategoriasProyectoController::class)->parameters(['project-categories' => 'id'])->names('categorias-proyecto');

Route::post('projects/order', [ProyectosController::class, 'ordenar'])->name('proyectos.ordenar');
Route::patch('projects/{id}/status', [ProyectosController::class, 'estado'])->name('proyectos.estado');
Route::put('projects/{id}/gallery', [ProyectosController::class, 'galeria'])->name('proyectos.galeria');
Route::apiResource('projects', ProyectosController::class)->parameters(['projects' => 'id'])->names('proyectos');

// ── Propuesta Educativa ──
Route::post('pillars/order', [PilaresController::class, 'ordenar'])->name('pilares.ordenar');
Route::patch('pillars/{id}/status', [PilaresController::class, 'estado'])->name('pilares.estado');
Route::apiResource('pillars', PilaresController::class)->parameters(['pillars' => 'id'])->names('pilares');

Route::post('proposal-levels/order', [NivelesPropuestaController::class, 'ordenar'])->name('niveles-propuesta.ordenar');
Route::patch('proposal-levels/{id}/status', [NivelesPropuestaController::class, 'estado'])->name('niveles-propuesta.estado');
Route::apiResource('proposal-levels', NivelesPropuestaController::class)->parameters(['proposal-levels' => 'id'])->names('niveles-propuesta');

Route::post('universities/order', [UniversidadesController::class, 'ordenar'])->name('universidades.ordenar');
Route::patch('universities/{id}/status', [UniversidadesController::class, 'estado'])->name('universidades.estado');
Route::apiResource('universities', UniversidadesController::class)->parameters(['universities' => 'id'])->names('universidades');

// Los ciclos de cada universidad (?universidad=…). Sin borrador: se ven con su universidad.
Route::post('cycles/order', [CiclosController::class, 'ordenar'])->name('ciclos.ordenar');
Route::apiResource('cycles', CiclosController::class)->parameters(['cycles' => 'id'])->names('ciclos');

Route::post('platforms/order', [PlataformasController::class, 'ordenar'])->name('plataformas.ordenar');
Route::patch('platforms/{id}/status', [PlataformasController::class, 'estado'])->name('plataformas.estado');
Route::apiResource('platforms', PlataformasController::class)->parameters(['platforms' => 'id'])->names('plataformas');

// ── Nosotros ──
// La dirección de cada subpágina se elige al crearla y no cambia. Sus bloques se editan aparte.
Route::post('pages/order', [PaginasController::class, 'ordenar'])->name('paginas.ordenar');
Route::patch('pages/{id}/status', [PaginasController::class, 'estado'])->name('paginas.estado');
Route::put('pages/{id}/blocks', [PaginasController::class, 'bloques'])->name('paginas.bloques');
Route::apiResource('pages', PaginasController::class)->parameters(['pages' => 'id'])->names('paginas');

Route::post('teacher-levels/order', [NivelesDocentesController::class, 'ordenar'])->name('niveles-docentes.ordenar');
Route::patch('teacher-levels/{id}/status', [NivelesDocentesController::class, 'estado'])->name('niveles-docentes.estado');
Route::apiResource('teacher-levels', NivelesDocentesController::class)->parameters(['teacher-levels' => 'id'])->names('niveles-docentes');

// Los docentes se ordenan dentro de su nivel (?nivel=…).
Route::post('teachers/order', [DocentesController::class, 'ordenar'])->name('docentes.ordenar');
Route::patch('teachers/{id}/status', [DocentesController::class, 'estado'])->name('docentes.estado');
Route::apiResource('teachers', DocentesController::class)->parameters(['teachers' => 'id'])->names('docentes');
