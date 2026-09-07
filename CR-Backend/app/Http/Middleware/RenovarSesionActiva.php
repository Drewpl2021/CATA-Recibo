<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mantiene viva la sesión mientras se esté trabajando.
 *
 * Antes el token caducaba 24 h después del login y punto: usaras el
 * sistema o no. Eso botaba a RR.HH. a media faena en un cierre de mes, y
 * a la vez dejaba una sesión abierta toda la noche en una computadora que
 * nadie estaba usando. Justo al revés de lo que uno querría.
 *
 * Ahora cada petición empuja la caducidad hacia adelante: la sesión se
 * cierra por ESTAR SIN USARSE, no por antigüedad. Quien está trabajando no
 * se entera nunca; quien deja la pantalla abierta y se va, queda fuera.
 *
 * La ventana es config('sanctum.ventana_inactividad') (en minutos).
 *
 * Va DESPUÉS de auth:sanctum: si el token ya venció, la petición ni llega
 * hasta acá.
 */
class RenovarSesionActiva
{
    /**
     * Margen para no escribir en la base en cada petición.
     *
     * Cargar una pantalla dispara varias llamadas seguidas; sin esto, cada
     * una haría su propio UPDATE sobre la misma fila para adelantar la
     * caducidad unos segundos. Solo se guarda cuando el empujón vale la
     * pena, y la precisión que se pierde son minutos sobre una ventana de
     * horas.
     */
    private const MINUTOS_ENTRE_ESCRITURAS = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $token = $request->user()?->currentAccessToken();

        // En una sesión por cookie (SPA de primera parte) Sanctum entrega un
        // TransientToken, que no es una fila de la base y no se renueva.
        if (! $token instanceof PersonalAccessToken) {
            return $respuesta;
        }

        $ventana = (int) config('sanctum.ventana_inactividad', 120);
        $nuevoVencimiento = now()->addMinutes($ventana);

        $faltaBastante = $token->expires_at
            && $token->expires_at->diffInMinutes($nuevoVencimiento, false) < self::MINUTOS_ENTRE_ESCRITURAS;

        if (! $faltaBastante) {
            $token->forceFill(['expires_at' => $nuevoVencimiento])->save();
        }

        return $respuesta;
    }
}
