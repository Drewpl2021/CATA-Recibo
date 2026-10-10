<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\CicloResource;
use App\Http\Resources\Portal\NivelPropuestaResource;
use App\Http\Resources\Portal\PlataformaResource;
use App\Models\Portal\NivelPropuesta;
use App\Models\Portal\Pilar;
use App\Models\Portal\Plataforma;
use App\Models\Portal\Universidad;
use App\Support\Portal\Ficha;
use App\Support\Portal\Formato;
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

    /** GET /v1/propuesta-educativa/pilares (§3.23): introducción, cifras y pilares. */
    public function pilares(): JsonResponse
    {
        $introduccion = Ficha::de('propuesta-pilares')->ficha('introduccion');

        return response()->json(['data' => [
            'introduccion' => $introduccion === null ? null : [
                'titulo'   => $introduccion->texto('titulo'),
                'parrafos' => $introduccion->textos('parrafos', alMenosUno: true),
            ],
            'cifras'       => Formato::cifras('propuesta'),
            'pilares'      => Pilar::publicados()->get()->map(fn (Pilar $pilar) => [
                'id'     => $pilar->clave,
                'titulo' => Formato::texto($pilar->titulo),
                'texto'  => Formato::texto($pilar->texto),
                'puntos' => Formato::textos($pilar->puntos),
            ])->all(),
        ]]);
    }

    /** GET /v1/propuesta-educativa/niveles (§3.24). */
    public function niveles(): JsonResponse
    {
        return response()->json(['data' => NivelPropuestaResource::collection(NivelPropuesta::publicados()->with('imagen')->get())]);
    }

    /** GET /v1/propuesta-educativa/academia (§3.25): universidades con sus ciclos. */
    public function academia(): JsonResponse
    {
        $ficha = Ficha::de('propuesta-academia');

        return response()->json(['data' => [
            'titulo'        => $ficha->texto('titulo'),
            'descripcion'   => $ficha->textoOpcional('descripcion'),
            'aclaracion'    => $ficha->textoOpcional('aclaracion'),
            'universidades' => Universidad::publicados()->with('ciclos')->get()->map(fn (Universidad $universidad) => [
                'id'     => $universidad->clave,
                'nombre' => Formato::texto($universidad->nombre),
                'ciclos' => CicloResource::collection($universidad->ciclos),
            ])->all(),
        ]]);
    }

    /** GET /v1/propuesta-educativa/plataformas (§3.26). */
    public function plataformas(): JsonResponse
    {
        return response()->json(['data' => PlataformaResource::collection(Plataforma::publicados()->get())]);
    }
}
