<?php

namespace App\Models\Portal;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una red social oficial del colegio. Ver la migración de portal_redes.
 */
class RedSocial extends Model
{
    use Auditable;

    protected $table = 'portal_redes';
    protected $keyType = 'string';
    public $incrementing = false;

    /** Las que admite el contrato del portal: de cada una pone el icono y el color. */
    public const REDES = ['facebook', 'youtube', 'tiktok', 'whatsapp', 'instagram', 'x', 'linkedin'];

    protected $fillable = ['red', 'cuenta', 'url', 'aclaracion', 'orden', 'estado'];

    protected array $camposAuditables = ['red', 'cuenta', 'url', 'aclaracion', 'orden', 'estado'];
    protected string $entidadAuditada = 'portal: red social';

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: (string) Str::uuid7());
    }

    public function nombreAuditado(): string
    {
        return "la red {$this->red} ({$this->cuenta})";
    }

    /** Las que se muestran, en el orden del portal. */
    public function scopePublicadas(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'publicado')->orderBy('orden');
    }
}
