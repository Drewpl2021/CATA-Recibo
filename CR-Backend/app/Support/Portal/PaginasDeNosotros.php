<?php

namespace App\Support\Portal;

/**
 * Las subpáginas de Nosotros que el portal sabe abrir (contrato §3.19): su
 * dirección es estructura del sitio y vive en el código del portal
 * (src/sitio/mapa.ts), con su grupo y su nombre en el menú. Una página con
 * otro slug no tendría cómo verse, por eso el panel solo ofrece estas.
 *
 * El grupo y el nombre se guardan en portal_paginas para el menú del
 * contrato 2.13 (§7.2); hasta entonces el portal usa los suyos.
 */
final class PaginasDeNosotros
{
    /** slug => [grupo, nombre en el menú], en el orden del portal. */
    public const PAGINAS = [
        'mision-y-vision'         => ['Institución', 'Misión y Visión'],
        'resena-historica'        => ['Institución', 'Reseña Histórica'],
        'emblema'                 => ['Institución', 'Emblema'],
        'himno'                   => ['Institución', 'Himno'],
        'consejo-directivo'       => ['Institución', 'Consejo Directivo'],
        'administracion'          => ['Institución', 'Administración'],
        'personal-docente'        => ['Institución', 'Personal Docente'],
        'organigrama'             => ['Institución', 'Organigrama'],
        'procesos'                => ['Gestión', 'Procesos'],
        'planificacion-anual'     => ['Gestión', 'Planificación Anual'],
        'planes-de-mejora'        => ['Gestión', 'Planes de Mejora'],
        'evaluacion-docente'      => ['Gestión', 'Evaluación Docente'],
        'acreditacion'            => ['Gestión', 'Acreditación'],
        'instrumentos-de-gestion' => ['Documentos', 'Instrumentos de Gestión'],
        'manual-de-identidad'     => ['Documentos', 'Manual de Identidad'],
        'planificador-eva'        => ['Documentos', 'Planificador Inteligente EVA'],
    ];

    public static function slugs(): array
    {
        return array_keys(self::PAGINAS);
    }

    public static function grupo(string $slug): ?string
    {
        return self::PAGINAS[$slug][0] ?? null;
    }

    public static function nombre(string $slug): ?string
    {
        return self::PAGINAS[$slug][1] ?? null;
    }
}
