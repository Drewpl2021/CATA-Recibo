<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProyectoResumen (contrato §3.17), la tarjeta de la lista. El detalle
 * (§3.18) es esto más sus propios campos: ver ProyectoDetalleResource.
 * `etapa` es el `estado` del contrato.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Proyecto
 */
class ProyectoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->clave,
            'slug'      => $this->slug,
            'titulo'    => Formato::texto($this->titulo),
            'lema'      => Formato::opcional($this->lema),
            'resumen'   => Formato::texto($this->resumen),
            'categoria' => $this->categoria->clave,
            'niveles'   => Formato::textos($this->niveles),
            'estado'    => $this->etapa,
            'fecha'     => Formato::fecha($this->fecha),
            'foto'      => Formato::imagen($this->imagen),
        ];
    }
}
