<?php

namespace App\Support\Gratificacion;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Support\Cts\MotorCts;
use App\Traits\CalculaConceptosPlanilla;
use Carbon\Carbon;

/**
 * La gratificación de un semestre, para el módulo «Gratificaciones»
 * (Ley 27735, D.S. 005-2002-TR; bonificación de la Ley 30334).
 *
 *   julio       semestre de enero a junio      → se paga hasta el 15 de julio
 *   diciembre   semestre de julio a diciembre  → hasta el 15 de diciembre
 *
 * La gratificación se PAGA en la planilla de julio o diciembre (o, la
 * trunca, en la del mes del cese), así que sale en la boleta. Este motor no
 * paga nada: dice cuánto corresponde por ley y lo compara con lo que tiene
 * esa planilla. Las reglas son las mismas del motor de planilla
 * (CalculaConceptosPlanilla::calcularGratificacion y gratificacionTrunca),
 * con la remuneración vigente de esa planilla, como la CTS.
 */
class MotorGratificacion
{
    use CalculaConceptosPlanilla;

    public const PERIODOS = ['julio', 'diciembre'];

    /** Estados de una fila, de lo que más pide atención a lo que menos. */
    public const DIFERENCIA = 'diferencia';
    public const POR_PAGAR = 'por_pagar';
    public const PAGADA = 'pagada';
    public const NO_CORRESPONDE = 'no_corresponde';

    /**
     * El semestre de una gratificación, su mes de pago y su fecha límite.
     *
     * @return array{desde: Carbon, hasta: Carbon, limite: Carbon, mes_pago: int}
     */
    public static function semestre(int $anio, string $periodo): array
    {
        $julio = $periodo === 'julio';

        return [
            'desde'    => Carbon::create($anio, $julio ? 1 : 7, 1)->startOfDay(),
            'hasta'    => Carbon::create($anio, $julio ? 6 : 12, 1)->endOfMonth()->startOfDay(),
            'limite'   => Carbon::create($anio, $julio ? 7 : 12, 15)->startOfDay(),
            'mes_pago' => $julio ? 7 : 12,
        ];
    }

