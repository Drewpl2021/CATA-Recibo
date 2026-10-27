<?php

namespace App\Support\Portal;

use RuntimeException;

/**
 * Al portal se le pide algo que todavía no se cargó en el módulo.
 *
 * Pasa con los datos que el contrato marca como obligatorios (el nombre del
 * colegio, su teléfono…): si faltan no hay respuesta válida que dar, y
 * tampoco se inventa una desde el código, porque todo lo que muestra el
 * portal sale del panel. Se responde 503 NO_DISPONIBLE: el portal muestra
 * «No pudimos cargar…» con «Reintentar» solo en esa sección, y el registro
 * dice qué falta cargar.
 */
class SeccionSinConfigurar extends RuntimeException
{
    public static function falta(string $seccion, ?string $campo = null): self
    {
        return new self($campo === null
            ? "La sección «{$seccion}» del portal no está cargada."
            : "En la sección «{$seccion}» del portal falta «{$campo}».");
    }
}
