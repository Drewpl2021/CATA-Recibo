<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Contrato extends Model
{
    protected $table = 'contratos';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'tipo_contrato',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'motivo_fin',
        'documento_id',
        'observaciones',
        'estado_registro',
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

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }
}