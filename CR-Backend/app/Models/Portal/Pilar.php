<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/** Un pilar de Propuesta Educativa (contrato §3.23). */
class Pilar extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_pilares';

    protected $fillable = ['clave', 'titulo', 'texto', 'puntos', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'titulo', 'texto', 'puntos', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: pilar';

    protected function casts(): array
    {
        return ['puntos' => 'array', 'orden' => 'integer'];
    }
}
