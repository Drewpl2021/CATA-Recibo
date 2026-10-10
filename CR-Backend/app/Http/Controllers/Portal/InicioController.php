<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\BannerResource;
use App\Http\Resources\Portal\NivelResource;
use App\Http\Resources\Portal\NoticiaResource;
use App\Http\Resources\Portal\SedeResource;
use App\Models\Portal\Banner;
use App\Models\Portal\Nivel;
use App\Models\Portal\Noticia;
use App\Models\Portal\Sede;
use App\Support\Portal\Ficha;
use App\Support\Portal\Formato;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las secciones de Inicio del portal. Cada una es su propio endpoint: si una
 * falla, el portal muestra el error solo en esa sección.
 */
class InicioController extends Controller
{
    /** GET /v1/portada/banners (§3.2): el carrusel, en su orden. */
    public function banners(): JsonResponse
    {
        $banners = Banner::publicados()->with(['imagen', 'imagenMovil'])->get();

        return response()->json(['data' => BannerResource::collection($banners)]);
    }

    /** GET /v1/portada/cifras (§3.9). */
    public function cifras(): JsonResponse
    {
        return response()->json(['data' => Formato::cifras('portada')]);
    }

    /** GET /v1/quienes-somos (§3.4). */
    public function quienesSomos(): JsonResponse
    {
        $ficha = Ficha::de('quienes-somos');

        return response()->json(['data' => [
            'titulo'   => $ficha->texto('titulo'),
            'parrafos' => $ficha->textos('parrafos', alMenosUno: true),
            'foto'     => $ficha->imagen('foto'),
            'simbolos' => array_map(fn (Ficha $simbolo) => [
                'nombre'      => $simbolo->texto('nombre'),
                'significado' => $simbolo->texto('significado'),
            ], $ficha->fichas('simbolos')),
        ]]);
    }

    /** GET /v1/niveles (§3.5): el título de la sección y los niveles. */
    public function niveles(): JsonResponse
    {
        $ficha = Ficha::de('niveles');

        return response()->json(['data' => [
            'titulo'       => $ficha->texto('titulo'),
            'propuestaUrl' => $ficha->textoOpcional('propuestaUrl'),
            'items'        => NivelResource::collection(Nivel::publicados()->with('imagen')->get()),
        ]]);
    }

    /** GET /v1/sedes (§3.6), en orden de recorrido. */
    public function sedes(): JsonResponse
    {
        return response()->json(['data' => SedeResource::collection(Sede::publicados()->with('imagen')->get())]);
    }

    /**
     * GET /v1/noticias?limite&pagina (§3.7): de la más reciente a la más
     * antigua, por páginas. Un parámetro que no vale responde 400.
     */
    public function noticias(Request $peticion): JsonResponse
    {
        $parametros = $peticion->validate([
            'limite' => 'sometimes|integer|between:1,24',
            'pagina' => 'sometimes|integer|min:1',
        ], [
            'limite.*' => 'limite debe ser un entero de 1 a 24.',
            'pagina.*' => 'pagina debe ser un entero mayor o igual a 1.',
        ]);

        $limite = (int) ($parametros['limite'] ?? 6);
        $pagina = (int) ($parametros['pagina'] ?? 1);

        $consulta = Noticia::recientes();
        $total = (clone $consulta)->count();
        $noticias = $consulta->with('imagen')->forPage($pagina, $limite)->get();

        return response()->json([
            'data' => NoticiaResource::collection($noticias),
            'meta' => ['pagina' => $pagina, 'limite' => $limite, 'total' => $total],
        ]);
    }
}
