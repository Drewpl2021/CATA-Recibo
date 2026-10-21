<?php

namespace App\Support\Cts;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Traits\CalculaConceptosPlanilla;
use Carbon\Carbon;

/**
 * La Compensación por Tiempo de Servicios de un semestre (D.S. 001-97-TR).
 *
 * Dos depósitos al año, cada uno por un semestre:
 *
 *   mayo        noviembre del año anterior a abril   → hasta el 15 de mayo
 *   noviembre   mayo a octubre                       → hasta el 15 de noviembre
 *
 *   Remuneración computable = sueldo + asignación familiar + bonificación
 *                             por cargo (fija, todos los meses)
 *                             + 1/6 de la gratificación del semestre
 *   CTS = computable ÷ 12 × meses completos + computable ÷ 360 × días sueltos
 *
 * El sueldo, la asignación y la bonificación son los vigentes al cierre del
 * semestre: los de su planilla de abril u octubre (o del mes en que cesó);
 * la ficha solo si ese mes no pasó por el sistema.
 *
 * La gratificación del semestre es la de diciembre para el depósito de mayo
 * y la de julio para el de noviembre. Se toma la que de verdad se pagó en su
 * planilla; si no hay planilla de ese mes (se pagó antes del sistema), se
 * calcula con la misma regla con la que el sistema la paga. La bonificación
 * extraordinaria de la Ley 30334 no entra: no es remuneración computable.
 *
 * No genera CTS quien tiene un tipo de contrato marcado sin CTS (las
 * prácticas) ni quien no llega a un mes de servicio.
 */
class MotorCts
{
    use CalculaConceptosPlanilla;

    public const PERIODOS = ['mayo', 'noviembre'];

    /**
     * El semestre que cubre un depósito, y su fecha límite.
     *
     * @return array{desde: Carbon, hasta: Carbon, limite: Carbon, grati_mes: int, grati_anio: int}
     */
    public static function semestre(int $anio, string $periodo): array
    {
        if ($periodo === 'mayo') {
            return [
                'desde'      => Carbon::create($anio - 1, 11, 1)->startOfDay(),
                'hasta'      => Carbon::create($anio, 4, 30)->startOfDay(),
                'limite'     => Carbon::create($anio, 5, 15)->startOfDay(),
                'grati_mes'  => 12,
                'grati_anio' => $anio - 1,
            ];
        }

        return [
            'desde'      => Carbon::create($anio, 5, 1)->startOfDay(),
            'hasta'      => Carbon::create($anio, 10, 31)->startOfDay(),
            'limite'     => Carbon::create($anio, 11, 15)->startOfDay(),
            'grati_mes'  => 7,
            'grati_anio' => $anio,
        ];
    }

    /**
     * La CTS de un trabajador en un semestre, con todo el desglose.
     *
     * `aplica` en false trae el porqué en `motivo`, para que la pantalla lo
     * diga en vez de esconder a la persona.
     */
    public function calcular(Empleado $empleado, int $anio, string $periodo): array
    {
        $semestre = self::semestre($anio, $periodo);
        $base = [
            'aplica' => false, 'motivo' => null,
            'desde' => null, 'hasta' => null,
            'sueldo' => 0.0, 'asignacion_familiar' => 0.0, 'bonificacion_cargo' => 0.0, 'remuneracion_fuente' => null,
            'gratificacion' => 0.0, 'gratificacion_fuente' => null, 'sexto_gratificacion' => 0.0,
            'remuneracion_computable' => 0.0, 'meses' => 0, 'dias' => 0, 'monto' => 0.0,
        ];

        $tipo = $empleado->tipoContratoVigente();
        if ($tipo && ! $tipo->genera_cts) {
            return [...$base, 'motivo' => "Su tipo de contrato ({$tipo->nombre}) no genera CTS."];
        }

        $ingreso = Carbon::parse($empleado->fecha_ingreso)->startOfDay();
        $cese = $empleado->estado === 'inactivo' && $empleado->fecha_cese
            ? Carbon::parse($empleado->fecha_cese)->startOfDay() : null;

        $desde = $ingreso->gt($semestre['desde']) ? $ingreso->copy() : $semestre['desde']->copy();
        $hasta = $cese && $cese->lt($semestre['hasta']) ? $cese->copy() : $semestre['hasta']->copy();

        if ($desde->gt($hasta)) {
            return [...$base, 'motivo' => $ingreso->gt($semestre['hasta'])
                ? 'Ingresó después de este semestre.'
                : 'Cesó antes de este semestre.'];
        }

        // Se necesita al menos un mes de servicio en total, no solo en el semestre.
        if ($ingreso->copy()->addMonth()->gt($hasta->copy()->addDay())) {
            return [...$base, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
                'motivo' => 'No llega a un mes de servicio.'];
        }

        ['meses' => $meses, 'dias' => $dias] = self::tiempo($desde, $hasta);

        ['sueldo' => $sueldo, 'asignacion' => $asignacion, 'cargo' => $cargo, 'fuente' => $fuenteRemuneracion]
            = $this->remuneracionAlCierre($empleado, (int) $hasta->month, (int) $hasta->year);
        ['monto' => $grati, 'fuente' => $fuente] = $this->gratificacionDelSemestre($empleado, $semestre['grati_mes'], $semestre['grati_anio']);
        $sexto      = round($grati / 6, 2);
        $computable = round($sueldo + $asignacion + $cargo + $sexto, 2);
        $monto      = round($computable / 12 * $meses + $computable / 360 * $dias, 2);

        return [
            'aplica' => true, 'motivo' => null,
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'sueldo' => $sueldo, 'asignacion_familiar' => $asignacion, 'bonificacion_cargo' => $cargo,
            'remuneracion_fuente' => $fuenteRemuneracion,
            'gratificacion' => round($grati, 2), 'gratificacion_fuente' => $fuente, 'sexto_gratificacion' => $sexto,
            'remuneracion_computable' => $computable, 'meses' => $meses, 'dias' => $dias, 'monto' => $monto,
        ];
    }

