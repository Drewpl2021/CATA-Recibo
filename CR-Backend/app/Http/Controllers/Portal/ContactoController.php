<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\Ficha;
use Illuminate\Http\JsonResponse;

/**
 * La página Contacto del portal (§3.16) y los textos del formulario
 * «Solicitar información» (§3.15), que está en Contacto y en Matrícula. Las
 * sedes salen de /v1/sedes, y el WhatsApp y las redes, de /v1/sitio.
 */
class ContactoController extends Controller
{
    /** GET /v1/contacto (§3.16). */
    public function contacto(): JsonResponse
    {
        $ficha = Ficha::de('contacto');

        return response()->json(['data' => [
            'titulo' => $ficha->texto('titulo'),
            'bajada' => $ficha->textoOpcional('bajada'),
            'figura' => $ficha->imagen('figura'),
            'correo' => $ficha->textoOpcional('correo'),
        ]]);
    }

    /** GET /v1/solicitud (§3.15). El formulario no envía nada: abre WhatsApp. */
    public function solicitud(): JsonResponse
    {
        $ficha = Ficha::de('solicitud');

        return response()->json(['data' => [
            'titulo'            => $ficha->texto('titulo'),
            'texto'             => $ficha->textoOpcional('texto'),
            'mensajeWhatsapp'   => $ficha->texto('mensajeWhatsapp'),
            'mensajesPorPagina' => array_map(fn (Ficha $mensaje) => [
                'pagina'  => $mensaje->texto('pagina'),
                'mensaje' => $mensaje->texto('mensaje'),
            ], $ficha->fichas('mensajesPorPagina')),
        ]]);
    }
}
