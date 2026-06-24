<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ModuloPadre extends Model
{
    protected $table = 'modulo_padre';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['nombre', 'icono', 'orden', 'estado_registro'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = Str::uuid());
    }

    public function modulos()
    {
        return $this->hasMany(Modulo::class, 'modulo_padre_id')->orderBy('orden');
    }
}