<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quién puede leer la API del portal desde el navegador.
 *
 * El portal vive en otro dominio (cata.edu.pe), así que el navegador solo le
 * entrega la respuesta si trae Access-Control-Allow-Origin con ese origen. Se
 * devuelve el origen que pidió, y solo si está en config('portal.origenes'):
 * nunca "*", para que ninguna otra página arme un portal con nuestros datos
 * desde el navegador de sus visitas.
 *
 * Va como middleware GLOBAL y el primero de la pila, no en el grupo de rutas,
 * por dos motivos que se vieron al armarlo:
 *
 *  - Un 404 de una dirección que no existe no pasa por ningún middleware de
 *    ruta. Sin la cabecera, el portal no puede leer el 404 de
 *    /proyectos/{slug} y mostraría «Reintentar» en vez de «Proyecto no
 *    encontrado».
 *  - Las capas de CORS del sistema (HandleCors de Laravel y CorsMiddleware)
 *    ponen el origen de la pantalla de RR.HH. Al ser la capa de más afuera,
 *    esta es la última en tocar la respuesta y deja solo lo del portal.
 *
 * Fuera de /api/portal no hace nada: las rutas del sistema siguen igual.
 */
class CorsDelPortal
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if (! $peticion->is('api/portal', 'api/portal/*')) {
            return $siguiente($peticion);
        }

        // El preflight se contesta aquí mismo: si siguiera, HandleCors lo
        // respondería con la lista de la pantalla de RR.HH.
        $respuesta = $peticion->isMethod('OPTIONS')
            ? response()->noContent()
            : $siguiente($peticion);

        foreach (array_keys($respuesta->headers->all()) as $cabecera) {
            if (str_starts_with($cabecera, 'access-control-')) {
                $respuesta->headers->remove($cabecera);
            }
        }

        // La respuesta cambia según quién la pide: un proxy no debe servirle
        // a cata.edu.pe la copia que guardó para otro origen.
        $respuesta->setVary('Origin', false);

        $origen = $peticion->headers->get('Origin');

        if ($origen !== null && in_array($origen, config('portal.origenes'), true)) {
            $respuesta->headers->set('Access-Control-Allow-Origin', $origen);
            $respuesta->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
            $respuesta->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
            $respuesta->headers->set('Access-Control-Max-Age', '600');
        }

        return $respuesta;
    }
}
