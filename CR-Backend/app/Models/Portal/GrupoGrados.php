<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/** Las opciones de grado de un nivel, para «Solicitar información» (contrato §3.8). */
class GrupoGrados extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_grados';

    protected $fillable = ['nivel', 'opciones', 'orden', 'estado'];

    protected array $camposAuditables = ['nivel', 'opciones', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: grados';

    protected function casts(): array
    {
        return ['opciones' => 'array', 'orden' => 'integer'];
    }
}
