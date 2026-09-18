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
Route::post('/olvide-password',      [AuthController::class, 'olvidePassword'])->middleware('throttle:recuperacion');
Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:recuperacion');

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
    // El trabajador sube su propio CV; el empleado sale del token.
    Route::post('mis-documentos/hoja-de-vida', [MisDocumentosController::class, 'subirHojaDeVida']);
    Route::get('mis-modulos', [MisModulosController::class, 'index']);
    Route::put('cambiar-password', [AuthController::class, 'cambiarPassword']);

    // Los términos de uso: el papel que antes se firmaba a mano. Los firma
    // cada trabajador desde su cuenta, así que van entre las rutas de
    // cualquier autenticado y no en el grupo de RR.HH.
    Route::get('terminos',          [TerminosController::class, 'mostrar']);
    Route::post('terminos/aceptar', [TerminosController::class, 'aceptar']);
    // El propio empleado registra/actualiza su firma y/o huella (RRHH tiene su
    // propio endpoint equivalente para hacerlo por cualquier empleado, más abajo).
    Route::post('mi-identidad-firma', [IdentidadFirmaController::class, 'subirMia']);
    // La imagen guardada, para poder enseñársela a quien la dibujó. Va por su
    // propia ruta porque está en el disco privado; el permiso se revisa dentro.
    Route::get('mi-firma-imagen',             [IdentidadFirmaController::class, 'ver']);
    Route::get('empleados/{id}/firma-imagen', [IdentidadFirmaController::class, 'ver']);

    // La foto de perfil. Cada quien sube y quita LA SUYA; la imagen se sirve
    // por su propia ruta porque vive en el disco privado y hay que comprobar
    // antes quién la pide. "users/{id}/foto" no choca con el apiResource de
    // users: tiene un segmento más.
    Route::post('mi-foto',        [FotoPerfilController::class, 'subirMia']);
    Route::delete('mi-foto',      [FotoPerfilController::class, 'quitarMia']);
    Route::get('users/{id}/foto', [FotoPerfilController::class, 'ver']);

    // Vacaciones — el trabajador ve y pide LAS SUYAS; RR.HH. ve las de todos.
    // El recorte se hace dentro del controlador, con el empleado del token.
    // 'saldo' va antes que '{id}' o la ruta con parametro se lo comeria.
    Route::get('vacaciones/saldo',    [VacacionController::class, 'saldo']);
    Route::get('vacaciones',          [VacacionController::class, 'index']);
    Route::post('vacaciones',         [VacacionController::class, 'store']);
    Route::get('vacaciones/{id}',     [VacacionController::class, 'show']);
    // Retirar una solicitud: RR.HH. cualquiera; el trabajador solo la suya y
    // mientras siga pendiente (se comprueba dentro del controlador).
    Route::delete('vacaciones/{id}',  [VacacionController::class, 'destroy']);

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
        // RR.HH. le repone la contrasena a quien se quedo fuera. Queda obligado
        // a cambiarla al entrar, y se le cierran las sesiones abiertas.
        Route::post('users/{id}/restablecer-password', [UserController::class, 'restablecerPassword']);
        // Buscar a la persona por su DNI antes de darla de alta. Va limitada
        // porque cada consulta que no esté en la base propia se le paga a
        // Decolecta: sin freno, un script podría vaciar el saldo.
        Route::get('consulta-dni/{dni}', ConsultaDniController::class)
            ->middleware('throttle:consulta_dni');

        Route::post('empleados/{id}/identidad-firma', [IdentidadFirmaController::class, 'subir']);
        // La lista del personal en CSV. Va ANTES del apiResource, igual que
        // la de planilla: si no, "exportar" entraría por show({id}).
        Route::get('empleados/exportar',       [EmpleadoController::class, 'exportar']);
        Route::apiResource('empleados',        EmpleadoController::class);

        // La planilla completa en CSV. Va ANTES del apiResource: si no,
        // "exportar" entraría por show({id}) y devolvería un 404 buscando
        // una planilla con ese id.
        Route::get('planilla/exportar',        [PlanillaController::class, 'exportar']);
        Route::apiResource('planilla',         PlanillaController::class);

        // Las corridas: la planilla con nombre que agrupa a un grupo de gente
        // ("Planilla TIC — Septiembre 2026"). Las rutas sueltas van ANTES del
        // apiResource: si no, "sacar" entraría por show({id}) y devolvería un
        // 404 buscando una corrida con ese id.
        Route::post('planilla-corridas/sacar',           [PlanillaCorridaController::class, 'sacar']);
        Route::post('planilla-corridas/{id}/generar',    [PlanillaCorridaController::class, 'generar']);
        Route::post('planilla-corridas/{id}/mover',      [PlanillaCorridaController::class, 'mover']);
        Route::apiResource('planilla-corridas', PlanillaCorridaController::class);
        // Documentos del personal: el expediente de cada trabajador, ordenado
        // por persona (hoja de vida, contratos, boletas). Ver ExpedienteController.
        Route::get('expedientes',              [\App\Http\Controllers\ExpedienteController::class, 'index']);
        Route::get('expedientes/{empleadoId}', [\App\Http\Controllers\ExpedienteController::class, 'show']);
        // Subir un archivo que llega de fuera (hoja de vida, contrato
        // escaneado). Va ANTES del apiResource: si no, "subir" entraría por
        // show({id}) y devolvería un 404 buscando un documento con ese id.
        Route::post('documentos/subir',        [DocumentoController::class, 'subir']);
        Route::apiResource('documentos',       DocumentoController::class);
        Route::post('documentos/{id}/firmar-empleador', [DocumentoController::class, 'firmarComoEmpleador']);
        Route::apiResource('areas',            AreaController::class);
        Route::apiResource('cargos',           CargoController::class);
        Route::apiResource('periodos',         PeriodoController::class);
        Route::post('periodos/{id}/generar-planilla', [PeriodoController::class, 'generarPlanilla']);
        Route::apiResource('payment-concepts', PaymentConceptController::class);
        Route::post('payment-concepts/{id}/aplicar-a-grupo', [PaymentConceptController::class, 'aplicarAGrupo']);
        // Deja las líneas de UNA planilla como diga la pantalla, de una vez.
        // Va antes del apiResource de planilla por el mismo motivo de siempre:
        // con un segmento más, no choca con show({id}).
        Route::post('planilla/{id}/conceptos', [PlanillaController::class, 'sincronizarConceptos']);
        // Importar conceptos desde el Excel de RR.HH.: todas las planillas del
        // mes de una vez. Tres pasos; el Excel se lee en el navegador y solo
        // llegan sus celdas. Ver ImportacionConceptosController. "modelo" es
        // el Excel para llenar, con la gente del mes y una columna por concepto.
        Route::get('importacion-conceptos/modelo',         [\App\Http\Controllers\ImportacionConceptosController::class, 'modelo']);
        Route::post('importacion-conceptos/reconocer',    [\App\Http\Controllers\ImportacionConceptosController::class, 'reconocer']);
        Route::post('importacion-conceptos/previsualizar', [\App\Http\Controllers\ImportacionConceptosController::class, 'previsualizar']);
        Route::post('importacion-conceptos/aplicar',       [\App\Http\Controllers\ImportacionConceptosController::class, 'aplicar']);
        // Importar empleados desde Excel (altas y actualizaciones por DNI) y sus
        // hojas de vida en lote, con el DNI en el nombre de cada archivo.
        Route::get('importacion-empleados/modelo',         [\App\Http\Controllers\ImportacionEmpleadosController::class, 'modelo']);
        Route::post('importacion-empleados/reconocer',    [\App\Http\Controllers\ImportacionEmpleadosController::class, 'reconocer']);
        Route::post('importacion-empleados/previsualizar', [\App\Http\Controllers\ImportacionEmpleadosController::class, 'previsualizar']);
        Route::post('importacion-empleados/aplicar',       [\App\Http\Controllers\ImportacionEmpleadosController::class, 'aplicar']);
        Route::post('importacion-empleados/hoja-de-vida',  [\App\Http\Controllers\ImportacionEmpleadosController::class, 'hojaDeVida']);
        // Boletas y contratos de antes del sistema, en lote: el navegador lee
        // cada PDF y aquí se decide de quién es. Ver DocumentosAnterioresController.
        Route::post('documentos-anteriores/previsualizar', [\App\Http\Controllers\DocumentosAnterioresController::class, 'previsualizar']);
        Route::post('documentos-anteriores',               [\App\Http\Controllers\DocumentosAnterioresController::class, 'subir']);
        Route::apiResource('payroll-detalles', PayrollDetalleController::class);
        Route::apiResource('sedes',            SedeController::class);

        // Aprobar o rechazar: solo RR.HH. y Administración.
        Route::put('vacaciones/{id}',    [VacacionController::class, 'update']);

        Route::post('boletas/generar-masivo', [BoletaController::class, 'generarMasivo']);
    });

    // ── Solo Administrador: gestión de roles y del sistema de permisos ──
    Route::middleware('rol:admin')->group(function () {
        // La auditoría: quién cambió qué. Solo lectura, y solo Admin —es
        // justamente donde se ve lo que hizo RR.HH.—.
        Route::get('auditoria', [AuditoriaController::class, 'index']);

        Route::apiResource('roles', RolController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('modulos-padre', ModuloPadreController::class);
        Route::apiResource('modulos', ModuloController::class);
        Route::post('modulos/{id}/roles', [ModuloController::class, 'asignarRoles']);
    });

});