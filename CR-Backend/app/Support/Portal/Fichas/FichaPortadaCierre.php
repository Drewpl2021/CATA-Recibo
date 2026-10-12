<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\ReglasDeAccion;

/**
 * El cierre de Inicio (contrato 2.13, /v1/portada/cierre): el título completo
 * («¿Quieres conocer CATA?») y hasta dos botones; el primero es el dorado y
 * el segundo, el blanco. Sin botones, solo el título.
 */
class FichaPortadaCierre implements Ficha
{
    public function nombre(): string
    {
        return 'el cierre de Inicio';
    }

    public function reglas(): array
    {
        return [
            'titulo'   => ['required', 'string', 'max:60'],
            'acciones' => ['present', 'array', 'max:2'],
        ]
            + ReglasDeAccion::reglas('acciones.0')
            + ReglasDeAccion::reglas('acciones.1');
    }

    public function atributos(): array
    {
        return ['titulo' => 'título', 'acciones' => 'botones']
            + self::delBoton(ReglasDeAccion::atributos('acciones.0'), 'dorado')
            + self::delBoton(ReglasDeAccion::atributos('acciones.1'), 'blanco');
    }

    /** «texto del botón» → «texto del botón dorado», para saber de cuál habla el error. */
    private static function delBoton(array $atributos, string $cual): array
    {
        return array_map(fn (string $nombre) => str_contains($nombre, 'botón') ? "{$nombre} {$cual}" : "{$nombre} del botón {$cual}", $atributos);
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo'   => $v['titulo'],
            'acciones' => array_values(array_filter([
                ReglasDeAccion::guardar($v['acciones'][0] ?? null),
                ReglasDeAccion::guardar($v['acciones'][1] ?? null),
            ])),
        ];
    }

    public function rutas(): array
    {
        return ['/v1/portada/cierre'];
    }
}
