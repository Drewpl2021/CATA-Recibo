<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plataforma (contrato §3.26).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Plataforma
 */
class PlataformaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->clave,
            'nombre'      => Formato::texto($this->nombre),
            'descripcion' => Formato::opcional($this->descripcion),
            'funciones'   => array_map(fn ($funcion) => [
                'titulo' => Formato::texto($funcion['titulo'] ?? null),
                'texto'  => Formato::texto($funcion['texto'] ?? null),
            ], $this->funciones ?? []),
            'enlace'      => Formato::enlace($this->enlace),
            'aclaracion'  => Formato::opcional($this->aclaracion),
        ];
    }
}