    /**
     * Meses calendario completos y días sueltos entre dos fechas, ambas
     * incluidas. Un mes cuenta entero si se trabajó del primer al último día;
     * los pedazos del principio y del final van como días.
     *
     *   1 nov – 30 abr   → 6 meses, 0 días
     *   15 ene – 30 abr  → 3 meses (feb, mar, abr), 17 días (15 al 31 de enero)
     *   1 may – 20 set   → 4 meses (may a ago), 20 días
     *
     * @return array{meses: int, dias: int}
     */
    public static function tiempo(Carbon $desde, Carbon $hasta): array
    {
        $meses = 0;
        $dias  = 0;
        $cursor = $desde->copy();

        while ($cursor->lte($hasta)) {
            $finDeMes = $cursor->copy()->endOfMonth()->startOfDay();
            $tramoFin = $finDeMes->lt($hasta) ? $finDeMes : $hasta->copy();

            if ($cursor->day === 1 && $tramoFin->equalTo($finDeMes)) {
                $meses++;
            } else {
                $dias += (int) $cursor->diffInDays($tramoFin) + 1;
            }

            $cursor = $finDeMes->copy()->addDay();
        }

        // Treinta días sueltos hacen un mes, como en la liquidación a mano.
        $meses += intdiv($dias, 30);
        $dias  %= 30;

        return ['meses' => $meses, 'dias' => $dias];
    }

    /**
     * Lo que ganó mes por mes en el semestre, de sus planillas: para que
     * RR.HH. vea de dónde sale la CTS y no solo el resultado.
     *
     * Cada mes trae su situación (con planilla, sin planilla en el sistema o
     * no trabajaba), el sueldo, cada concepto con su tipo, los totales y dos
     * marcas: el mes de cierre (de donde sale la remuneración) y el de la
     * gratificación (de donde sale el sexto).
     */
    public function historial(Empleado $empleado, int $anio, string $periodo): array
    {
        $semestre = self::semestre($anio, $periodo);
        $calculo = $this->calcular($empleado, $anio, $periodo);
        $cierre = Carbon::parse($calculo['hasta'] ?? $semestre['hasta']);

        $planillas = Planilla::with('payrollDetalles.paymentConcept')
            ->where('empleado_id', $empleado->id)->where('estado_registro', 'activo')
            ->whereIn('anio', [$semestre['desde']->year, $semestre['hasta']->year])->get()
            ->keyBy(fn ($p) => $p->anio . '-' . $p->mes);

        $meses = [];
        for ($cursor = $semestre['desde']->copy(); $cursor->lte($semestre['hasta']); $cursor->addMonth()) {
            $mes = (int) $cursor->month;
            $anioMes = (int) $cursor->year;
            $planilla = $planillas->get($anioMes . '-' . $mes);
            $trabajo = $this->repartoDeDiasDelMes($empleado, $mes, $anioMes)['proporcion'] > 0;

            $conceptos = $planilla ? $planilla->payrollDetalles
                ->filter(fn ($d) => $d->paymentConcept && abs((float) $d->monto_calculado) >= 0.005)
                ->map(fn ($d) => [
                    'nombre' => $d->paymentConcept->nombre,
                    'tipo'   => $d->paymentConcept->tipo,
                    'monto'  => round((float) $d->monto_calculado, 2),
                ])->values() : collect();
            $suma = fn (string $tipo) => round($conceptos->where('tipo', $tipo)->sum('monto'), 2);
            $sueldo = $planilla ? round((float) $planilla->sueldo_base, 2) : 0.0;

            $meses[] = [
                'mes'              => $mes,
                'anio'             => $anioMes,
                'situacion'        => $planilla ? 'planilla' : ($trabajo ? 'sin_planilla' : 'no_trabajaba'),
                'sueldo_base'      => $sueldo,
                'conceptos'        => $conceptos,
                'ingresos'         => round($sueldo + $suma('bonificacion'), 2),
                'descuentos'       => round($suma('descuento') + $suma('adelanto'), 2),
                'aportes'          => $suma('aportacion'),
                'neto'             => $planilla ? round((float) $planilla->total, 2) : 0.0,
                'es_cierre'        => $mes === (int) $cierre->month && $anioMes === (int) $cierre->year,
                'es_gratificacion' => $mes === $semestre['grati_mes'] && $anioMes === $semestre['grati_anio'],
            ];
        }

        return [
            'anio'     => $anio,
            'periodo'  => $periodo,
            'empleado' => ['id' => $empleado->id, 'dni' => $empleado->dni, 'nombre' => trim("{$empleado->apellido} {$empleado->nombre}")],
            'calculo'  => $calculo,
            'meses'    => $meses,
        ];
    }

