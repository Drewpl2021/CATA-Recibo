<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/** Un proceso de matrícula, con sus pasos y sus listas (contrato §3.11). */
class ProcesoMatricula extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_procesos_matricula';

    protected $fillable = ['clave', 'nombre', 'resumen', 'pasos', 'listas', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'nombre', 'resumen', 'pasos', 'listas', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: proceso de matrícula';

    protected function casts(): array
    {
        return ['pasos' => 'array', 'listas' => 'array', 'orden' => 'integer'];
    }
}
