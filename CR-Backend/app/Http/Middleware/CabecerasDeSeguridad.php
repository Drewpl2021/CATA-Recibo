<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo mínimo en cada respuesta del backend: que el navegador no adivine el tipo
 * de un archivo, que nadie meta estas direcciones en un marco y que la
 * dirección (con su enlace firmado) no viaje a otros sitios. Y que nada del
 * API se quede guardado en la caché del navegador.
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

        // Lo del API trae sueldos, DNI, cuentas y PDFs de boletas y hojas: que
        // el navegador no lo guarde en su disco (en una PC compartida lo
        // abriría el siguiente que la use) ni un proxy en el camino.
        // El portal web público (api/portal) es la excepción: no trae datos
        // del personal y se guarda en caché a propósito (CabecerasDelPortal).
        if ($request->is('api/*') && ! $request->is('api/portal/*')) {
            $cabeceras->set('Cache-Control', 'no-store, private');
            $cabeceras->set('Pragma', 'no-cache');
        }

        return $respuesta;
    }
}
