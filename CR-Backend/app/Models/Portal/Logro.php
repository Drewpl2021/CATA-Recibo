<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un logro del colegio (contrato §3.22). Solo los confirmados por el colegio,
 * con su fuente; un dato que no se conoce se deja en null, no se inventa.
 */
class Logro extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_logros';

    public const CATEGORIAS = ['academico', 'deportivo', 'artistico', 'institucional'];

    protected $fillable = [
        'clave', 'titulo', 'categoria', 'anio', 'nivel', 'descripcion', 'imagen_id',
        'destacado', 'fuente', 'aclaracion', 'orden', 'estado',
    ];

    protected array $camposAuditables = [
        'clave', 'titulo', 'categoria', 'anio', 'nivel', 'descripcion', 'imagen_id',
        'destacado', 'fuente', 'aclaracion', 'orden', 'estado',
    ];
    protected string $entidadAuditada = 'portal: logro';

    protected function casts(): array
    {
        return ['anio' => 'integer', 'destacado' => 'boolean', 'fuente' => 'array', 'orden' => 'integer'];
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
