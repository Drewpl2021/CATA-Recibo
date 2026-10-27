<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NivelPropuesta (contrato §3.24).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\NivelPropuesta
 */
class NivelPropuestaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->clave,
            'nombre'      => Formato::texto($this->nombre),
            'rango'       => Formato::opcional($this->rango),
            'descripcion' => Formato::textos($this->descripcion),
            'rasgos'      => Formato::textos($this->rasgos),
            'talleres'    => array_map(fn ($taller) => [
                'titulo' => Formato::texto($taller['titulo'] ?? null),
                'texto'  => Formato::opcional($taller['texto'] ?? null),
            ], $this->talleres ?? []),
            'foto'        => Formato::imagen($this->imagen),
        ];
    }
}
