<?php

namespace App\Support\Portal\Fichas;

/**
 * La sección Niveles de Inicio (contrato §3.5): su título y el enlace a la
 * propuesta educativa (sin enlace, se oculta). Los niveles son su propia
 * lista.
 */
class FichaNiveles implements Ficha
{
    public function nombre(): string
    {
        return 'la sección Niveles';
    }

    public function reglas(): array
    {
        return [
            'titulo'       => ['required', 'string', 'max:60'],
            'propuestaUrl' => ['nullable', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }

    public function atributos(): array
    {
        return ['titulo' => 'título', 'propuestaUrl' => 'enlace a la propuesta educativa'];
    }

    public function aContenido(array $v): array
    {
        return ['titulo' => $v['titulo'], 'propuestaUrl' => $v['propuestaUrl'] ?? null];
    }

    public function rutas(): array
    {
        return ['/v1/niveles'];
    }
}
