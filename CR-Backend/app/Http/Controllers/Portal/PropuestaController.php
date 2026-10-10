<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\Ficha;
use Illuminate\Http\JsonResponse;

/** La página Propuesta Educativa del portal (§3.3 y §3.23 a §3.26). */
class PropuestaController extends Controller
{
    /** GET /v1/propuesta-educativa/cabecera (§3.3). */
    public function cabecera(): JsonResponse
    {
        $ficha = Ficha::de('propuesta-cabecera');

        return response()->json(['data' => [
            'titulo'           => $ficha->texto('titulo'),
            'bajada'           => $ficha->textoOpcional('bajada'),
            'figuras'          => array_map(fn (Ficha $figura) => [
                'tipo'   => $figura->texto('tipo'),
                'imagen' => $figura->imagen('imagen'),
            ], $ficha->fichas('figuras')),
            'accionPrincipal'  => $ficha->accion('accionPrincipal'),
            'accionSecundaria' => $ficha->accionOpcional('accionSecundaria'),
        ]]);
    }
}
