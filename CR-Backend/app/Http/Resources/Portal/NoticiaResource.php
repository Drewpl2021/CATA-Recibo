<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Noticia (contrato §3.7). Ver BannerResource: todas las claves, en orden.
 *
 * @mixin \App\Models\Portal\Noticia
 */
class NoticiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->clave,
            'slug'    => $this->slug,
            'tipo'    => $this->tipo,
            'titulo'  => Formato::texto($this->titulo),
            'resumen' => Formato::opcional($this->resumen),
            'fecha'   => Formato::fecha($this->fecha),
            'foto'    => Formato::imagen($this->imagen),
            'enlace'  => Formato::enlace($this->enlace),
        ];
    }
}
