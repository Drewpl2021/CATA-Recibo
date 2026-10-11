<?php

namespace App\Support\Portal\Fichas;

/**
 * La introducción de los pilares de Propuesta Educativa (contrato §3.23):
 * un título y de 1 a 6 párrafos. Sin introducción (null), el portal pasa
 * directo a los pilares. Las cifras y los pilares son listas aparte.
 */
class FichaPropuestaPilares implements Ficha
{
    public function nombre(): string
    {
        return 'la introducción de los pilares';
    }

    public function reglas(): array
    {
        return [
            'introduccion'            => ['nullable', 'array:titulo,parrafos'],
            'introduccion.titulo'     => ['required_with:introduccion', 'string', 'max:60'],
            'introduccion.parrafos'   => ['required_with:introduccion', 'array', 'min:1', 'max:6'],
            'introduccion.parrafos.*' => ['required', 'string', 'max:600'],
        ];
    }

    public function atributos(): array
    {
        return [
            'introduccion'            => 'introducción',
            'introduccion.titulo'     => 'título de la introducción',
            'introduccion.parrafos'   => 'párrafos de la introducción',
            'introduccion.parrafos.*' => 'párrafo',
        ];
    }

    public function aContenido(array $v): array
    {
        $introduccion = $v['introduccion'] ?? null;

        return [
            'introduccion' => $introduccion ? [
                'titulo'   => $introduccion['titulo'],
                'parrafos' => array_values($introduccion['parrafos']),
            ] : null,
        ];
    }

    public function rutas(): array
    {
        return ['/v1/propuesta-educativa/pilares'];
    }
}
