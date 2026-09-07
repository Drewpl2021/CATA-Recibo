<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cargo extends Model
{
    protected $table = 'cargos';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'descripcion', 'estado'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }
}