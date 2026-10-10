<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Logro (contrato §3.22).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Logro
 */
class LogroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->clave,
            'titulo'      => Formato::texto($this->titulo),
            'categoria'   => $this->categoria,
            'anio'        => $this->anio,
            'nivel'       => Formato::opcional($this->nivel),
            'descripcion' => Formato::opcional($this->descripcion),
            'foto'        => Formato::imagen($this->imagen),
            'destacado'   => $this->destacado,
            'fuente'      => $this->fuente ? [
                'etiqueta' => Formato::texto($this->fuente['etiqueta'] ?? null),
                'url'      => Formato::opcional($this->fuente['url'] ?? null),
            ] : null,
            'aclaracion'  => Formato::opcional($this->aclaracion),
        ];
    }
}
