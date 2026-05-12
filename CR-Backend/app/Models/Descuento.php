<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Descuento extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'empleado_id',
        'tipo',
        'monto',
        'mes',
        'anio',
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
        return $this->belongsTo(Empleado::class, 'empleado_id');
    }
}