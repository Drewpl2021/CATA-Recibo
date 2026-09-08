<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una solicitud de vacaciones.
 *
 * Ojo con `estado` y `aprobado_por`: son asignables porque el controlador
 * los escribe al resolver la solicitud, pero NUNCA deben llenarse desde el
 * cuerpo de una petición. Por hacerlo con `$request->all()` cualquiera podía
 * crear sus vacaciones ya aprobadas. En VacacionController se pasa campo por
 * campo justamente por eso.
 */
class Vacacion extends Model
{
    protected $table = 'vacaciones';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'periodo_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'motivo',
        'estado',
        'observacion',
        'aprobado_por',
        'aprobado_at',
        'estado_registro',
    ];

    protected $casts = [
        'fecha_inicio'     => 'date:Y-m-d',
        'fecha_fin'        => 'date:Y-m-d',
        'dias_solicitados' => 'integer',
        'aprobado_at'      => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
