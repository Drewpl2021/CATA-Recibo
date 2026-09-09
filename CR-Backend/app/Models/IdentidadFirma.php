<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IdentidadFirma extends Model
{
    protected $table = 'identidades_firma';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'firma_imagen',
        'huella_imagen',
        'registrado_por',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
