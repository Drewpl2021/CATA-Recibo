<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una categoría del filtro de Proyectos (contrato §3.17). */
class ProyectoCategoria extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_proyecto_categorias';

    protected $fillable = ['clave', 'nombre', 'orden'];

    protected array $camposAuditables = ['clave', 'nombre', 'orden'];
    protected string $entidadAuditada = 'portal: categoría de proyectos';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'categoria_id');
    }
}
