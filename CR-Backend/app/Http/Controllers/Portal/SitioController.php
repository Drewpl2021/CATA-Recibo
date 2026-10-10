<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\RedSocial;
use App\Models\Portal\Seccion;
use App\Support\Portal\Ficha;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/portal/v1/sitio — los datos generales del colegio (contrato §3.1).
 *
 * Es el endpoint que más pesa en el portal: lo usan la cabecera, el pie, las
 * redes y el formulario de WhatsApp. Si falla, todas esas partes quedan sin
 * datos a la vez.
 */
class SitioController extends Controller
{
    public function ver(): JsonResponse
    {
        $sitio = new Ficha('sitio', Seccion::contenido('sitio'));

        return response()->json(['data' => [
            'nombre'         => $sitio->texto('nombre'),
            'nombreCorto'    => $sitio->texto('nombreCorto'),
            'subtitulo'      => $sitio->textoOpcional('subtitulo'),
            'ciudad'         => $sitio->texto('ciudad'),
            'anioFundacion'  => $sitio->entero('anioFundacion'),
            'telefono'       => $this->telefono($sitio, 'telefono'),
            'whatsapp'       => $this->telefono($sitio, 'whatsapp'),
            'direccion'      => [
                'linea'      => $sitio->texto('direccion.linea'),
                'referencia' => $sitio->textoOpcional('direccion.referencia'),
                'distrito'   => $sitio->textoOpcional('direccion.distrito'),
            ],
            // Sin plataforma, el portal no muestra el botón.
            'portalAcademico' => $sitio->tiene('portalAcademico') ? [
                'nombre' => $sitio->texto('portalAcademico.nombre'),
                'url'    => $sitio->texto('portalAcademico.url'),
            ] : null,
            'promotora'      => $sitio->textoOpcional('promotora'),
            'redes'          => RedSocial::publicadas()->get()->map(fn (RedSocial $red) => [
                'red'        => $red->red,
                'cuenta'     => $red->cuenta,
                'url'        => $red->url,
                'aclaracion' => Ficha::limpio($red->aclaracion),
            ])->all(),
        ]]);
    }

    /** El tipo Telefono del contrato: E.164 para el enlace y cómo se lee. */
    private function telefono(Ficha $sitio, string $campo): array
    {
        return [
            'numero'  => $sitio->texto("{$campo}.numero"),
            'visible' => $sitio->texto("{$campo}.visible"),
        ];
    }
}
