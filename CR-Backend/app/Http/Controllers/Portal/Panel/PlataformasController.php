<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Plataforma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * /web-portal/platforms — las plataformas digitales de Propuesta Educativa
 * (contrato §3.26), hasta 8: qué ofrece cada una a quién, su enlace y un
 * aviso si la descripción aún no está confirmada.
 *
 * El portal solo enlaza a cada plataforma: nunca pide usuario ni contraseña.
 */
class PlataformasController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Plataforma::class;
    }

    protected function entidad(): string
    {
        return 'la plataforma';
    }

    protected function topePublicados(): ?int
    {
        return 8;
    }

    protected function plural(): string
    {
        return 'plataformas publicadas';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/propuesta-educativa/plataformas'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'             => [$obligatorio, 'string', 'max:40'],
            'descripcion'        => ['nullable', 'string', 'max:300'],
            'funciones'          => ['sometimes', 'array', 'max:8'],
            'funciones.*'        => ['array:titulo,texto'],
            'funciones.*.titulo' => ['required', 'string', 'max:40'],
            'funciones.*.texto'  => ['required', 'string', 'max:200'],
            'enlace'             => ['nullable', 'array'],
            'enlace.etiqueta'    => ['required_with:enlace', 'string', 'max:40'],
            'enlace.url'         => ['required_with:enlace', 'url', 'starts_with:https://', 'max:2048'],
            'enlace.externo'     => ['sometimes', 'boolean'],
            'aclaracion'         => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function atributos(): array
    {
        return [
            'nombre'             => 'nombre',
            'descripcion'        => 'descripción',
            'funciones'          => 'funciones',
            'funciones.*.titulo' => 'para quién',
            'funciones.*.texto'  => 'qué ofrece',
            'enlace.etiqueta'    => 'texto del enlace',
            'enlace.url'         => 'dirección del enlace',
            'aclaracion'         => 'aviso',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('funciones', $v)) {
            $v['funciones'] = array_map(fn (array $f) => ['titulo' => $f['titulo'], 'texto' => $f['texto']], array_values($v['funciones']));
        }

        if (array_key_exists('enlace', $v)) {
            $v['enlace'] = filled($v['enlace']['url'] ?? null)
                ? ['etiqueta' => $v['enlace']['etiqueta'], 'url' => $v['enlace']['url'], 'externo' => (bool) ($v['enlace']['externo'] ?? true)]
                : null;
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nombre'], 'plataforma');
            $v['funciones'] ??= [];
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'descripcion', 'funciones', 'enlace', 'aclaracion', 'orden', 'estado']);
    }
}
