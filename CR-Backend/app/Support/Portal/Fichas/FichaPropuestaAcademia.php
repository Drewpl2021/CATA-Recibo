<?php

namespace App\Support\Portal\Fichas;

/**
 * La academia preuniversitaria de Propuesta Educativa (contrato §3.25):
 * título, descripción y un aviso para toda la academia («Precios y fechas
 * por actualizar»). Las universidades y sus ciclos son listas aparte.
 */
class FichaPropuestaAcademia implements Ficha
{
    public function nombre(): string
    {
        return 'los textos de la academia';
    }

    public function reglas(): array
    {
        return [
            'titulo'      => ['required', 'string', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:240'],
            'aclaracion'  => ['nullable', 'string', 'max:80'],
        ];
    }

    public function atributos(): array
    {
        return ['titulo' => 'título', 'descripcion' => 'descripción', 'aclaracion' => 'aviso'];
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo'      => $v['titulo'],
            'descripcion' => $v['descripcion'] ?? null,
            'aclaracion'  => $v['aclaracion'] ?? null,
        ];
    }

    public function rutas(): array
    {
        return ['/v1/propuesta-educativa/academia'];
    }
}
