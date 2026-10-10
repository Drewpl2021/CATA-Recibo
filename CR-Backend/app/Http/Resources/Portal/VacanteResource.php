<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * VacantesNivel (contrato §3.12). `disponibilidad` es el `estado` del contrato.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Vacante
 */
class VacanteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->clave,
            'nivel'    => Formato::texto($this->nivel),
            'detalle'  => Formato::opcional($this->detalle),
            'estado'   => $this->disponibilidad,
            'vacantes' => $this->vacantes,
        ];
    }
}
