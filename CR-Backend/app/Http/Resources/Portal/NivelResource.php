<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nivel de Inicio (contrato §3.5). Ver BannerResource: todas las claves, en orden.
 *
 * @mixin \App\Models\Portal\Nivel
 */
class NivelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->clave,
            'nombre'         => Formato::texto($this->nombre),
            'rango'          => Formato::opcional($this->rango),
            'sede'           => Formato::opcional($this->sede),
            'reconocimiento' => Formato::opcional($this->reconocimiento),
            'foto'           => Formato::imagen($this->imagen),
        ];
    }
}
