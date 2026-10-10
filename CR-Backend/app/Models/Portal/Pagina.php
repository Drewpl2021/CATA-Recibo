<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/**
 * Una subpágina de Nosotros, armada con bloques (contrato §3.19 y §3.20).
 * Las imágenes de sus bloques van por id ("imagenId") a portal_imagenes.
 */
class Pagina extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_paginas';

    /** Los tipos de bloque del contrato, en el orden de su tabla. */
    public const TIPOS_DE_BLOQUE = [
        'texto', 'destacado', 'lista', 'hitos', 'imagen', 'personas',
        'mensaje', 'letra', 'audio', 'organigrama', 'enlaces',
    ];

    protected $fillable = ['slug', 'grupo', 'nombre', 'titulo', 'bajada', 'resumen', 'bloques', 'orden', 'estado'];

    protected array $camposAuditables = ['slug', 'grupo', 'nombre', 'titulo', 'bajada', 'resumen', 'bloques', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: página de Nosotros';

    protected function casts(): array
    {
        return ['bloques' => 'array', 'orden' => 'integer'];
    }
}
