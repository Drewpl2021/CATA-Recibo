<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Empleado extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'dni',
        'nombre',
        'apellido',
        'cargo_id',
        'area_id',
        'telefono',
        'direccion',
        'fecha_ingreso',
        'estado',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }
}