    /**
     * La remuneración vigente al cierre del semestre (art. 9 del D.S.
     * 001-97-TR): la de su planilla de abril u octubre, o la del mes en que
     * cesó. Si ese mes no tiene planilla en el sistema, la de su ficha.
     *
     * La planilla paga el sueldo y la bonificación por cargo por los días
     * del mes; para la CTS va el monto MENSUAL, así que en un mes incompleto
     * (ingresó o cesó a mitad) se devuelve a mes entero con la misma
     * proporción con la que se pagó. La asignación familiar no se prorratea.
     *
     * @return array{sueldo: float, asignacion: float, cargo: float, fuente: string}
     */
    private function remuneracionAlCierre(Empleado $empleado, int $mes, int $anio): array
    {
        $planilla = Planilla::with('payrollDetalles.paymentConcept')
            ->where('empleado_id', $empleado->id)->where('mes', $mes)->where('anio', $anio)
            ->where('estado_registro', 'activo')->first();
        $proporcion = (float) $this->repartoDeDiasDelMes($empleado, $mes, $anio)['proporcion'];

        if (! $planilla || $proporcion <= 0) {
            return [
                'sueldo'     => round((float) $empleado->sueldo_base, 2),
                'asignacion' => round($this->calcularAsignacionFamiliar($empleado, $anio), 2),
                'cargo'      => round((float) ($empleado->bonificacion_cargo ?? 0), 2),
                'fuente'     => 'ficha',
            ];
        }

        $linea = fn (string $concepto) => (float) $planilla->payrollDetalles
            ->filter(fn ($d) => $d->paymentConcept?->nombre === $concepto)
            ->sum('monto_calculado');

        return [
            'sueldo'     => round((float) $planilla->sueldo_base / $proporcion, 2),
            'asignacion' => round($linea(ConceptosDePago::ASIGNACION_FAMILIAR), 2),
            'cargo'      => round($linea(ConceptosDePago::BONIFICACION_CARGO) / $proporcion, 2),
            'fuente'     => 'planilla',
        ];
    }

    /**
     * La gratificación del semestre: la pagada en su planilla o, si ese mes
     * no pasó por el sistema, la que le correspondía con la regla de siempre
     * (sin la bonificación extraordinaria, que no es computable).
     *
     * @return array{monto: float, fuente: ?string}
     */
    private function gratificacionDelSemestre(Empleado $empleado, int $mes, int $anio): array
    {
        $planilla = Planilla::with('payrollDetalles.paymentConcept')
            ->where('empleado_id', $empleado->id)->where('mes', $mes)->where('anio', $anio)
            ->where('estado_registro', 'activo')->first();

        if ($planilla) {
            $pagada = (float) $planilla->payrollDetalles
                ->filter(fn ($d) => $d->paymentConcept?->nombre === ConceptosDePago::GRATIFICACION)
                ->sum('monto_calculado');

            return ['monto' => $pagada, 'fuente' => 'planilla'];
        }

        $calculada = $this->calcularGratificacion($empleado, $empleado->sueldo_base, $mes, $anio);
        if (! $calculada['aplica']) {
            return ['monto' => 0.0, 'fuente' => null];
        }

        return ['monto' => $calculada['monto_base'] + $calculada['asignacion_familiar'], 'fuente' => 'calculada'];
    }
}
