<?php

namespace App\Models\Portal;

use App\Traits\ContenidoDelPortal;
use Illuminate\Database\Eloquent\Model;

/**
 * Una red social oficial del colegio. Ver la migración de portal_redes.
 */
class RedSocial extends Model
{
    use ContenidoDelPortal;

    protected $table = 'portal_redes';

    /** Las que admite el contrato del portal: de cada una pone el icono y el color. */
    public const REDES = ['facebook', 'youtube', 'tiktok', 'whatsapp', 'instagram', 'x', 'linkedin'];

    protected $fillable = ['red', 'cuenta', 'url', 'aclaracion', 'orden', 'estado'];

    protected array $camposAuditables = ['red', 'cuenta', 'url', 'aclaracion', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: red social';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function nombreAuditado(): string
    {
        return "la red {$this->red} ({$this->cuenta})";
    }
}
