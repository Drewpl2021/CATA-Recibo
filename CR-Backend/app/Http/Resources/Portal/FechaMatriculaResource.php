<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FechaMatricula (contrato §3.13).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\FechaMatricula
 */
class FechaMatriculaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->clave,
            'titulo'  => Formato::texto($this->titulo),
            'inicio'  => Formato::fecha($this->inicio),
            'fin'     => Formato::fecha($this->fin),
            'detalle' => Formato::opcional($this->detalle),
        ];
    }
}
