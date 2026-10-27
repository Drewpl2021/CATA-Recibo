<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\ImagenesEnJson;
use Illuminate\Validation\Rule;

/**
 * «Quiénes somos» de Inicio (contrato §3.4): título, de 1 a 6 párrafos, una
 * foto 4:5 y el significado de los símbolos del emblema (hasta 8; sin
 * símbolos, el bloque no se muestra).
 */
class FichaQuienesSomos implements Ficha
{
    public function nombre(): string
    {
        return 'Quiénes somos';
    }

    public function reglas(): array
    {
        return [
            'titulo'                => ['required', 'string', 'max:80'],
            'parrafos'              => ['required', 'array', 'min:1', 'max:6'],
            'parrafos.*'            => ['required', 'string', 'max:600'],
            // El id de una imagen ya subida (uso «quienes-somos», 4:5).
            'foto'                  => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
            'simbolos'              => ['present', 'array', 'max:8'],
            'simbolos.*'            => ['array:nombre,significado'],
            'simbolos.*.nombre'     => ['required', 'string', 'max:40'],
            'simbolos.*.significado' => ['required', 'string', 'max:160'],
        ];
    }

    public function atributos(): array
    {
        return [
            'titulo'                 => 'título',
            'parrafos'               => 'párrafos',
            'parrafos.*'             => 'párrafo',
            'foto'                   => 'foto',
            'simbolos'               => 'símbolos',
            'simbolos.*.nombre'      => 'nombre del símbolo',
            'simbolos.*.significado' => 'significado del símbolo',
        ];
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo'   => $v['titulo'],
            'parrafos' => array_values($v['parrafos']),
            'foto'     => filled($v['foto'] ?? null) ? [ImagenesEnJson::CLAVE => $v['foto']] : null,
            'simbolos' => array_map(fn (array $s) => [
                'nombre'      => $s['nombre'],
                'significado' => $s['significado'],
            ], array_values($v['simbolos'] ?? [])),
        ];
    }

    public function rutas(): array
    {
        return ['/v1/quienes-somos'];
    }
}
