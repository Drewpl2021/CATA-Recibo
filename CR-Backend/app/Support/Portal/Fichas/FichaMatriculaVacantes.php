<?php

namespace App\Support\Portal\Fichas;

/**
 * Lo de una sola vez de las vacantes (contrato §3.12): la fecha en que se
 * actualizaron y la nota bajo la lista. Las vacantes de cada nivel son su
 * propia lista.
 *
 * La fecha la pone quien edita, al confirmar las cifras: el portal la muestra
 * como «Actualizado el…», y no tiene que moverse sola por corregir una coma.
 */
class FichaMatriculaVacantes implements Ficha
{
    public function nombre(): string
    {
        return 'la nota de las vacantes';
    }

    public function reglas(): array
    {
        return [
            'actualizado' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'nota'        => ['nullable', 'string', 'max:240'],
        ];
    }

    public function atributos(): array
    {
        return ['actualizado' => 'fecha de actualización', 'nota' => 'nota'];
    }

    public function aContenido(array $v): array
    {
        return ['actualizado' => $v['actualizado'] ?? null, 'nota' => $v['nota'] ?? null];
    }

    public function rutas(): array
    {
        return ['/v1/matricula/vacantes'];
    }
}
