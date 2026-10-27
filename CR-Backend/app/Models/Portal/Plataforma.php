<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/** Una plataforma digital del colegio (contrato §3.26). */
class Plataforma extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_plataformas';

    protected $fillable = ['clave', 'nombre', 'descripcion', 'funciones', 'enlace', 'aclaracion', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'descripcion', 'funciones', 'enlace', 'aclaracion', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: plataforma digital';

    protected function casts(): array
    {
        return ['funciones' => 'array', 'enlace' => 'array', 'orden' => 'integer'];
    }
}
