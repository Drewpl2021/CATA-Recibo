<?php

namespace App\Support\Portal\Fichas;

/**
 * La cabecera de la página Logros (contrato §3.22): título y frase. Las
 * cifras y los logros son sus propias listas.
 */
class FichaLogros implements Ficha
{
    public function nombre(): string
    {
        return 'la cabecera de Logros';
    }

    public function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:60'],
            'bajada' => ['nullable', 'string', 'max:200'],
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
        return ['/v1/logros'];
    }
}
