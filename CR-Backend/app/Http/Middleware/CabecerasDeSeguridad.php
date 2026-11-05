<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo mínimo en cada respuesta del backend: que el navegador no adivine el tipo
 * de un archivo, que nadie meta estas direcciones en un marco y que la
 * dirección (con su enlace firmado) no viaje a otros sitios.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $cabeceras = $respuesta->headers;
        $cabeceras->set('X-Content-Type-Options', 'nosniff');
        $cabeceras->set('X-Frame-Options', 'DENY');
        $cabeceras->set('Referrer-Policy', 'no-referrer');
        if ($request->secure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=15552000');
        }
        $cabeceras->remove('X-Powered-By');

        return $respuesta;
    }
}
