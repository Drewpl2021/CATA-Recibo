<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Modulo extends Model
{
    protected $table = 'modulos';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['modulo_padre_id', 'nombre', 'ruta', 'icono', 'orden', 'estado_registro'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = Str::uuid());
    }

    public function moduloPadre()
    {
        return $this->belongsTo(ModuloPadre::class, 'modulo_padre_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_modulo', 'modulo_id', 'rol_id');
    }
}