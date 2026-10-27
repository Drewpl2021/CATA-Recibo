<?php

namespace App\Support\Portal\Fichas;

/**
 * El aviso sobre el cronograma de matrícula (contrato §3.13), en un recuadro
 * sobre la lista. Las fechas son su propia lista.
 */
class FichaMatriculaFechas implements Ficha
{
    public function nombre(): string
    {
        return 'el aviso del cronograma';
    }

    public function reglas(): array
    {
        return ['nota' => ['nullable', 'string', 'max:200']];
    }

    public function atributos(): array
    {
        return ['nota' => 'aviso'];
    }

    public function aContenido(array $v): array
    {
        return ['nota' => $v['nota'] ?? null];
    }

    public function rutas(): array
    {
        return ['/v1/matricula/fechas'];
    }
}
