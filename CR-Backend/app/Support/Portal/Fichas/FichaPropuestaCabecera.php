<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\ImagenesEnJson;
use App\Support\Portal\ReglasDeAccion;
use Illuminate\Validation\Rule;

/**
 * La cabecera de Propuesta Educativa (contrato §3.3): título, frase, hasta
 * tres figuras (recortes sin fondo o fotos, en orden de importancia) y dos
 * botones, el principal obligatorio.
 */
class FichaPropuestaCabecera implements Ficha
{
    public const TIPOS_DE_FIGURA = ['recorte', 'foto'];

    public function nombre(): string
    {
        return 'la cabecera de Propuesta Educativa';
    }

    public function reglas(): array
    {
        return [
            'titulo'           => ['required', 'string', 'max:70'],
            'bajada'           => ['nullable', 'string', 'max:180'],
            'figuras'          => ['present', 'array', 'max:3'],
            'figuras.*'        => ['array:tipo,imagen'],
            'figuras.*.tipo'   => ['required', Rule::in(self::TIPOS_DE_FIGURA)],
            // El id de una imagen ya subida (uso «recorte» o «foto-cabecera»).
            'figuras.*.imagen' => ['required', 'uuid', 'distinct', Rule::exists('portal_imagenes', 'id')],
        ]
            + ReglasDeAccion::reglas('accionPrincipal', obligatoria: true)
            + ReglasDeAccion::reglas('accionSecundaria');
    }

    public function atributos(): array
    {
        return [
            'titulo'           => 'título',
            'bajada'           => 'frase bajo el título',
            'figuras'          => 'figuras',
            'figuras.*.tipo'   => 'tipo de figura',
            'figuras.*.imagen' => 'imagen de la figura',
            'accionPrincipal'  => 'botón principal',
        ]
            + self::delBoton(ReglasDeAccion::atributos('accionPrincipal'), 'principal')
            + self::delBoton(ReglasDeAccion::atributos('accionSecundaria'), 'secundario');
    }

    /** «texto del botón» → «texto del botón principal», para saber de cuál habla el error. */
    private static function delBoton(array $atributos, string $cual): array
    {
        return array_map(fn (string $nombre) => str_contains($nombre, 'botón') ? "{$nombre} {$cual}" : "{$nombre} del botón {$cual}", $atributos);
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo'           => $v['titulo'],
            'bajada'           => $v['bajada'] ?? null,
            'figuras'          => array_map(fn (array $f) => [
                'tipo'   => $f['tipo'],
                'imagen' => [ImagenesEnJson::CLAVE => $f['imagen']],
            ], array_values($v['figuras'] ?? [])),
            'accionPrincipal'  => ReglasDeAccion::guardar($v['accionPrincipal']),
            'accionSecundaria' => ReglasDeAccion::guardar($v['accionSecundaria'] ?? null),
        ];
    }

    public function rutas(): array
    {
        return ['/v1/propuesta-educativa/cabecera'];
    }
}
