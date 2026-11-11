<?php

namespace App\Support\Solicitudes;

use App\Models\Solicitud;
use Carbon\Carbon;

/**
 * Los días que las solicitudes APROBADAS le quitan a la planilla y a la CTS.
 * Una sola cuenta para las dos, para que nunca digan cosas distintas.
 *
 *   Planilla  la licencia sin goce no se paga: sus días hábiles salen de los
 *             días pagados del mes, igual que se cuentan los del sueldo
 *             (CalculaConceptosPlanilla::repartoDeDiasDelMes).
 *
 *   CTS       solo cuenta el tiempo trabajado (D.S. 001-97-TR, arts. 7 y 8):
 *               · la licencia sin goce no cuenta: resta sus días;
 *               · la licencia con goce cuenta como trabajada: no resta;
 *               · el descanso médico cuenta hasta 60 días en el año; lo que
 *                 pase de 60 resta;
 *               · la falta justificada no resta (la injustificada la anota
 *                 RR.HH. aparte, en CtsInasistencia).
 *
 * Lo pendiente o rechazado no cuenta para nada: solo lo aprobado.
 */
final class DiasDeLicencia
{
    /** Tope de descanso médico que la CTS toma como trabajado, por año calendario. */
    public const TOPE_DESCANSO_MEDICO = 60;

    /** Días hábiles (lunes a viernes) de licencia sin goce entre dos fechas. */
    public static function habilesSinGoce(string $empleadoId, Carbon $desde, Carbon $hasta): int
    {
        return self::contarSinGoce($empleadoId, $desde, $hasta, soloHabiles: true);
    }

    /** Días de calendario de licencia sin goce entre dos fechas: los de la boleta y la CTS. */
    public static function calendarioSinGoce(string $empleadoId, Carbon $desde, Carbon $hasta): int
    {
        return self::contarSinGoce($empleadoId, $desde, $hasta, soloHabiles: false);
    }

    /**
     * Días de descanso médico que pasan del tope del año y caen entre dos
     * fechas. Se cuentan en orden: los primeros 60 del año valen como
     * trabajados, del 61 en adelante restan.
     */
    public static function excesoDescansoMedico(string $empleadoId, Carbon $desde, Carbon $hasta): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();
        $exceso = 0;

        for ($anio = $desde->year; $anio <= $hasta->year; $anio++) {
            $inicioAnio = Carbon::create($anio, 1, 1)->startOfDay();
            $finAnio    = Carbon::create($anio, 12, 31)->startOfDay();

            $tramos = Solicitud::where('empleado_id', $empleadoId)
                ->where('tipo', Solicitud::DESCANSO_MEDICO)->where('estado', 'aprobada')
                ->where('fecha_inicio', '<=', $finAnio->toDateString())
                ->where('fecha_fin', '>=', $inicioAnio->toDateString())
                ->orderBy('fecha_inicio')->get(['fecha_inicio', 'fecha_fin']);

            // Días únicos del año, en orden: dos certificados que se pisan no
            // cuentan el mismo día dos veces.
            $dias = [];
            foreach ($tramos as $t) {
                $cursor = $t->fecha_inicio->copy()->startOfDay()->max($inicioAnio);
                $fin    = $t->fecha_fin->copy()->startOfDay()->min($finAnio);
                for (; $cursor->lte($fin); $cursor->addDay()) {
                    $dias[$cursor->toDateString()] = true;
                }
            }
            ksort($dias);

            $n = 0;
            foreach (array_keys($dias) as $dia) {
                $n++;
                if ($n > self::TOPE_DESCANSO_MEDICO && $dia >= $desde->toDateString() && $dia <= $hasta->toDateString()) {
                    $exceso++;
                }
            }
        }

        return $exceso;
    }

    /** Los días que la CTS no toma como trabajados entre dos fechas, por las solicitudes. */
    public static function paraCts(string $empleadoId, Carbon $desde, Carbon $hasta): int
    {
        return self::calendarioSinGoce($empleadoId, $desde, $hasta)
            + self::excesoDescansoMedico($empleadoId, $desde, $hasta);
    }

    /** Días de descanso médico aprobados en el año (para el aviso del subsidio desde el día 21). */
    public static function descansoMedicoDelAnio(string $empleadoId, int $anio, ?string $exceptoId = null): int
    {
        $inicio = Carbon::create($anio, 1, 1)->startOfDay();
        $fin    = Carbon::create($anio, 12, 31)->startOfDay();
        $dias = [];
        Solicitud::where('empleado_id', $empleadoId)->where('tipo', Solicitud::DESCANSO_MEDICO)->where('estado', 'aprobada')
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->where('fecha_inicio', '<=', $fin->toDateString())->where('fecha_fin', '>=', $inicio->toDateString())
            ->get(['fecha_inicio', 'fecha_fin'])
            ->each(function ($t) use (&$dias, $inicio, $fin) {
                $c = $t->fecha_inicio->copy()->startOfDay()->max($inicio);
                $f = $t->fecha_fin->copy()->startOfDay()->min($fin);
                for (; $c->lte($f); $c->addDay()) {
                    $dias[$c->toDateString()] = true;
                }
            });

        return count($dias);
    }

    private static function contarSinGoce(string $empleadoId, Carbon $desde, Carbon $hasta, bool $soloHabiles): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();
        if ($desde->gt($hasta)) {
            return 0;
        }

        $dias = [];
        Solicitud::query()->sinGoceAprobadas($empleadoId)
            ->where('fecha_inicio', '<=', $hasta->toDateString())
            ->where('fecha_fin', '>=', $desde->toDateString())
            ->get(['fecha_inicio', 'fecha_fin'])
            ->each(function ($t) use (&$dias, $desde, $hasta, $soloHabiles) {
                $c = $t->fecha_inicio->copy()->startOfDay()->max($desde);
                $f = $t->fecha_fin->copy()->startOfDay()->min($hasta);
                for (; $c->lte($f); $c->addDay()) {
                    if (! $soloHabiles || $c->isWeekday()) {
                        $dias[$c->toDateString()] = true;
                    }
                }
            });

        return count($dias);
    }
}
