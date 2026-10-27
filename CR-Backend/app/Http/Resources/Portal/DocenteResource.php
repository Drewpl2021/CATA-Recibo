<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Docente (contrato §3.21): solo nombre, cargo, nivel y foto.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Docente
 */
class DocenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->clave,
            'nombre' => Formato::texto($this->nombre),
            'cargo'  => Formato::texto($this->cargo),
            'nivel'  => $this->nivel->clave,
            'foto'   => Formato::imagen($this->imagen),
        ];
    }
}
