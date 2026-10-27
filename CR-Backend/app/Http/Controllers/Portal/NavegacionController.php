<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Pagina;
use App\Support\Portal\Ficha;
use App\Support\Portal\Formato;
use App\Support\Portal\Navegacion;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/portal/v1/navegacion (contrato 2.13, §3.27): el menú, los grupos
 * de Nosotros con sus subpáginas, los títulos del pie y los de cada página.
 *
 * Lo usan todas las páginas del portal, como /v1/sitio. El portal lo
 * descarga además al compilarse, para no quedarse sin menú si esto falla.
 *
 *   - El menú y los títulos salen de las fichas `navegacion` y `titulos`.
 *   - Los grupos de Nosotros, de la ficha `navegacion` (id y título), y sus
 *     subpáginas, de portal_paginas: las publicadas con ese grupo, en su
 *     orden. Una en borrador o sin grupo no sale, y el portal no la abre.
 *     Un grupo sin ninguna publicada tampoco sale.
 */
class NavegacionController extends Controller
{
    public function ver(): JsonResponse
    {
        $navegacion = Ficha::de('navegacion');
        $titulos = Ficha::de('titulos');

        return response()->json(['data' => [
            'menu'     => array_map(fn (Ficha $item) => [
                'pagina'   => $item->texto('pagina'),
                'etiqueta' => $item->texto('etiqueta'),
            ], $navegacion->fichas('menu')),
            'nosotros' => $this->grupos($navegacion),
            'pie'      => collect(Navegacion::PIE)->mapWithKeys(fn ($campo) => [$campo => $navegacion->texto("pie.{$campo}")])->all(),
            'titulos'  => collect(Navegacion::TITULOS)->map(
                fn (array $campos, string $pagina) => collect(array_keys($campos))
                    ->mapWithKeys(fn ($campo) => [$campo => $titulos->texto("{$pagina}.{$campo}")])
                    ->all(),
            )->all(),
        ]]);
    }

    private function grupos(Ficha $navegacion): array
    {
        $porGrupo = Pagina::publicados()->whereNotNull('grupo')->get()->groupBy('grupo');

        return collect($navegacion->fichas('grupos'))
            ->map(fn (Ficha $grupo) => [
                'id'      => $grupo->texto('id'),
                'titulo'  => $grupo->texto('titulo'),
                'paginas' => ($porGrupo[$grupo->texto('id')] ?? collect())->map(fn (Pagina $pagina) => [
                    'slug'     => $pagina->slug,
                    // Sin nombre para el menú, el título de la página.
                    'etiqueta' => Formato::texto($pagina->nombre ?: $pagina->titulo),
                ])->values()->all(),
            ])
            ->filter(fn (array $grupo) => $grupo['paginas'] !== [])
            ->values()
            ->all();
    }
}
