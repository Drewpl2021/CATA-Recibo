<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un proyecto, con su resumen (§3.17) y su detalle (§3.18). `etapa` es el
 * `estado` del contrato: ver la migración.
 */
class Proyecto extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_proyectos';

    public const ETAPAS = ['en-curso', 'proximo', 'finalizado'];
    public const TIPOS_DE_SECCION = ['lista', 'numerada'];

    protected $fillable = [
        'clave', 'slug', 'titulo', 'lema', 'resumen', 'categoria_id', 'niveles', 'etapa',
        'fecha', 'imagen_id', 'descripcion', 'secciones', 'orden', 'estado',
    ];

    protected array $camposAuditables = [
        'clave', 'slug', 'titulo', 'lema', 'resumen', 'categoria_id', 'niveles', 'etapa',
        'fecha', 'imagen_id', 'descripcion', 'secciones', 'orden', 'estado',
    ];
    protected string $entidadAuditada = 'portal: proyecto';

    protected function casts(): array
    {
        return [
            'niveles'     => 'array',
            'fecha'       => 'date',
            'descripcion' => 'array',
            'secciones'   => 'array',
            'orden'       => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ProyectoCategoria::class, 'categoria_id');
    }

    public function imagen(): BelongsTo
    {
        return $this->belongsTo(Imagen::class, 'imagen_id');
    }

    /** La galería, en su orden. */
    public function galeria(): HasMany
    {
        return $this->hasMany(ProyectoImagen::class, 'proyecto_id')->orderBy('orden');
    }
}
