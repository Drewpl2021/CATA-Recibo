<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Planilla extends Model
{
    protected $table = 'planilla';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'empleado_id',
        'mes',
        'anio',
        'sueldo_base',
        'bonificaciones',
        'descuentos',
        'total',
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

    public function payrollDetalles()
    {
        return $this->hasMany(PayrollDetalle::class, 'planilla_id');
    }

    /**
     * Recalcula y guarda el total real de la planilla:
     * sueldo_base + bonificaciones (manual + conceptos) - descuentos (manual + conceptos) - adelantos.
     * "aportacion" (ESSALUD, SCTR) NO se resta: lo paga el colegio, es solo informativo.
     * Se llama cada vez que la planilla o alguno de sus PayrollDetalle cambia,
     * para que el total nunca quede desincronizado de sus conceptos.
     */
    public function recalcularTotal(): float
    {
        $bonificacionesConcepto = (float) $this->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->where('tipo', 'bonificacion'))
            ->sum('monto_calculado');

        $descuentosConcepto = (float) $this->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->where('tipo', 'descuento'))
            ->sum('monto_calculado');

        $adelantosConcepto = (float) $this->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->where('tipo', 'adelanto'))
            ->sum('monto_calculado');

        $total = (float) $this->sueldo_base
            + (float) $this->bonificaciones + $bonificacionesConcepto
            - (float) $this->descuentos - $descuentosConcepto
            - $adelantosConcepto;

        $this->update(['total' => $total]);

        return $total;
    }
}