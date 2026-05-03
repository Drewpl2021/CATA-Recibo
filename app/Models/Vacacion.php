<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Vacacion extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'motivo',
        'estado',
        'aprobado_por',
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
}