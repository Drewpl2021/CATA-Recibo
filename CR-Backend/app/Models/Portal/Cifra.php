<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Una cifra destacada (contrato §3.9). La misma tabla sirve a Inicio, Logros
 * y Propuesta Educativa; `ambito` dice a cuál. Solo cifras reales y
 * verificables.
 */
class Cifra extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_cifras';

    public const AMBITOS = ['portada', 'logros', 'propuesta'];

    protected $fillable = ['ambito', 'clave', 'valor', 'sufijo', 'etiqueta', 'orden', 'estado'];

    protected array $camposAuditables = ['ambito', 'clave', 'valor', 'sufijo', 'etiqueta', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: cifra';

    protected function casts(): array
    {
        return ['valor' => 'integer', 'orden' => 'integer'];
    }

    public function scopeDe(Builder $consulta, string $ambito): Builder
    {
        return $consulta->where('ambito', $ambito);
    }

    public function nombreAuditado(): string
    {
        return "«{$this->valor} {$this->etiqueta}» ({$this->ambito})";
    }
}
