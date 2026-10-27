<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\NivelPropuesta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/proposal-levels — los niveles como los presenta Propuesta
 * Educativa (contrato §3.24), hasta 6: descripción, rasgos, talleres y foto
 * 3:2. Es una lista propia, aparte de la de Inicio (home-levels).
 */
class NivelesPropuestaController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return NivelPropuesta::class;
    }

    protected function entidad(): string
    {
        return 'el nivel';
    }

    protected function topePublicados(): ?int
    {
        return 6;
    }

    protected function plural(): string
    {
        return 'niveles publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/propuesta-educativa/niveles'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'            => [$obligatorio, 'string', 'max:40'],
            'rango'             => ['nullable', 'string', 'max:40'],
            'descripcion'       => ['sometimes', 'array', 'max:6'],
            'descripcion.*'     => ['required', 'string', 'max:600'],
            'rasgos'            => ['sometimes', 'array', 'max:8'],
            'rasgos.*'          => ['required', 'string', 'max:50', 'distinct'],
            'talleres'          => ['sometimes', 'array', 'max:12'],
            'talleres.*'        => ['array:titulo,texto'],
            'talleres.*.titulo' => ['required', 'string', 'max:50'],
            'talleres.*.texto'  => ['nullable', 'string', 'max:200'],
            'imagen_id'         => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
        ];
    }

    protected function atributos(): array
    {
        return [
            'nombre'            => 'nombre',
            'rango'             => 'rango',
            'descripcion'       => 'descripción',
            'descripcion.*'     => 'párrafo de la descripción',
            'rasgos'            => 'rasgos',
            'rasgos.*'          => 'rasgo',
            'talleres'          => 'talleres',
            'talleres.*.titulo' => 'nombre del taller',
            'talleres.*.texto'  => 'texto del taller',
            'imagen_id'         => 'foto',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        foreach (['descripcion', 'rasgos'] as $lista) {
            if (array_key_exists($lista, $v)) {
                $v[$lista] = array_values($v[$lista]);
            }
        }

        if (array_key_exists('talleres', $v)) {
            $v['talleres'] = array_map(fn (array $t) => [
                'titulo' => $t['titulo'],
                'texto'  => filled($t['texto'] ?? null) ? $t['texto'] : null,
            ], array_values($v['talleres']));
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nombre'], 'nivel');
            $v['descripcion'] ??= [];
            $v['rasgos'] ??= [];
            $v['talleres'] ??= [];
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'rango', 'descripcion', 'rasgos', 'talleres', 'imagen_id', 'orden', 'estado']) + [
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
