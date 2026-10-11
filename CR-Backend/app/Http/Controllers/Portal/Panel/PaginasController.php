<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Pagina;
use App\Support\Portal\PaginasDeNosotros;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/pages — las subpáginas de Nosotros (contrato §3.19 y §3.20):
 * su título, su frase y la frase de su tarjeta en el índice. Sus bloques se
 * editan aparte (y no se tocan aquí).
 *
 * La dirección se elige de las que el portal sabe abrir (PaginasDeNosotros)
 * y no cambia después de creada. En borrador, su dirección responde 404.
 */
class PaginasController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Pagina::class;
    }

    protected function entidad(): string
    {
        return 'la página';
    }

    /** El índice y, si se ve, la página misma. */
    protected function rutasAfectadas(?Model $fila): array
    {
        return $fila?->estado === Pagina::PUBLICADO
            ? ['/v1/nosotros', "/v1/nosotros/paginas/{$fila->slug}"]
            : ['/v1/nosotros'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'slug'    => $fila ? ['prohibited'] : ['required', Rule::in(PaginasDeNosotros::slugs()), Rule::unique('portal_paginas', 'slug')],
            'titulo'  => [$obligatorio, 'string', 'max:60'],
            'bajada'  => ['nullable', 'string', 'max:160'],
            'resumen' => ['nullable', 'string', 'max:140'],
        ];
    }

    protected function atributos(): array
    {
        return ['slug' => 'dirección', 'titulo' => 'título', 'bajada' => 'frase bajo el título', 'resumen' => 'frase de su tarjeta'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            $v['grupo'] = PaginasDeNosotros::grupo($v['slug']);
            $v['nombre'] = PaginasDeNosotros::nombre($v['slug']);
            // Sin bloques: «El contenido de esta página se publicará pronto».
            $v['bloques'] = [];
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'slug', 'titulo', 'bajada', 'resumen', 'orden', 'estado']) + [
            'grupo'   => $fila->grupo ?? PaginasDeNosotros::grupo($fila->slug),
            'nombre'  => $fila->nombre ?? PaginasDeNosotros::nombre($fila->slug),
            'bloques' => count($fila->bloques ?? []),
        ];
    }
}
