<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * La CTS de un trabajador en un semestre: con qué se calculó y si ya se
 * depositó. Ver la migración de cts_liquidaciones y App\Support\Cts\MotorCts.
 *
 * Sin el trait Auditable a propósito: calcular un semestre crea una fila por
 * trabajador, y la auditoría se llenaría de «Dio de alta» sin decir nada. El
 * controlador anota UNA línea por acción (calcular, depositar), con cuántos.
 */
class CtsLiquidacion extends Model
{
    public const CALCULADA  = 'calculada';
    public const DEPOSITADA = 'depositada';

    protected $table = 'cts_liquidaciones';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id', 'anio', 'periodo', 'desde', 'hasta',
        'sueldo', 'asignacion_familiar', 'bonificacion_cargo', 'remuneracion_fuente',
        'gratificacion', 'gratificacion_fuente', 'sexto_gratificacion', 'remuneracion_computable',
        'meses', 'dias', 'monto',
        'estado', 'fecha_deposito', 'entidad_financiera', 'numero_cuenta', 'documento_id', 'observacion',
    ];

    protected $casts = [
        'anio'                    => 'integer',
        'desde'                   => 'date:Y-m-d',
        'hasta'                   => 'date:Y-m-d',
        'sueldo'                  => 'float',
        'asignacion_familiar'     => 'float',
        'bonificacion_cargo'      => 'float',
        'gratificacion'           => 'float',
        'sexto_gratificacion'     => 'float',
        'remuneracion_computable' => 'float',
        'meses'                   => 'integer',
        'dias'                    => 'integer',
        'monto'                   => 'float',
        'fecha_deposito'          => 'date:Y-m-d',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->id = $m->id ?: Str::uuid7());
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function depositada(): bool
    {
        return $this->estado === self::DEPOSITADA;
    }
}
