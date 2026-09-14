<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Planilla extends Model
{
    protected $table = 'planilla';
    protected $keyType = 'string';
    public $incrementing = false;

    /*
     * Ojo con esta lista: lo que no está aquí, Laravel lo TIRA sin avisar.
     *
     * 'periodo_id' faltaba, y como PeriodoController::generarPlanilla lo
     * pasaba en el create(), se descartaba en silencio: la generación decía
     * "13 generadas" y las 13 quedaban sin periodo. Se veía en la base
     * (0 de 13 con periodo_id) y en la pantalla, cuyo filtro por periodo no
     * devolvía nunca nada. Nadie lo notó porque no falla: solo no guarda.
     */
    protected $fillable = [
        'empleado_id',
        'mes',
        'anio',
        'periodo_id',
        'corrida_id',
        'sueldo_base',
        // 'bonificaciones' y 'descuentos' salieron de acá el 2026-09-13: lo
        // que suma o resta a un sueldo va por conceptos. Las columnas siguen
        // en la base con lo que tuvieran —ninguna planilla ya pagada cambia
        // de cifra—, pero ya nada las escribe ni las cuenta en el neto.
        'total',
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

    /** El año escolar del que cuelga, si se le puso. */
    public function periodo()
    {
        return $this->belongsTo(Periodo::class);
    }

    /** La corrida que la agrupa ("Planilla TIC"). Sin ella queda suelta. */
    public function corrida()
    {
        return $this->belongsTo(PlanillaCorrida::class, 'corrida_id');
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

        /*
         * El neto sale SOLO de los conceptos.
         *
         * Antes sumaba además las columnas `bonificaciones` y `descuentos` de
         * la propia planilla, que llenaba la pantalla de editar. Esa pantalla
         * ya no existe: lo que sube o baja un sueldo va por el catálogo, con
         * su etiqueta y su rastro. Las columnas siguen en la base con lo que
         * tuvieran —no se toca ninguna planilla ya pagada—, pero dejan de
         * entrar en la cuenta.
         */
        $total = (float) $this->sueldo_base
            + $bonificacionesConcepto
            - $descuentosConcepto
            - $adelantosConcepto;

        $this->update(['total' => $total]);

        return $total;
    }
}