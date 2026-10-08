<?php

namespace App\Support;

use App\Http\Controllers\BoletaController;
use App\Models\Planilla;

/**
 * Que la boleta emitida diga lo mismo que su planilla.
 *
 * Si a una planilla con la boleta ya emitida (y sin firmar) se le agrega,
 * cambia o quita un concepto, el PDF que ya tiene el trabajador quedaba con
 * los montos de antes. Esto lo vuelve a generar con lo que la planilla tiene
 * ahora, en el mismo documento: no es una emisión nueva y no se le vuelve a
 * avisar.
 *
 * Con la boleta firmada no se llega aquí: Planilla::motivoParaNoTocar() ya
 * lo impide antes.
 */
final class BoletaAlDia
{
    /** @return bool true si había boleta emitida y se rehízo. */
    public static function rehacer(Planilla $planilla): bool
    {
        $planilla = $planilla->fresh(['documentoBoleta', 'empleado']);

        if (! $planilla?->documentoBoleta || ! $planilla->empleado || $planilla->boletaFirmada()) {
            return false;
        }

        app(BoletaController::class)->construirBoleta(
            $planilla->empleado->load('area', 'cargo', 'sede'),
            $planilla,
            (int) $planilla->mes,
            (int) $planilla->anio
        );

        return true;
    }
}
