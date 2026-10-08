<?php

namespace App\Support\Renta5ta;

use App\Http\Controllers\BoletaController;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Traits\CalculaConceptosPlanilla;

/**
 * Vuelve a calcular la 5ta de las planillas de un año, mes por mes.
 *
 * Va en orden porque cada mes resta lo retenido en los anteriores: si cambia
 * el historial de marzo, cambian abril, mayo... Solo toca las planillas que
 * todavía se pueden tocar: no las de una planilla cerrada (ya se pagó) ni
 * las de una boleta firmada. Si la boleta ya estaba emitida y sin firmar,
 * se vuelve a generar su PDF con el monto nuevo.
 */
class RecalculoRenta5ta
{
    use CalculaConceptosPlanilla;

    /** @return array{recalculadas:int, saltadas:int} */
    public function empleado(Empleado $empleado, int $anio, ?int $desdeMes = 1): array
    {
        $recalculadas = 0;
        $saltadas = 0;

        $planillas = Planilla::with('corrida', 'documentoBoleta')
            ->where('empleado_id', $empleado->id)->where('anio', $anio)
            ->where('mes', '>=', $desdeMes ?? 1)
            ->where('estado_registro', 'activo')
            ->orderBy('mes')->get();

        foreach ($planillas as $planilla) {
            $firmada = $planilla->documentoBoleta && in_array($planilla->documentoBoleta->estado_firma, Documento::FIRMA_RESUELTA, true);
            if ($planilla->corrida?->estaCerrada() || $firmada) {
                $saltadas++;
                continue;
            }

            $this->generarYPersistirRenta5ta($planilla, $empleado);
            $recalculadas++;

            if ($planilla->documentoBoleta) {
                app(BoletaController::class)->construirBoleta(
                    $empleado->load('area', 'cargo', 'sede'),
                    $planilla->fresh(),
                    (int) $planilla->mes,
                    (int) $planilla->anio
                );
            }
        }

        return ['recalculadas' => $recalculadas, 'saltadas' => $saltadas];
    }
}
