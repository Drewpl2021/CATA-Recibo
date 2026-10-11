<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\ProcesoMatricula;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * /web-portal/enrollment-processes — un proceso de matrícula por tipo de
 * estudiante (nuevos, regulares…), contrato §3.11.
 *
 * Los pasos y las listas (requisitos, documentos) van dentro del proceso, en
 * su orden: se editan siempre junto con él y se guardan enteros. El portal
 * numera los pasos; en el título no va «1er paso».
 */
class ProcesosMatriculaController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return ProcesoMatricula::class;
    }

    protected function entidad(): string
    {
        return 'el proceso';
    }

    protected function topePublicados(): ?int
    {
        return 4;
    }

    protected function plural(): string
    {
        return 'procesos publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/matricula/procesos'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'  => [$obligatorio, 'string', 'max:40'],
            'resumen' => ['nullable', 'string', 'max:160'],

            'pasos'           => ['sometimes', 'array', 'max:8'],
            'pasos.*'         => ['array:titulo,detalle'],
            'pasos.*.titulo'  => ['required', 'string', 'max:60'],
            'pasos.*.detalle' => ['nullable', 'string', 'max:240'],

            'listas'           => ['sometimes', 'array', 'max:4'],
            'listas.*'         => ['array:titulo,items'],
            'listas.*.titulo'  => ['required', 'string', 'max:60'],
            'listas.*.items'   => ['required', 'array', 'min:1', 'max:15'],
            'listas.*.items.*' => ['required', 'string', 'max:240'],
        ];
    }

    protected function atributos(): array
    {
        return [
            'nombre'           => 'para quién es',
            'resumen'          => 'resumen',
            'pasos'            => 'pasos',
            'pasos.*.titulo'   => 'título del paso',
            'pasos.*.detalle'  => 'detalle del paso',
            'listas'           => 'listas',
            'listas.*.titulo'  => 'título de la lista',
            'listas.*.items'   => 'elementos de la lista',
            'listas.*.items.*' => 'elemento de la lista',
        ];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('pasos', $v)) {
            $v['pasos'] = array_map(fn (array $p) => [
                'titulo'  => $p['titulo'],
                'detalle' => $p['detalle'] ?? null,
            ], array_values($v['pasos']));
        }

        if (array_key_exists('listas', $v)) {
            $v['listas'] = array_map(fn (array $l) => [
                'titulo' => $l['titulo'],
                'items'  => array_values($l['items']),
            ], array_values($v['listas']));
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nombre'], 'proceso');
            $v['pasos'] ??= [];
            $v['listas'] ??= [];
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'resumen', 'pasos', 'listas', 'orden', 'estado']);
    }
}
