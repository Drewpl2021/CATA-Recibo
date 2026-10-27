<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un nivel como lo presenta Propuesta Educativa (contrato §3.24): con su
 * descripción, sus rasgos y sus talleres. Lista propia, aparte de la de
 * Inicio (Nivel).
 */
class NivelPropuesta extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_niveles_propuesta';

    protected $fillable = ['clave', 'nombre', 'rango', 'descripcion', 'rasgos', 'talleres', 'imagen_id', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'rango', 'descripcion', 'rasgos', 'talleres', 'imagen_id', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: nivel de Propuesta Educativa';

    protected function casts(): array
    {
        return ['descripcion' => 'array', 'rasgos' => 'array', 'talleres' => 'array', 'orden' => 'integer'];
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
