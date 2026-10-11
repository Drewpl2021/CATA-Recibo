<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Logro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/achievements — los logros del colegio (contrato §3.22).
 *
 * Solo los que el colegio confirmó, cada uno con su fuente. Lo que no se sabe
 * se deja vacío (el año sin año, no inventado) y, si el dato está incompleto,
 * se dice a la vista con `aclaracion` («Año por confirmar»).
 */
class LogrosController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Logro::class;
    }

    protected function entidad(): string
    {
        return 'el logro';
    }

    protected function topePublicados(): ?int
    {
        return 200;
    }

    protected function plural(): string
    {
        return 'logros publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/logros'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'titulo'         => [$obligatorio, 'string', 'max:140'],
            'categoria'      => [$obligatorio, Rule::in(Logro::CATEGORIAS)],
            'anio'           => ['nullable', 'integer', 'between:1900,2100'],
            'nivel'          => ['nullable', 'string', 'max:60'],
            'descripcion'    => ['nullable', 'string', 'max:300'],
            'imagen_id'      => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
            'destacado'      => ['sometimes', 'boolean'],
            // De dónde sale el dato. La dirección, solo si es pública (https).
            'fuente'           => ['nullable', 'array'],
            'fuente.etiqueta'  => ['required_with:fuente', 'nullable', 'string', 'max:120'],
            'fuente.url'       => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
            'aclaracion'     => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('fuente', $v)) {
            $v['fuente'] = filled($v['fuente']['etiqueta'] ?? null)
                ? ['etiqueta' => $v['fuente']['etiqueta'], 'url' => $v['fuente']['url'] ?? null]
                : null;
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'logro');
            $v['destacado'] ??= false;
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only([
            'id', 'clave', 'titulo', 'categoria', 'anio', 'nivel', 'descripcion', 'imagen_id',
            'destacado', 'fuente', 'aclaracion', 'orden', 'estado',
        ]) + [
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
