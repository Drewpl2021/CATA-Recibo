<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lo cobrado y retenido de 5ta en un mes que no pasó por el sistema.
 * Ver la migración de renta_quinta_previa.
 */
class RentaQuintaPrevia extends Model
{
    protected $table = 'renta_quinta_previa';

    protected $fillable = ['empleado_id', 'anio', 'mes', 'remuneracion', 'retencion', 'origen'];

    protected $casts = [
        'anio'         => 'integer',
        'mes'          => 'integer',
        'remuneracion' => 'float',
        'retencion'    => 'float',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }
}
