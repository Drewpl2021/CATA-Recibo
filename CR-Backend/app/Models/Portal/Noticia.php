<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una noticia o un comunicado (contrato §3.7). */
class Noticia extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_noticias';

    public const TIPOS = ['noticia', 'comunicado'];

    protected $fillable = ['clave', 'slug', 'tipo', 'titulo', 'resumen', 'fecha', 'imagen_id', 'enlace', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'slug', 'tipo', 'titulo', 'resumen', 'fecha', 'imagen_id', 'enlace', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: noticia';

    protected function casts(): array
    {
        return ['fecha' => 'date', 'enlace' => 'array', 'orden' => 'integer'];
    }

    /**
     * Las publicadas, de la más reciente a la más antigua. No es el orden
     * manual de las demás listas: el contrato las quiere por fecha.
     */
    public function scopeRecientes(Builder $consulta): Builder
    {
        return $consulta->where('estado', self::PUBLICADO)
            ->orderByDesc('fecha')
            ->orderBy('orden');
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
