<?php

namespace App\Http\Resources\Portal;

use App\Support\Portal\Formato;
use Illuminate\Http\Request;

/**
 * Proyecto (contrato §3.18): el resumen de la lista más el detalle.
 *
 * Ver BannerResource: todas las claves, en el orden del contrato.
 *
 * @mixin \App\Models\Portal\Proyecto
 */
class ProyectoDetalleResource extends ProyectoResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'categoriaNombre' => Formato::texto($this->categoria->nombre),
            'descripcion'     => Formato::textos($this->descripcion),
            'secciones'       => array_map(fn ($seccion) => [
                'titulo' => Formato::texto($seccion['titulo'] ?? null),
                'tipo'   => $seccion['tipo'] ?? null,
                'items'  => array_map(fn ($item) => [
                    'titulo' => Formato::opcional($item['titulo'] ?? null),
                    'texto'  => Formato::texto($item['texto'] ?? null),
                ], $seccion['items'] ?? []),
            ], $this->secciones ?? []),
            'galeria'         => $this->galeria->map(fn ($foto) => Formato::imagen($foto->imagen))->all(),
        ];
    }
}
