<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Rol extends Model
{
    protected $table = 'roles';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'descripcion', 'es_superadmin'];
    protected $casts = ['es_superadmin' => 'boolean'];

    /** Los módulos que tiene marcados: son a la vez su menú y sus permisos (ver App\Support\Permisos). */
    public function modulos()
    {
        return $this->belongsToMany(Modulo::class, 'rol_modulo', 'rol_id', 'modulo_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid7());
    }
}