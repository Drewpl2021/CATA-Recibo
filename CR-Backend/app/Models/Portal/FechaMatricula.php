<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Una fecha del cronograma de matrícula (contrato §3.13). */
class FechaMatricula extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_fechas_matricula';

    protected $fillable = ['clave', 'titulo', 'inicio', 'fin', 'detalle', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'titulo', 'inicio', 'fin', 'detalle', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: fecha de matrícula';

    protected function casts(): array
    {
        return ['inicio' => 'date', 'fin' => 'date', 'orden' => 'integer'];
    }

    /** Las publicadas en orden cronológico, como las quiere el contrato. */
    public function scopeCronologicas(Builder $consulta): Builder
    {
        return $consulta->where('estado', self::PUBLICADO)
            ->orderBy('inicio')
            ->orderBy('orden');
    }
}
