<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GrupoGrados (contrato §3.8). El contrato no le da id.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\GrupoGrados
 */
class GrupoGradosResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nivel'    => Formato::texto($this->nivel),
            'opciones' => Formato::textos($this->opciones),
        ];
    }
}
