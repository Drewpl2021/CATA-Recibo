<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Una corrida de planilla: "Planilla TIC — Septiembre 2026".
 *
 * Agrupa las filas de los trabajadores que se pagan juntos. No guarda ningún
 * monto: cuánta gente tiene y cuánto suma se cuenta sobre sus planillas cada
 * vez que hace falta. Un total copiado aquí sería un total que algún día deja
 * de coincidir con la suma de sus filas.
 */
class PlanillaCorrida extends Model
{
    protected $table = 'planilla_corridas';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nombre',
        'mes',
        'anio',
        'periodo_id',
        'estado',
        'observaciones',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = Str::uuid();
        });
    }

    /** Las filas de los trabajadores que la componen. */
    public function planillas()
    {
        return $this->hasMany(Planilla::class, 'corrida_id');
    }

    /** El año escolar del que cuelga, si se le puso. */
    public function periodo()
    {
        return $this->belongsTo(Periodo::class);
    }

    /** Una corrida cerrada ya se pagó: no se le mueven las cifras. */
    public function estaCerrada(): bool
    {
        return $this->estado === 'cerrada';
    }
}
