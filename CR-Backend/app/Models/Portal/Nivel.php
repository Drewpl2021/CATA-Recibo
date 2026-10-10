<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un nivel como lo presenta Inicio (contrato §3.5). Propuesta Educativa tiene
 * su propia lista (NivelPropuesta) y Personal Docente la suya (DocenteNivel):
 * ver la migración de portal_niveles.
 */
class Nivel extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_niveles';

    protected $fillable = ['clave', 'nombre', 'rango', 'sede', 'reconocimiento', 'imagen_id', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'rango', 'sede', 'reconocimiento', 'imagen_id', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: nivel de Inicio';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
