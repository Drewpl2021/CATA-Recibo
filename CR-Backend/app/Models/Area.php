<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Area extends Model
{
    protected $table = 'areas';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'nombre', 'descripcion'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: Str::uuid());
    }
}