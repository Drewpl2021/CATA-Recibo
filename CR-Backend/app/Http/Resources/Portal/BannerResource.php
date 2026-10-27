<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Banner (contrato §3.2).
 *
 * Los Resources del portal arman cada objeto con TODAS sus claves y en el
 * orden del contrato. Nada de when()/whenLoaded(): omiten la clave, y una
 * clave que falta hace que el portal descarte la respuesta.
 *
 * @mixin \App\Models\Portal\Banner
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->clave,
            'categoria'     => $this->categoria,
            'titulo'        => Formato::texto($this->titulo),
            'fecha'         => Formato::fecha($this->fecha),
            'imagen'        => Formato::imagen($this->imagen),
            'imagenMovil'   => Formato::imagen($this->imagenMovil),
            'puntoFocal'    => $this->foco_x === null || $this->foco_y === null
                ? null
                : ['x' => $this->foco_x, 'y' => $this->foco_y],
            'textoEnImagen' => $this->texto_en_imagen,
            'ladoAccion'    => $this->lado_accion,
            'accion'        => Formato::accion($this->accion),
        ];
    }
}
