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
    'sistema_pensiones',
    'afp',
    'cuspp',
    'entidad_financiera',
    'numero_cuenta',
    'tiene_hijos',
    'sueldo_base',
    'tipo_contrato',
    'forma_pago',
    'sede_id',
    'firma_imagen',
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
    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }
}