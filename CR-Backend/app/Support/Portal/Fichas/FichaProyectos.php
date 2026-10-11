<?php

namespace App\Support\Portal\Fichas;

/**
 * La cabecera de la página Proyectos (contrato §3.17): título y frase. Las
 * categorías y los proyectos son sus propias listas.
 */
class FichaProyectos implements Ficha
{
    public function nombre(): string
    {
        return 'la cabecera de Proyectos';
    }

    public function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:60'],
            'bajada' => ['nullable', 'string', 'max:240'],
        ];
    }

    public function atributos(): array
    {
        return ['titulo' => 'título', 'bajada' => 'frase bajo el título'];
    }

    public function aContenido(array $v): array
    {
        return ['titulo' => $v['titulo'], 'bajada' => $v['bajada'] ?? null];
    }

    public function rutas(): array
    {
        return ['/v1/proyectos'];
    }
}
