<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un docente como se publica en el portal (contrato §3.21): nombre, cargo y
 * foto, nada más. No es App\Models\Empleado: ver la migración.
 */
class Docente extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_docentes';

    protected $fillable = ['clave', 'nombre', 'cargo', 'nivel_id', 'imagen_id', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'cargo', 'nivel_id', 'imagen_id', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: docente';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(DocenteNivel::class, 'nivel_id');
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
