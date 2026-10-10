<?php

namespace App\Support\Portal;

use App\Http\Middleware\RastroDePeticion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Los errores de la API del portal, con la forma de su contrato (§1.4):
 *
 *   { "error": { "codigo": "NO_ENCONTRADO", "mensaje": "…" } }
 *
 * No es el { success, message } del resto del sistema porque quien lo lee es
 * el portal, que decide qué mostrar por el `codigo`. El `mensaje` es para el
 * registro técnico, y por eso tampoco cuenta nada de cómo está armado el
 * servidor: igual que en bootstrap/app.php, el detalle va al log.
 */
class ErrorDelPortal
{
    public static function responder(Throwable $e, Request $peticion): JsonResponse
    {
        $cabeceras = [];

        [$estado, $codigo, $mensaje] = match (true) {
            // Un parámetro de consulta que no vale (?limite=abc). Es 400 y no
            // el 422 del sistema: así lo fija el contrato.
            $e instanceof ValidationException => [
                400, 'PARAMETRO_INVALIDO', collect($e->errors())->flatten()->first() ?? 'Parámetro inválido.',
            ],
            // Un POST o un PUT se contestan como «no existe», igual que en el
            // resto del API: el portal solo lee.
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException,
            $e instanceof MethodNotAllowedHttpException => [
                404, 'NO_ENCONTRADO', 'No existe ese recurso del portal.',
            ],
            $e instanceof SeccionSinConfigurar => [
                503, 'NO_DISPONIBLE', 'Esta sección del portal todavía no está disponible.',
            ],
            // `php artisan down`: lo lanza PreventRequestsDuringMaintenance.
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 503 => [
                503, 'NO_DISPONIBLE', 'El servicio está en mantenimiento.',
            ],
            // El contrato no lo nombra; se avisó al equipo del portal. Lleva
            // Retry-After para que sepa cuánto esperar.
            $e instanceof ThrottleRequestsException => [
                429, 'DEMASIADAS_PETICIONES', 'Demasiadas peticiones seguidas. Espera un momento.',
            ],
            default => [
                500, 'ERROR_INTERNO', 'No pudimos responder. Si sigue igual, avisa a soporte con la referencia.',
            ],
        };

        if ($e instanceof ThrottleRequestsException) {
            $cabeceras = $e->getHeaders();
        }

        if ($e instanceof SeccionSinConfigurar) {
            // No es una falla del servidor: es contenido que falta cargar. Lo
            // que se lee en el registro es justo eso, qué sección y qué campo.
            Log::warning('Portal: ' . $e->getMessage());
        }

        if ($estado === 500) {
            $referencia = $peticion->attributes->get(RastroDePeticion::ATRIBUTO);
            $mensaje .= " Referencia: {$referencia}.";

            Log::error('Falló una respuesta del portal', [
                'ruta'      => $peticion->path(),
                'excepcion' => $e::class,
                'mensaje'   => $e->getMessage(),
                'archivo'   => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        return response()->json(
            ['error' => ['codigo' => $codigo, 'mensaje' => $mensaje]],
            $estado,
            $cabeceras + [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
