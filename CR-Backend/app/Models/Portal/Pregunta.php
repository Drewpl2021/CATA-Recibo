<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/** Una pregunta frecuente de matrícula (contrato §3.14). */
class Pregunta extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_preguntas';

    protected $fillable = ['clave', 'pregunta', 'respuesta', 'orden', 'estado'];

    protected array $camposAuditables = ['clave', 'pregunta', 'respuesta', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: pregunta frecuente';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }
}
