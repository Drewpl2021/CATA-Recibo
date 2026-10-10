<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\ProyectoDetalleResource;
use App\Http\Resources\Portal\ProyectoResource;
use App\Models\Portal\Proyecto;
use App\Models\Portal\ProyectoCategoria;
use App\Support\Portal\Ficha;
use App\Support\Portal\Formato;
use Illuminate\Http\JsonResponse;

/** La página Proyectos del portal (§3.17) y el detalle de cada uno (§3.18). */
class ProyectosController extends Controller
{
    /**
     * GET /v1/proyectos: cabecera, categorías del filtro y lista.
     *
     * Van todas las categorías, en el orden del filtro: el portal ya oculta
     * las que no tienen proyectos. Las categorías no tienen borrador, así
     * que ningún proyecto apunta a una que no esté en la lista.
     */
    public function index(): JsonResponse
    {
        $ficha = Ficha::de('proyectos');

        return response()->json(['data' => [
            'titulo'     => $ficha->texto('titulo'),
            'bajada'     => $ficha->textoOpcional('bajada'),
            'categorias' => ProyectoCategoria::orderBy('orden')->get()->map(fn (ProyectoCategoria $categoria) => [
                'id'     => $categoria->clave,
                'nombre' => Formato::texto($categoria->nombre),
            ])->all(),
            'proyectos'  => ProyectoResource::collection(Proyecto::publicados()->with(['categoria', 'imagen'])->get()),
        ]]);
    }

    /**
     * GET /v1/proyectos/{slug}. Si no existe o está en borrador, 404: el
     * portal muestra «Proyecto no encontrado» con un enlace a la lista.
     */
    public function ver(string $slug): JsonResponse
    {
        $proyecto = Proyecto::where('slug', $slug)
            ->where('estado', Proyecto::PUBLICADO)
            ->with(['categoria', 'imagen', 'galeria.imagen'])
            ->firstOrFail();

        return response()->json(['data' => new ProyectoDetalleResource($proyecto)]);
    }
}
