<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un afiche del carrusel de Inicio (contrato §3.2). */
class Banner extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_banners';

    public const CATEGORIAS = ['aviso', 'matricula', 'evento', 'noticia'];
    public const LADOS = ['izquierda', 'derecha'];

    protected $fillable = [
        'clave', 'categoria', 'titulo', 'fecha', 'imagen_id', 'imagen_movil_id',
        'foco_x', 'foco_y', 'texto_en_imagen', 'lado_accion', 'accion', 'orden', 'estado',
    ];

    protected array $camposAuditables = [
        'clave', 'categoria', 'titulo', 'fecha', 'imagen_id', 'imagen_movil_id',
        'foco_x', 'foco_y', 'texto_en_imagen', 'lado_accion', 'accion', 'orden', 'estado',
    ];
    protected string $entidadAuditada = 'portal: banner';

    protected function casts(): array
    {
        return [
            'fecha'           => 'date',
            'foco_x'          => 'integer',
            'foco_y'          => 'integer',
            'texto_en_imagen' => 'boolean',
            'accion'          => 'array',
            'orden'           => 'integer',
        ];
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }

    public function imagenMovil(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_movil_id');
    }
}
