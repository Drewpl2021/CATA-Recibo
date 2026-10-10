<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ciclo de la academia (CicloAcademia, contrato §3.25). Precios y fechas
 * solo si están vigentes; si no, null.
 */
class Ciclo extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_ciclos';

    public const MODALIDADES = ['presencial', 'virtual'];

    protected $fillable = [
        'clave', 'universidad_id', 'nombre', 'turno', 'modalidades',
        'precio', 'precio_virtual', 'inicio', 'fin', 'orden',
    ];

    protected array $camposAuditables = [
        'clave', 'universidad_id', 'nombre', 'turno', 'modalidades',
        'precio', 'precio_virtual', 'inicio', 'fin', 'orden',
    ];
    protected string $entidadAuditada = 'portal: ciclo de la academia';

    protected function casts(): array
    {
        return [
            'modalidades'    => 'array',
            'precio'         => 'decimal:2',
            'precio_virtual' => 'decimal:2',
            'inicio'         => 'date',
            'fin'            => 'date',
            'orden'          => 'integer',
        ];
    }

    public function universidad(): BelongsTo
    {
        return $this->belongsTo(Universidad::class, 'universidad_id');
    }
}
