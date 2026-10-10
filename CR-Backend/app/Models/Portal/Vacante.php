<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/**
 * Las vacantes de un nivel (contrato §3.12). `disponibilidad` es el `estado`
 * del contrato: ver la migración. Solo cifras reales: sin confirmar, null o
 * 'consultar'.
 */
class Vacante extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_vacantes';

    public const DISPONIBILIDADES = ['disponible', 'pocas', 'agotadas', 'consultar'];

    protected $fillable = ['clave', 'nivel', 'detalle', 'disponibilidad', 'vacantes', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nivel', 'detalle', 'disponibilidad', 'vacantes', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: vacantes';

    protected function casts(): array
    {
        return ['vacantes' => 'integer', 'orden' => 'integer'];
    }
}
