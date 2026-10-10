<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una universidad de la academia preuniversitaria, con sus ciclos (contrato §3.25). */
class Universidad extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_universidades';

    protected $fillable = ['clave', 'nombre', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: universidad de la academia';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    /** Sus ciclos, en su orden. */
    public function ciclos(): HasMany
    {
        return $this->hasMany(Ciclo::class, 'universidad_id')->orderBy('orden');
    }
}
