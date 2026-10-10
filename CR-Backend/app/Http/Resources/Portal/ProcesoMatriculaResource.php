<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProcesoMatricula (contrato §3.11).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\ProcesoMatricula
 */
class ProcesoMatriculaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->clave,
            'nombre'  => Formato::texto($this->nombre),
            'resumen' => Formato::opcional($this->resumen),
            'pasos'   => array_map(fn ($paso) => [
                'titulo'  => Formato::texto($paso['titulo'] ?? null),
                'detalle' => Formato::opcional($paso['detalle'] ?? null),
            ], $this->pasos ?? []),
            'listas'  => array_map(fn ($lista) => [
                'titulo' => Formato::texto($lista['titulo'] ?? null),
                'items'  => Formato::textos($lista['items'] ?? []),
            ], $this->listas ?? []),
        ];
    }
}
