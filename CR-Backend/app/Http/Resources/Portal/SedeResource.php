<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sede (contrato §3.6). Ver BannerResource: todas las claves, en orden.
 *
 * @mixin \App\Models\Portal\Sede
 */
class SedeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->clave,
            'nombre'       => Formato::texto($this->nombre),
            'direccion'    => Formato::texto($this->direccion),
            'referencia'   => Formato::opcional($this->referencia),
            'distrito'     => Formato::opcional($this->distrito),
            'telefono'     => Formato::telefono($this->telefono),
            'nota'         => Formato::opcional($this->nota),
            'foto'         => Formato::imagen($this->imagen),
            'mapaUrl'      => Formato::opcional($this->mapa_url),
            'correo'       => Formato::opcional($this->correo),
            'horario'      => array_map(fn ($fila) => [
                'dias'  => Formato::texto($fila['dias'] ?? null),
                'horas' => Formato::texto($fila['horas'] ?? null),
            ], $this->horario ?? []),
            'mapaEmbedUrl' => Formato::opcional($this->mapa_embed_url),
        ];
    }
}
