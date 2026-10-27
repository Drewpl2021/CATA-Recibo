<?php

namespace App\Support\Portal\Fichas;

/**
 * El índice de Nosotros (contrato §3.19): su título y su frase. La frase de
 * cada subpágina va con la subpágina (su `resumen`).
 */
class FichaNosotros implements Ficha
{
    public function nombre(): string
    {
        return 'el índice de Nosotros';
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
        return ['/v1/nosotros'];
    }
}
