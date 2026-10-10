<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\DocenteResource;
use App\Http\Resources\Portal\LogroResource;
use App\Models\Portal\Docente;
use App\Models\Portal\DocenteNivel;
use App\Models\Portal\Logro;
use App\Models\Portal\Pagina;
use App\Support\Portal\Ficha;
use App\Support\Portal\Formato;
use Illuminate\Http\JsonResponse;

/** Nosotros en el portal: su índice, sus subpáginas, Personal Docente y Logros (§3.19 a §3.22). */
class NosotrosController extends Controller
{
    /**
     * GET /v1/nosotros (§3.19): el título, la frase y una frase por
     * subpágina. Solo las publicadas que tienen frase: las demás muestran
     * su nombre solo, sin tarjeta con texto.
     */
    public function indice(): JsonResponse
    {
        $ficha = Ficha::de('nosotros');

        return response()->json(['data' => [
            'titulo'  => $ficha->texto('titulo'),
            'bajada'  => $ficha->textoOpcional('bajada'),
            'paginas' => Pagina::publicados()->get()
                ->filter(fn (Pagina $pagina) => Formato::opcional($pagina->resumen) !== null)
                ->map(fn (Pagina $pagina) => [
                    'slug'    => $pagina->slug,
                    'resumen' => Formato::texto($pagina->resumen),
                ])
                ->values()
                ->all(),
        ]]);
    }

    /**
     * GET /v1/docentes (§3.21).
     *
     * Los niveles van todos los publicados, tengan o no docentes (así lo
     * publica el contrato). Un docente cuyo nivel está en borrador no sale:
     * apuntaría a un nivel que no está en la lista, y el portal descarta la
     * respuesta entera.
     */
    public function docentes(): JsonResponse
    {
        $niveles = DocenteNivel::publicados()->get();

        $docentes = Docente::publicados()
            ->whereIn('nivel_id', $niveles->pluck('id'))
            ->with(['nivel', 'imagen'])
            ->get();

        return response()->json(['data' => [
            'niveles'  => $niveles->map(fn (DocenteNivel $nivel) => [
                'id'     => $nivel->clave,
                'nombre' => Formato::texto($nivel->nombre),
            ])->all(),
            'docentes' => DocenteResource::collection($docentes),
        ]]);
    }

    /** GET /v1/logros (§3.22): cabecera, cifras y logros, en su orden. */
    public function logros(): JsonResponse
    {
        $ficha = Ficha::de('logros');

        return response()->json(['data' => [
            'titulo' => $ficha->texto('titulo'),
            'bajada' => $ficha->textoOpcional('bajada'),
            'cifras' => Formato::cifras('logros'),
            'logros' => LogroResource::collection(Logro::publicados()->with('imagen')->get()),
        ]]);
    }
}
