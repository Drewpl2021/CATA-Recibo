<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Banner;
use App\Models\Portal\Imagen;
use App\Support\Portal\ReglasDeAccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/banners — el carrusel de Inicio (contrato §3.2).
 *
 * Hasta 8 publicados, del más reciente al más antiguo: lo nuevo entra
 * arriba, y se puede mover. La imagen de escritorio es obligatoria (1900:700)
 * y lleva su punto focal, el que no se recorta si la proporción no calza.
 */
class BannersController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Banner::class;
    }

    protected function entidad(): string
    {
        return 'el banner';
    }

    protected function topePublicados(): ?int
    {
        return 8;
    }

    protected function plural(): string
    {
        return 'banners publicados';
    }

    protected function nuevoAlPrincipio(): bool
    {
        return true;
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/portada/banners'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';
        $imagen = ['uuid', Rule::exists('portal_imagenes', 'id')];

        return [
            'titulo'          => [$obligatorio, 'string', 'max:80'],
            'categoria'       => [$obligatorio, Rule::in(Banner::CATEGORIAS)],
            'fecha'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'imagen_id'       => [$obligatorio, ...$imagen],
            'imagen_movil_id' => ['nullable', ...$imagen],
            // El punto que no se recorta, en %; los dos o ninguno (null = centro).
            'foco_x'          => ['nullable', 'required_with:foco_y', 'integer', 'between:0,100'],
            'foco_y'          => ['nullable', 'required_with:foco_x', 'integer', 'between:0,100'],
            'texto_en_imagen' => ['sometimes', 'boolean'],
            'lado_accion'     => ['sometimes', Rule::in(Banner::LADOS)],
        ] + ReglasDeAccion::reglas('accion');
    }

    protected function atributos(): array
    {
        return [
            'titulo'          => 'título',
            'categoria'       => 'categoría',
            'fecha'           => 'fecha',
            'imagen_id'       => 'imagen de escritorio',
            'imagen_movil_id' => 'imagen para celular',
            'foco_x'          => 'punto focal',
            'foco_y'          => 'punto focal',
            'lado_accion'     => 'lado del botón',
        ] + ReglasDeAccion::atributos('accion');
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('accion', $v)) {
            $v['accion'] = ReglasDeAccion::guardar($v['accion']);
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'banner');
            $v['texto_en_imagen'] ??= false;
            $v['lado_accion'] ??= 'derecha';
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only([
            'id', 'clave', 'categoria', 'titulo', 'imagen_id', 'imagen_movil_id', 'foco_x', 'foco_y',
            'texto_en_imagen', 'lado_accion', 'accion', 'orden', 'estado',
        ]) + [
            'fecha'        => $fila->fecha?->format('Y-m-d'),
            'imagen'       => Imagen::find($fila->imagen_id)?->aPanel(),
            'imagen_movil' => $fila->imagen_movil_id ? Imagen::find($fila->imagen_movil_id)?->aPanel() : null,
        ];
    }
}
