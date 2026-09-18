<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\RastroDePeticion;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorsMiddleware::class);
        // Le pone un código a cada petición para poder seguirla en el registro.
        $middleware->append(RastroDePeticion::class);
        /*
         * Sin sesión, la API contesta 401 en JSON.
         *
         * Por defecto Laravel manda al invitado a la pantalla de login, y
         * aquí no hay ninguna: es una API. Al intentar armar esa dirección
         * reventaba con "Route [login] not defined" y el 401 se convertía en
         * un 500 —probado con GET /api/mi-firma-imagen sin token—. Devolver
         * null quita el redirigir y deja pasar la excepción de siempre, que
         * el manejador de abajo ya convierte en 401.
         */
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'rol'         => \App\Http\Middleware\CheckRol::class,
            'sesion'      => \App\Http\Middleware\RenovarSesionActiva::class,
            // Traba la cuenta que todavía usa la contraseña que le dieron.
            'clave_nueva' => \App\Http\Middleware\ExigirCambioPassword::class,
            // Traba a quien todavía no firmó los términos de uso.
            'terminos'    => \App\Http\Middleware\ExigirTerminos::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Qué ve quien usa el sistema cuando algo sale mal.
         *
         * Antes esto estaba vacío, así que el API devolvía el error tal cual
         * lo cuenta Laravel. Con APP_DEBUG=true eso es el mensaje de la
         * excepción, el archivo, la línea y la traza entera —se vio en
         * pantalla un "Undefined variable $catalogos" con la ruta del
         * proyecto—. Y aun con APP_DEBUG=false, un 404 seguía diciendo
         * "No query results for model [App\Models\Empleado] 0198…": el nombre
         * de la clase y el id que se probó.
         *
         * La regla es la que pidió el colegio: al usuario, lo justo para
         * saber qué hacer; el detalle, al registro del servidor.
         *
         * Los errores de formulario (422) se quedan como están: esos SÍ son
         * para el usuario —"el correo ya existe", "falta el sueldo"— y son
         * los que le permiten corregir.
         */
        $exceptions->render(function (Throwable $e, Request $peticion) {
            // Las pantallas web (hoy solo el enlace de recuperación) siguen
            // con el comportamiento de siempre.
            if (! $peticion->is('api/*') && ! $peticion->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null;
            }

            [$estado, $mensaje] = match (true) {
                $e instanceof AuthenticationException => [
                    401, 'Tu sesión terminó. Vuelve a entrar.',
                ],
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [
                    403, 'No tienes permiso para hacer esto.',
                ],
                // El nombre del modelo y el id que se buscó se quedan en el
                // registro: al usuario no le dicen nada y a un curioso le
                // dicen cómo está armado el sistema por dentro.
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                    404, 'No encontramos lo que buscas. Puede que ya no exista.',
                ],
                // Un método que no corresponde se responde como "no existe":
                // por el comodín de CORS (OPTIONS api/{any}) una dirección
                // inventada llega acá, y decir "ese método no vale" sería a
                // la vez confuso para el usuario y una pista de qué rutas
                // existen para quien anda probando.
                $e instanceof MethodNotAllowedHttpException => [
                    404, 'No encontramos lo que buscas. Puede que ya no exista.',
                ],
                $e instanceof ThrottleRequestsException => [
                    429, 'Demasiados intentos seguidos. Espera un momento y vuelve a intentarlo.',
                ],
                $e instanceof PostTooLargeException => [
                    413, 'El archivo pesa demasiado.',
                ],
                default => [
                    500, 'No pudimos completar la operación. Vuelve a intentarlo; si sigue igual, avisa a soporte con el código.',
                ],
            };

            $cuerpo = ['success' => false, 'message' => $mensaje];

            if ($estado >= 500) {
                $rastro = $peticion->attributes->get(RastroDePeticion::ATRIBUTO);
                $cuerpo['referencia'] = $rastro;

                // El detalle, acá: con el mismo código que ve el usuario.
                Log::error('Falló una operación', [
                    'usuario'   => $peticion->user()?->id,
                    'excepcion' => $e::class,
                    'mensaje'   => $e->getMessage(),
                    'archivo'   => $e->getFile() . ':' . $e->getLine(),
                ]);

                // En desarrollo sí conviene verlo en la respuesta: es la
                // máquina de quien programa, no la del colegio.
                if (config('app.debug')) {
                    $cuerpo['detalle'] = $e->getMessage();
                }
            }

            return response()->json($cuerpo, $estado);
        });
    })->create();
