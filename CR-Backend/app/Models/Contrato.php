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
        'tipo_contrato_id',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'motivo_fin',
        // Lo cerró una baja, y cuál era su fin antes: para reabrirlo tal
        // cual si la persona se reactiva (ver Empleado::reactivar).
        'cerrado_por_baja',
        'fin_antes_de_baja',
        'observaciones',
        'estado_registro',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid7();
        });
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function tipoContrato()
    {
        return $this->belongsTo(TipoContrato::class);
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'contrato_id');
    }
}