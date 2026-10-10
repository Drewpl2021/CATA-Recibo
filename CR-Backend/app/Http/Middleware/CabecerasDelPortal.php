<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cabeceras que pide el contrato del portal (§1.2) en cada respuesta.
 *
 *  - Content-Type con charset: Laravel manda "application/json" a secas.
 *  - Los acentos y las barras tal cual ("Túpac", "https://…") y no como
 *    ú y \/: es el mismo JSON, pero más corto y legible en el registro.
 *  - Cache-Control público, solo en los 200: un error no debe quedarse
 *    guardado en el navegador de nadie.
 */
class CabecerasDelPortal
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $respuesta = $siguiente($peticion);

        if ($respuesta instanceof JsonResponse) {
            $respuesta->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $respuesta->headers->set('Content-Type', 'application/json; charset=utf-8');
        }

        if ($respuesta->getStatusCode() === 200) {
            $respuesta->headers->set('Cache-Control', 'public, max-age=' . config('portal.cache_segundos'));
        } else {
            $respuesta->headers->set('Cache-Control', 'no-store');
        }

        return $respuesta;
    }
}
