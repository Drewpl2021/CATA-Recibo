<?php

namespace App\Support\Portal;

/**
 * Las subpáginas de Nosotros que el portal tenía escritas en su código hasta
 * el contrato 2.13, con su lugar en el menú: el grupo (por su id, el de la
 * ficha `navegacion`) y su nombre.
 *
 * Desde la 2.13 el menú sale de la API y una subpágina nueva no necesita
 * código, así que esta lista ya no limita qué direcciones se pueden crear:
 * solo propone el grupo y el nombre de estas 16 al crearlas, si quien edita
 * no elige otros.
 */
final class PaginasDeNosotros
{
    /** slug => [id del grupo, nombre en el menú], en el orden del portal. */
    public const PAGINAS = [
        'mision-y-vision'         => ['institucion', 'Misión y Visión'],
        'resena-historica'        => ['institucion', 'Reseña Histórica'],
        'emblema'                 => ['institucion', 'Emblema'],
        'himno'                   => ['institucion', 'Himno'],
        'consejo-directivo'       => ['institucion', 'Consejo Directivo'],
        'administracion'          => ['institucion', 'Administración'],
        'personal-docente'        => ['institucion', 'Personal Docente'],
        'organigrama'             => ['institucion', 'Organigrama'],
        'procesos'                => ['gestion', 'Procesos'],
        'planificacion-anual'     => ['gestion', 'Planificación Anual'],
        'planes-de-mejora'        => ['gestion', 'Planes de Mejora'],
        'evaluacion-docente'      => ['gestion', 'Evaluación Docente'],
        'acreditacion'            => ['gestion', 'Acreditación'],
        'instrumentos-de-gestion' => ['documentos', 'Instrumentos de Gestión'],
        'manual-de-identidad'     => ['documentos', 'Manual de Identidad'],
        'planificador-eva'        => ['documentos', 'Planificador Inteligente EVA'],
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
