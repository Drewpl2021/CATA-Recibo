<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una foto de la galería de un proyecto (contrato §3.18). */
class ProyectoImagen extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_proyecto_imagenes';

    protected $fillable = ['proyecto_id', 'imagen_id', 'orden'];

    protected array $camposAuditables = ['imagen_id', 'orden'];
    protected string $entidadAuditada = 'portal: foto de la galería de un proyecto';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
