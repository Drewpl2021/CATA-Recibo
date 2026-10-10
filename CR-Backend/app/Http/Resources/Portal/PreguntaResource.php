<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PreguntaFrecuente (contrato §3.14).
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Pregunta
 */
class PreguntaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->clave,
            'pregunta'  => Formato::texto($this->pregunta),
            'respuesta' => Formato::texto($this->respuesta),
        ];
    }
}