    /**
     * Lo que le corresponde a un trabajador y lo que tiene su planilla.
     *
     * Si cesó dentro del semestre, lo que corresponde es la trunca, en la
     * planilla del mes de su cese.
     */
    public function calcular(Empleado $empleado, int $anio, string $periodo): array
    {
        $semestre = self::semestre($anio, $periodo);
        $mesPlanilla = $semestre['mes_pago'];
        $anioPlanilla = $anio;

        $regla = $this->calcularGratificacion($empleado, (float) $empleado->sueldo_base, $mesPlanilla, $anio);
        $trunca = false;
        if (! $regla['aplica'] && $empleado->estado === 'inactivo' && $empleado->fecha_cese) {
            $cese = Carbon::parse($empleado->fecha_cese)->startOfDay();
            if ($cese->betweenIncluded($semestre['desde'], $semestre['hasta'])) {
                $truncaRegla = $this->gratificacionTrunca($empleado, (float) $empleado->sueldo_base, (int) $cese->month, (int) $cese->year);
                if ($truncaRegla['aplica']) {
                    [$regla, $trunca, $mesPlanilla, $anioPlanilla] = [$truncaRegla, true, (int) $cese->month, (int) $cese->year];
                }
            }
        }

        $planilla = Planilla::with('payrollDetalles.paymentConcept', 'corrida')
            ->where('empleado_id', $empleado->id)->where('mes', $mesPlanilla)->where('anio', $anioPlanilla)
            ->where('estado_registro', 'activo')->first();
        $linea = fn (string $concepto) => $planilla ? round((float) $planilla->payrollDetalles
            ->filter(fn ($d) => $d->paymentConcept?->nombre === $concepto)->sum('monto_calculado'), 2) : 0.0;
        $pagadoGrati = $linea(ConceptosDePago::GRATIFICACION);
        $pagadoBono  = $linea(ConceptosDePago::BONIF_EXTRAORDINARIA);

        $base = [
            'aplica' => $regla['aplica'], 'motivo' => $regla['motivo'], 'trunca' => $trunca,
            'meses' => $regla['meses_trabajados'],
            'mes_planilla' => $mesPlanilla, 'anio_planilla' => $anioPlanilla,
            'planilla_id' => $planilla?->id, 'planilla_del_plame' => (bool) $planilla?->corrida?->vieneDelPlame(),
            'pagado_gratificacion' => $pagadoGrati, 'pagado_bonificacion' => $pagadoBono,
            'pagado_total' => round($pagadoGrati + $pagadoBono, 2),
        ];

        if (! $regla['aplica']) {
            return $base + [
                'sueldo' => 0.0, 'bonificacion_cargo' => 0.0, 'asignacion_familiar' => 0.0, 'remuneracion_fuente' => null,
                'remuneracion_computable' => 0.0, 'gratificacion' => 0.0, 'bonificacion_extraordinaria' => 0.0,
                'total' => 0.0, 'diferencia' => round($pagadoGrati + $pagadoBono, 2),
                // Se le pagó algo que no le correspondía: también es una diferencia.
                'estado' => $pagadoGrati + $pagadoBono > 0.005 ? self::DIFERENCIA : self::NO_CORRESPONDE,
            ];
        }

        // La remuneración vigente en el mes en que se paga (como la CTS): la
        // de esa planilla devuelta a mes entero, o la de la ficha.
        $rem = (new MotorCts())->remuneracionAlCierre($empleado, $mesPlanilla, $anioPlanilla);
        $computable = round($rem['sueldo'] + $rem['cargo'] + $rem['asignacion'], 2);
        $gratificacion = round(round($rem['sueldo'] * $regla['meses_trabajados'] / 6, 2)
            + round($rem['cargo'] * $regla['meses_trabajados'] / 6, 2)
            + round($rem['asignacion'] * $regla['meses_trabajados'] / 6, 2), 2);
        $bonificacion = round($gratificacion * ($this->valoresLegales($anioPlanilla)->essalud / 100), 2);
        $total = round($gratificacion + $bonificacion, 2);

        $estado = match (true) {
            ! $planilla                                        => self::POR_PAGAR,
            abs($total - $base['pagado_total']) <= 0.01        => self::PAGADA,
            default                                            => self::DIFERENCIA,
        };

        return $base + [
            'sueldo' => $rem['sueldo'], 'bonificacion_cargo' => $rem['cargo'], 'asignacion_familiar' => $rem['asignacion'],
            'remuneracion_fuente' => $rem['fuente'], 'remuneracion_computable' => $computable,
            'gratificacion' => $gratificacion, 'bonificacion_extraordinaria' => $bonificacion, 'total' => $total,
            'diferencia' => round($base['pagado_total'] - $total, 2),
            'estado' => $estado,
        ];
    }

    /** El semestre mes a mes, de sus planillas, con el mes en que se paga marcado. */
    public function historial(Empleado $empleado, int $anio, string $periodo): array
    {
        $semestre = self::semestre($anio, $periodo);
        $calculo = $this->calcular($empleado, $anio, $periodo);
        // Hasta el mes del pago: julio o diciembre (o el del cese, si es trunca).
        $hasta = Carbon::create($calculo['anio_planilla'], $calculo['mes_planilla'], 1)->endOfMonth();
        $hasta = $hasta->lt($semestre['hasta']) ? $semestre['hasta'] : $hasta;

        $meses = array_map(fn (array $m) => $m + [
            'es_pago' => $m['mes'] === $calculo['mes_planilla'] && $m['anio'] === $calculo['anio_planilla'],
        ], (new MotorCts())->mesAMes($empleado, $semestre['desde'], $hasta));

        return [
            'anio' => $anio, 'periodo' => $periodo,
            'empleado' => ['id' => $empleado->id, 'dni' => $empleado->dni, 'nombre' => trim("{$empleado->apellido} {$empleado->nombre}")],
            'calculo' => $calculo,
            'meses' => $meses,
        ];
    }
}
