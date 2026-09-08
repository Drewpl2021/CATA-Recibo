<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    /**
     * Ojo con el origen: sale de config(), no de env().
     *
     * En producción se cachea la configuración (`config:cache`) y a partir de
     * ese momento env() devuelve null fuera de los archivos de config — el
     * navegador se habría quedado sin cabecera y todas las peticiones del
     * frontend habrían empezado a fallar por CORS, en el servidor y no en
     * local, que es la peor forma de descubrirlo.
     */
    public function handle(Request $request, Closure $next)
    {
        $origin = config('app.frontend_url');

        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        return $response;
    }
}