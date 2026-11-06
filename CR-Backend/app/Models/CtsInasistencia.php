<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Los días de falta injustificada de un trabajador en un semestre de CTS.
 * Ver la migración de cts_inasistencias y MotorCts::calcular.
 *
 * Sin Auditable: el controlador anota en la auditoría una línea que dice de
 * quién, de qué semestre y de cuántos a cuántos días, que es lo que se busca
 * después ante un reclamo.
 */
class CtsInasistencia extends Model
{
    protected $table = 'cts_inasistencias';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['empleado_id', 'anio', 'periodo', 'dias', 'observacion', 'registrado_por'];

    protected $casts = [
        'anio' => 'integer',
        'dias' => 'integer',
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

    /** Los días de cada trabajador en un semestre: [empleado_id => días]. */
    public static function delSemestre(int $anio, string $periodo): array
    {
        return static::where('anio', $anio)->where('periodo', $periodo)->pluck('dias', 'empleado_id')->map(fn ($d) => (int) $d)->all();
    }
}
