<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una sede como se publica en el portal (contrato §3.6). No es App\Models\Sede,
 * la de RR.HH.: ver la migración de portal_sedes.
 */
class Sede extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_sedes';

    protected $fillable = [
        'clave', 'nombre', 'direccion', 'referencia', 'distrito', 'telefono', 'nota',
        'imagen_id', 'mapa_url', 'correo', 'horario', 'mapa_embed_url', 'orden', 'estado',
    ];

    protected array $camposAuditables = [
        'clave', 'nombre', 'direccion', 'referencia', 'distrito', 'telefono', 'nota',
        'imagen_id', 'mapa_url', 'correo', 'horario', 'mapa_embed_url', 'orden', 'estado',
    ];
    protected string $entidadAuditada = 'portal: sede';

    protected function casts(): array
    {
        return ['telefono' => 'array', 'horario' => 'array', 'orden' => 'integer'];
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }
}
