<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TipoContrato extends Model
{
    protected $table = 'tipos_contrato';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'requiere_fecha_fin', 'permite_vacaciones', 'estado'];
    protected $casts = ['requiere_fecha_fin' => 'boolean', 'permite_vacaciones' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid7());
    }
}
