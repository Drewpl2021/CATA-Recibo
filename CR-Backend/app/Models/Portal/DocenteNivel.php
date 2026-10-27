<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un grupo de la página Personal Docente, con su filtro (contrato §3.21). */
class DocenteNivel extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_docentes_niveles';

    protected $fillable = ['clave', 'nombre', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: nivel de Personal Docente';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function docentes(): HasMany
    {
        return $this->hasMany(Docente::class, 'nivel_id');
    }
}
