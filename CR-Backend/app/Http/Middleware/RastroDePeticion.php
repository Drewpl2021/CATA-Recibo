<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le pone un código corto a cada petición y lo mete en el registro.
 *
 * Es la otra mitad del manejo de errores (ver bootstrap/app.php): cuando algo
 * se rompe, la pantalla dice solo "no pudimos completar la operación" y ese
 * código. Quien atiende lo busca en el registro del servidor y ahí sí está
 * todo: la consulta, el archivo y la línea.
 *
 * Así el detalle no viaja al navegador —donde lo lee cualquiera que abra las
 * herramientas del desarrollador— pero tampoco se pierde para quien tiene que
 * arreglarlo.
 */
class RastroDePeticion
{
    /** Dónde queda el código dentro de la petición, para el manejador. */
    public const ATRIBUTO = 'rastro';

    public function handle(Request $request, Closure $next): Response
    {
        $codigo = Str::upper(Str::random(8));
        $request->attributes->set(self::ATRIBUTO, $codigo);

        // Todo lo que se registre durante esta petición lleva el código, la
        // ruta y de dónde vino. El usuario NO va acá: a esta altura todavía
        // no se sabe quién es, y el manejador lo agrega cuando hace falta.
        Log::withContext([
            'rastro' => $codigo,
            'ruta'   => $request->method() . ' ' . $request->path(),
            'ip'     => $request->ip(),
        ]);

        $respuesta = $next($request);
        $respuesta->headers->set('X-Rastro', $codigo);

        return $respuesta;
    }
}
