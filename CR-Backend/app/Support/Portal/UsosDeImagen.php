<?php

namespace App\Support\Portal;

/**
 * Para qué se sube cada imagen del portal y qué espera el portal de ella
 * (contrato §1.7 y la descripción de cada campo).
 *
 *   proporcion   → cómo la recorta el portal (centrada, o en el punto focal
 *                  en los banners). null = se muestra completa, sin recorte.
 *   minimo       → el tamaño mínimo recomendado. Más chica se acepta, con aviso.
 *   transparente → un recorte (una persona sin fondo): se conserva la
 *                  transparencia y se avisa si la foto no la tiene.
 *   foco         → si el panel deja elegir el punto que no se recorta.
 *
 * Es la única fuente: el panel la pide (GET /web-portal/images/uses) para
 * dibujar el marco de la vista previa con la misma proporción.
 */
final class UsosDeImagen
{
    public const USOS = [
        'banner'        => ['nombre' => 'Banner del carrusel (escritorio)', 'proporcion' => [1900, 700], 'minimo' => [1920, 708], 'transparente' => false, 'foco' => true],
        'banner-movil'  => ['nombre' => 'Banner del carrusel (celular)', 'proporcion' => [4, 5], 'minimo' => [1080, 1350], 'transparente' => false, 'foco' => false],
        'sede'          => ['nombre' => 'Foto de sede', 'proporcion' => [4, 3], 'minimo' => [1200, 900], 'transparente' => false, 'foco' => false],
        'nivel'         => ['nombre' => 'Foto de nivel', 'proporcion' => [3, 2], 'minimo' => [1200, 800], 'transparente' => false, 'foco' => false],
        'quienes-somos' => ['nombre' => 'Foto de Quiénes somos', 'proporcion' => [4, 5], 'minimo' => [1200, 1500], 'transparente' => false, 'foco' => false],
        'noticia'       => ['nombre' => 'Foto de noticia', 'proporcion' => [3, 2], 'minimo' => [1200, 800], 'transparente' => false, 'foco' => false],
        'proyecto'      => ['nombre' => 'Foto de proyecto', 'proporcion' => [3, 2], 'minimo' => [1200, 800], 'transparente' => false, 'foco' => false],
        'logro'         => ['nombre' => 'Foto de logro', 'proporcion' => [3, 2], 'minimo' => [1200, 800], 'transparente' => false, 'foco' => false],
        'persona'       => ['nombre' => 'Foto de una persona', 'proporcion' => [1, 1], 'minimo' => [400, 400], 'transparente' => false, 'foco' => false],
        'recorte'       => ['nombre' => 'Recorte de un estudiante (sin fondo)', 'proporcion' => [1, 2], 'minimo' => [500, 1000], 'transparente' => true, 'foco' => false],
        'foto-cabecera' => ['nombre' => 'Foto de cabecera', 'proporcion' => [4, 3], 'minimo' => [1600, 1200], 'transparente' => false, 'foco' => false],
        'contenido'     => ['nombre' => 'Imagen de contenido (completa)', 'proporcion' => null, 'minimo' => [800, 0], 'transparente' => false, 'foco' => false],
    ];

    /** El lado más largo que se guarda: más que eso solo pesa, nadie lo ve. */
    public const LADO_MAXIMO = 2560;

    /** Lo que recomienda el contrato por imagen. */
    public const PESO_RECOMENDADO = 400 * 1024;

    public static function claves(): array
    {
        return array_keys(self::USOS);
    }

    public static function de(string $uso): array
    {
        return self::USOS[$uso];
    }
}
