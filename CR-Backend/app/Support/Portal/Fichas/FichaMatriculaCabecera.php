<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\ImagenesEnJson;
use Illuminate\Validation\Rule;

/**
 * La cabecera de la página Matrícula (contrato §3.10): título, frase, el año
 * del proceso y el recorte de un estudiante.
 *
 * El año lo pone el colegio: el portal no lo calcula ni lo deduce de la fecha.
 * Vacío, no se muestra la placa del año.
 */
class FichaMatriculaCabecera implements Ficha
{
    public function nombre(): string
    {
        return 'la cabecera de Matrícula';
    }

    public function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:60'],
            'bajada' => ['nullable', 'string', 'max:160'],
            'anio'   => ['nullable', 'integer', 'between:2000,2100'],
            // El id de una imagen ya subida (POST /web-portal/images, uso «recorte»).
            'figura' => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
        ];
    }

    public function atributos(): array
    {
        return [
            'titulo' => 'título',
            'bajada' => 'frase bajo el título',
            'anio'   => 'año del proceso',
            'figura' => 'recorte del estudiante',
        ];
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo' => $v['titulo'],
            'bajada' => $v['bajada'] ?? null,
            'anio'   => isset($v['anio']) ? (int) $v['anio'] : null,
            'figura' => filled($v['figura'] ?? null) ? [ImagenesEnJson::CLAVE => $v['figura']] : null,
        ];
    }

    public function rutas(): array
    {
        return ['/v1/matricula/cabecera'];
    }
}
