<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CicloAcademia (contrato §3.25). Los precios salen como número (440), no
 * como el texto "440.00" de la columna decimal.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Ciclo
 */
class CicloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->clave,
            'nombre'        => Formato::texto($this->nombre),
            'turno'         => Formato::opcional($this->turno),
            'modalidades'   => array_values(array_intersect(\App\Models\Portal\Ciclo::MODALIDADES, $this->modalidades ?? [])),
            'precio'        => Formato::numero($this->precio),
            'precioVirtual' => Formato::numero($this->precio_virtual),
            'inicio'        => Formato::fecha($this->inicio),
            'fin'           => Formato::fecha($this->fin),
        ];
    }
}
