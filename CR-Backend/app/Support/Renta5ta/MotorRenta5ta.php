<?php

namespace App\Support\Renta5ta;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Support\ConceptosDePago;
use App\Traits\CalculaConceptosPlanilla;

/**
 * La retención de Renta de 5ta Categoría con el procedimiento de SUNAT
 * (Art. 40 del Reglamento de la Ley del Impuesto a la Renta).
 *
 * Es lo que hacía la hoja «RETENCION» de la PLAME del colegio —que se quedó
 * en 2025 con 8 trabajadores—, ahora para todos y con los datos reales:
 *
 *   Renta bruta del año = lo cobrado de verdad en los meses anteriores
 *                       + lo del mes × los meses que faltan (incluido el mes)
 *                       + las gratificaciones que faltan, con su 9%
 *   − 7 UIT  →  tramos 8 / 14 / 17 / 20 / 30 %  =  impuesto del año
 *
 *   Retención del mes = (impuesto − lo ya retenido) ÷ divisor
 *     enero-marzo ÷ 12 · abril ÷ 9 · mayo-julio ÷ 8 · agosto ÷ 5
 *     setiembre-noviembre ÷ 4 · diciembre: todo lo que falta (regulariza)
 *     "Lo ya retenido" es el de enero-marzo para abril, enero-abril para
 *     mayo a julio, enero-julio para agosto, enero-agosto para setiembre a
 *     noviembre y enero-noviembre para diciembre.
 *
 *   + Retención adicional del mes: si cobra algo extraordinario (un bono,
 *     un reintegro), el impuesto que ese monto suma se retiene entero ese
 *     mes, sin dividir.
 *
 * Los datos de cada mes salen, por orden: de su planilla en el sistema, del
 * historial cargado (los meses que se pagaron antes del sistema) o, si no
 * hay nada, se proyectan con su ficha.
 */
class MotorRenta5ta
{
    use CalculaConceptosPlanilla {
        calcularGratificacion as private gratificacionSinRecordar;
    }

    public const DIVISOR = [1 => 12, 2 => 12, 3 => 12, 4 => 9, 5 => 8, 6 => 8, 7 => 8, 8 => 5, 9 => 4, 10 => 4, 11 => 4, 12 => 1];

    /** Hasta qué mes se resta lo ya retenido (0 = nada). */
    public const CORTE = [1 => 0, 2 => 0, 3 => 0, 4 => 3, 5 => 4, 6 => 4, 7 => 4, 8 => 7, 9 => 8, 10 => 8, 11 => 8, 12 => 11];

    /** Lo que se paga todos los meses: entra en la proyección. */
    private const ORDINARIOS = [ConceptosDePago::ASIGNACION_FAMILIAR, ConceptosDePago::BONIFICACION_CARGO];

    /** Las gratificaciones de julio y diciembre: se proyectan aparte. */
    private const GRATIFICACIONES = [ConceptosDePago::GRATIFICACION, ConceptosDePago::BONIF_EXTRAORDINARIA];

    /** No es remuneración (condición de trabajo): no paga 5ta. */
    // La CTS está inafecta (art. 18 de la Ley del IR). Con el PLAME de mayo
    // entraba como ingreso y subía el impuesto del año de todos.
    private const NO_AFECTOS = [ConceptosDePago::MOVILIDAD, ConceptosDePago::CTS];

    /**
     * Lo ya calculado de cada trabajador y mes (sueldo del mes, proporción,
     * estimados). La hoja del año pide lo mismo muchas veces, y la lista de
     * todo el personal la arma para cada uno: sin esto tardaba segundos.
     */
    private array $memoria = [];

    private function recordar(string $clave, callable $calculo): mixed
    {
        return array_key_exists($clave, $this->memoria) ? $this->memoria[$clave] : ($this->memoria[$clave] = $calculo());
    }

    /** La hoja del año pide las mismas dos gratificaciones cada mes. */
    protected function calcularGratificacion($empleado, $sueldoBase, $mes, $anio): array
    {
        return $empleado->exists
            ? $this->recordar("grati-{$empleado->id}-{$anio}-{$mes}-{$sueldoBase}", fn () => $this->gratificacionSinRecordar($empleado, $sueldoBase, $mes, $anio))
            : $this->gratificacionSinRecordar($empleado, $sueldoBase, $mes, $anio);
    }

    /**
     * Si ese mes ya está cargado en el sistema (su PLAME o su planilla). Quien
     * no tiene planilla en un mes cargado no trabajó ese mes: el PLAME de
     * febrero de 2026 trae a 24 personas, y a los demás no se les puede
     * estimar un sueldo de febrero que nunca cobraron (la hoja de RR.HH.
     * también los pone en cero). Un mes que todavía no se cargó sí se estima.
     */
    private function mesCargado(int $mes, int $anio): bool
    {
        return $this->recordar("cargado-{$anio}-{$mes}", fn () => Planilla::where('anio', $anio)->where('mes', $mes)->exists());
    }

    /**
     * Lo que de verdad pasó cada mes del año: de su planilla o del historial.
     *
     * @return array<int, array{fuente:string, remuneracion:float, ordinaria:float, gratificacion:float, extraordinaria:float, retencion:float, sueldo:?float}>
     */
    public function mesesReales(Empleado $empleado, int $anio): array
    {
        $reales = [];

        $planillas = Planilla::with('payrollDetalles.paymentConcept')
            ->where('empleado_id', $empleado->id)->where('anio', $anio)
            ->where('estado_registro', 'activo')->get();

        foreach ($planillas as $p) {
            $lineas = $p->payrollDetalles->filter(fn ($d) => $d->paymentConcept);
            $suma = fn (callable $filtro) => (float) $lineas->filter($filtro)->sum('monto_calculado');
            $esIngreso = fn ($d) => $d->paymentConcept->tipo === 'bonificacion' && ! in_array($d->paymentConcept->nombre, self::NO_AFECTOS, true);

            $ordinaria      = (float) $p->sueldo_base + $suma(fn ($d) => in_array($d->paymentConcept->nombre, self::ORDINARIOS, true));
            $gratificacion  = $suma(fn ($d) => in_array($d->paymentConcept->nombre, self::GRATIFICACIONES, true));
            $remuneracion   = (float) $p->sueldo_base + $suma($esIngreso);

            $reales[(int) $p->mes] = [
                'fuente'         => 'planilla',
                'remuneracion'   => $remuneracion,
                'ordinaria'      => $ordinaria,
                'gratificacion'  => $gratificacion,
                'extraordinaria' => $remuneracion - $ordinaria - $gratificacion,
                'retencion'      => $suma(fn ($d) => $d->paymentConcept->nombre === ConceptosDePago::RENTA_5TA),
                'sueldo'         => (float) $p->sueldo_base,
                // Si cobró vacaciones truncas ese mes (la liquidación de un contrato anterior).
                'truncas'        => $suma(fn ($d) => $d->paymentConcept->nombre === ConceptosDePago::VACACIONES_TRUNCAS),
            ];
        }

        // Los meses pagados antes del sistema: lo que dice el historial.
        foreach (RentaQuintaPrevia::where('empleado_id', $empleado->id)->where('anio', $anio)->get() as $h) {
            if (isset($reales[$h->mes])) {
                continue;   // si el mes tiene planilla, manda la planilla
            }
            $reales[$h->mes] = [
                'fuente'         => 'historial',
                'remuneracion'   => (float) $h->remuneracion,
                'ordinaria'      => (float) $h->remuneracion,
                'gratificacion'  => 0.0,
                'extraordinaria' => 0.0,
                'retencion'      => (float) $h->retencion,
                'sueldo'         => null,
            ];
        }

        ksort($reales);

        return $reales;
    }

    /**
     * La retención de un mes, con todo el cálculo a la vista.
     *
     * @param float|null $sueldoMes    el sueldo de ESE mes si se está escribiendo (vista previa)
     * @param float|null $bonifCargo   la Bonificación por Cargo que se está escribiendo
     * @param array|null $reales       mesesReales() ya leído (para no repetir consultas)
     * @param array      $simuladas    retenciones de meses sin dato real (al armar la hoja del año)
     */
    public function retencionDelMes(Empleado $empleado, int $mes, int $anio, ?float $sueldoMes = null, ?float $bonifCargo = null, ?array $reales = null, array $simuladas = [], ?int $referencia = null): array
    {
        $reales ??= $this->mesesReales($empleado, $anio);
        $ley = $this->valoresLegales($anio);
        // Los meses que trabaja se miran desde el mes que se está calculando:
        // al estimar un mes pasado sin dato (ver abajo), una fecha de fin que
        // ya venció no lo corta si sigue activo (le pasaba a Limachi).
        [$desde, $hasta] = $this->mesesTrabajadosDelAnio($empleado, $anio, $referencia ?? $mes);

        $fila = [
            'mes' => $mes, 'fuente' => $reales[$mes]['fuente'] ?? 'proyectado', 'trabaja' => true,
            'remuneracion_mes' => 0.0, 'remuneracion_mensual' => 0.0, 'meses_que_faltan' => 0, 'remuneracion_proyectada' => 0.0,
            'gratificaciones' => 0.0, 'remuneraciones_anteriores' => 0.0, 'meses_sin_dato' => [],
            'renta_bruta' => 0.0, 'deduccion' => round($ley->uit * 7, 2), 'renta_neta' => 0.0, 'impuesto_anual' => 0.0,
            'retenido_antes' => 0.0, 'divisor' => self::DIVISOR[$mes], 'retencion_ordinaria' => 0.0,
            'extraordinario' => 0.0, 'retencion_adicional' => 0.0, 'retencion' => 0.0,
            'retencion_real' => isset($reales[$mes]) ? round($reales[$mes]['retencion'], 2) : null,
        ];

        // Fuera de su contrato, o un mes ya pasado y cargado en el que no tiene
        // planilla (no trabajó: ver mesCargado). Solo meses ya cerrados: el
        // mes en curso puede estar a medio generar.
        $mesCerrado = $anio < (int) now()->year || ($anio === (int) now()->year && $mes < (int) now()->month);
        if ($mes < $desde || $mes > $hasta || (! isset($reales[$mes]) && $mesCerrado && $this->mesCargado($mes, $anio))) {
            $fila['trabaja'] = false;
            $fila['fuente'] = 'no_trabaja';

            return $fila;
        }

        $real = $reales[$mes] ?? null;

        // Lo que cobra un mes completo: con esto se proyectan los meses que
        // faltan y las gratificaciones. Es el sueldo de este mes (el de su
        // planilla, o el que se está escribiendo); si este mes lo trabajó solo
        // en parte (entró a mitad), el de su ficha.
        $asignacion = $this->calcularAsignacionFamiliar($empleado, $anio);
        $sueldoDeEsteMes = $sueldoMes ?? ($real['sueldo'] ?? null);
        $mesCompleto = ! $empleado->exists
            || $this->recordar("prop-{$empleado->id}-{$anio}-{$mes}", fn () => $this->proporcionDelMes($empleado, $mes, $anio)) >= 1;
        $sueldoMensual = ($sueldoDeEsteMes !== null && $mesCompleto) ? $sueldoDeEsteMes : (float) $empleado->sueldo_base;
        $mensual = $sueldoMensual + $asignacion + (float) ($bonifCargo ?? $empleado->bonificacion_cargo ?? 0);

        // Lo ordinario de ESTE mes (puede ser solo una parte si entró a mitad).
        // En la vista previa de la boleta, el sueldo y la bonificación que se
        // están escribiendo reemplazan a los de la planilla.
        if ($real && $real['fuente'] === 'historial') {
            $delMes = $real['remuneracion'];
        } elseif ($real) {
            $delMes = $real['ordinaria'];
            if ($sueldoMes !== null) {
                $delMes += $sueldoMes - $real['sueldo'];
            }
            if ($bonifCargo !== null) {
                $delMes += $bonifCargo - $this->lineaDe($empleado, $mes, $anio, ConceptosDePago::BONIFICACION_CARGO);
            }
        } else {
            $delMes = ($sueldoMes ?? $this->sueldoFicha($empleado, $mes, $anio))
                + $asignacion
                + ($bonifCargo ?? $this->bonifFicha($empleado, $mes, $anio));
        }

        $mesesQueFaltan = $hasta - $mes + 1;
        $proyectada = $delMes + $mensual * ($mesesQueFaltan - 1);

        // Las gratificaciones que todavía no cobró: julio (si estamos hasta
        // julio) y diciembre, con la bonificación del 9% (la tasa de EsSalud).
        // La del mes en curso, si su planilla ya la tiene, va con su monto real.
        $gratificaciones = 0.0;
        foreach ([7, 12] as $g) {
            if ($g < $mes || $g > $hasta) {
                continue;
            }
            if ($g === $mes && $real && $real['gratificacion'] > 0) {
                $gratificaciones += $real['gratificacion'];
                continue;
            }
            $semestre = $this->calcularGratificacion($empleado, $mensual, $g, $anio);
            $gratificaciones += $mensual / 6 * $semestre['meses_trabajados'] * (1 + $ley->essalud / 100);
        }

        // Lo cobrado de verdad en los meses anteriores (los que trabajó). Un
        // mes sin planilla ni historial se estima con su ficha —contarlo como
        // cero bajaría la renta del año— y se avisa que falta ese dato.
        $anteriores = 0.0;
        $sinDato = [];
        // Un mes con dato real cuenta aunque sea de antes de su ingreso: quien
        // reingresó en marzo cobró enero con su contrato anterior, y para la
        // 5ta va todo lo del año con el mismo empleador. Antes se saltaba ese
        // sueldo pero sí se restaba su retención, y parecía retenido de más.
        for ($k = 1; $k < $mes; $k++) {
            if (isset($reales[$k])) {
                $anteriores += $reales[$k]['remuneracion'];
            } elseif ($k < $desde || $this->mesCargado($k, $anio)) {
                continue;
            } else {
                $sinDato[] = $k;
                $anteriores += $this->recordar("est-{$empleado->id}-{$anio}-{$k}", fn () => $this->estimadoDelMes($empleado, $k, $anio, $asignacion));
            }
        }

        // Y lo retenido en esos meses sin dato: lo que el método habría retenido.
        for ($k = 1; $k < $mes; $k++) {
            if (! isset($reales[$k]) && ! array_key_exists($k, $simuladas) && $this->mesCargado($k, $anio)) {
                $simuladas[$k] = 0.0;
            }
            if (! isset($reales[$k]) && ! array_key_exists($k, $simuladas)) {
                $simuladas[$k] = $this->retencionDelMes($empleado, $k, $anio, null, null, $reales, $simuladas, $referencia ?? $mes)['retencion'];
            }
        }

        // Las vacaciones truncas que cobrará al terminar su contrato, si es de
        // los que no toman vacaciones (contratados) y su contrato termina este
        // año. La planilla no las pone sola (RR.HH. agrega la línea en el mes
        // del cese), y sin proyectarlas a varios contratados les salía la renta
        // justo por debajo de las 7 UIT. Misma fórmula que la hoja de RR.HH.:
        // lo mensual ÷ 12 × los meses del año que trabaja. Cuando la planilla de
        // ese mes ya existe, manda lo que de verdad se pagó.
        $truncas = 0.0;
        $ceseEsteAnio = $empleado->fecha_cese && (int) substr((string) $empleado->fecha_cese, 0, 4) === $anio;
        if ($ceseEsteAnio && $hasta >= $mes && ! isset($reales[$hasta]) && ! $empleado->puedeTomarVacaciones()) {
            // Desde el mes siguiente a sus últimas truncas cobradas: el contratado
            // que se liquidó en enero y volvió en marzo cobra en diciembre solo
            // las de marzo a diciembre, no las del año entero.
            // De los meses ya pasados cuentan los que tienen planilla o historial,
            // y los que todavía no se cargaron (ver mesCargado); los que vienen, todos.
            $ultimasTruncas = max([0, ...array_keys(array_filter($reales, fn ($r) => ($r['truncas'] ?? 0) > 0))]);
            $meses = count(array_filter(range(max($desde, $ultimasTruncas + 1), $hasta),
                fn ($k) => $k >= $mes || isset($reales[$k]) || ! $this->mesCargado($k, $anio)));
            $truncas = $mensual / 12 * $meses;
        }

        $rentaBruta = $proyectada + $gratificaciones + $anteriores + $truncas;
        $exento = $ley->uit * 7;
        $impuesto = $this->aplicarTramosImpuestoRenta(max(0, $rentaBruta - $exento), $ley->uit);

        // Lo ya retenido hasta el mes de corte: lo real, o lo calculado si no hay dato.
        $retenido = 0.0;
        for ($k = 1; $k <= self::CORTE[$mes]; $k++) {
            $retenido += isset($reales[$k]) ? $reales[$k]['retencion'] : ($simuladas[$k] ?? 0.0);
        }

        $base = $impuesto - $retenido;
        $ordinaria = max(0, $mes === 12 ? $base : $base / self::DIVISOR[$mes]);

        // Lo extraordinario del mes se retiene entero en ese mes.
        $extra = $real['extraordinaria'] ?? 0.0;
        $adicional = $extra > 0
            ? $this->aplicarTramosImpuestoRenta(max(0, $rentaBruta + $extra - $exento), $ley->uit) - $impuesto
            : 0.0;

        return array_merge($fila, [
            'remuneracion_mes'          => round($delMes, 2),
            'remuneracion_mensual'      => round($mensual, 2),
            'meses_que_faltan'          => $mesesQueFaltan,
            'remuneracion_proyectada'   => round($proyectada, 2),
            'gratificaciones'           => round($gratificaciones, 2),
            'vacaciones_truncas'        => round($truncas, 2),
            'remuneraciones_anteriores' => round($anteriores, 2),
            'meses_sin_dato'            => $sinDato,
            'renta_bruta'               => round($rentaBruta, 2),
            'renta_neta'                => round(max(0, $rentaBruta - $exento), 2),
            'impuesto_anual'            => round($impuesto, 2),
            'retenido_antes'            => round($retenido, 2),
            'retencion_ordinaria'       => round($ordinaria, 2),
            'extraordinario'            => round($extra, 2),
            'retencion_adicional'       => round($adicional, 2),
            'retencion'                 => round($ordinaria + $adicional, 2),
        ]);
    }

    /**
     * La hoja del año, mes por mes, como la hoja «RETENCION» de la PLAME.
     * En los meses sin dato real, lo retenido es lo que el cálculo da.
     * Con el método de RR.HH. activo (Ajustes), la hoja dice lo que dice la
     * planilla: si no, cada mes parecería mal retenido.
     */
    public function hoja(Empleado $empleado, int $anio): array
    {
        $reales = $this->mesesReales($empleado, $anio);
        $comoRrhh = MetodoRenta5ta::comoHojaDeRrhh();
        $simuladas = [];
        $retenidas = [];
        $meses = [];

        for ($m = 1; $m <= 12; $m++) {
            $fila = $this->retencionDelMes($empleado, $m, $anio, null, null, $reales, $simuladas);
            if ($comoRrhh && $fila['trabaja']) {
                $fila = $this->filaComoRrhh($fila, $empleado, $m, $anio, $reales[$m]['sueldo'] ?? (float) $empleado->sueldo_base, $retenidas);
                $retenidas[$m] = $fila['retencion_real'] ?? $fila['retencion'];
            }
            $simuladas[$m] = $fila['retencion_real'] ?? $fila['retencion'];
            $meses[$m] = $fila;
        }

        $retenidoReal = array_sum(array_map(fn ($r) => $r['retencion'], $reales));
        $impuestoAnual = $meses[12]['trabaja'] ? $meses[12]['impuesto_anual']
            : max(array_map(fn ($f) => $f['impuesto_anual'], $meses));

        return [
            'anio'          => $anio,
            'metodo'        => $comoRrhh ? 'rrhh' : 'sunat',
            'uit'           => (float) $this->valoresLegales($anio)->uit,
            'meses'         => array_values($meses),
            'resumen'       => [
                'impuesto_anual'   => round($impuestoAnual, 2),
                'retenido'         => round($retenidoReal, 2),
                'por_retener'      => round(max(0, $impuestoAnual - $retenidoReal), 2),
                'meses_con_dato'   => array_keys($reales),
                // Solo los meses que ya pasaron: los que vienen todavía no tienen por qué tener dato.
                'meses_sin_dato'   => $this->mesesPasadosSinDato($meses, $reales, $anio),
            ],
        ];
    }

    /**
     * Un mes de la hoja con el método de RR.HH. Enero y febrero no los calcula
     * su hoja: los trae escritos, y se toman como se retuvieron. Sin dato, se
     * quedan con la proyección de SUNAT, igual que la planilla.
     */
    private function filaComoRrhh(array $fila, Empleado $empleado, int $mes, int $anio, float $sueldo, array $retenidas): array
    {
        if ($mes < 3) {
            $real = $fila['retencion_real'];

            return $real === null ? $fila
                : array_merge($fila, ['retencion_ordinaria' => $real, 'retencion_adicional' => 0.0, 'retencion' => $real]);
        }

        $d = $this->desgloseRenta5taComoElColegio($empleado, $sueldo, $mes, $anio, null, $retenidas);

        return array_merge($fila, [
            'remuneracion_mensual'      => round($d['ingreso_mensual'], 2),
            'meses_que_faltan'          => $d['meses'],
            'remuneracion_proyectada'   => round($d['proyectado'], 2),
            'gratificaciones'           => round($d['gratificaciones'], 2),
            'vacaciones_truncas'        => round($d['vacaciones_truncas'], 2),
            'remuneraciones_anteriores' => round($d['anteriores'], 2),
            'renta_bruta'               => round($d['renta_bruta'], 2),
            'renta_neta'                => round(max(0, $d['renta_bruta'] - $fila['deduccion']), 2),
            'impuesto_anual'            => round($d['impuesto'], 2),
            'retenido_antes'            => round($d['retenido'], 2),
            'divisor'                   => $d['pendientes'],
            'retencion_ordinaria'       => round($d['retencion'], 2),
            'extraordinario'            => 0.0,
            'retencion_adicional'       => 0.0,
            'retencion'                 => round($d['retencion'], 2),
        ]);
    }

    /** Los meses ya pasados (antes del mes en curso) que trabajó y no tienen planilla ni historial. */
    private function mesesPasadosSinDato(array $meses, array $reales, int $anio): array
    {
        $hasta = $anio < (int) now()->year ? 12 : ($anio > (int) now()->year ? 0 : (int) now()->month - 1);

        return array_values(array_filter(range(1, 12),
            fn ($m) => $m <= $hasta && $meses[$m]['trabaja'] && ! isset($reales[$m]) && ! $this->mesCargado($m, $anio)));
    }

    /**
     * Lo que habría cobrado un mes del que no hay dato, según su ficha: el
     * sueldo de ese mes (prorrateado si entró a mitad), la asignación, la
     * bonificación por cargo y, en julio y diciembre, la gratificación con su 9%.
     */
    private function estimadoDelMes(Empleado $empleado, int $mes, int $anio, float $asignacion): float
    {
        $ordinario = $this->sueldoFicha($empleado, $mes, $anio) + $asignacion + $this->bonifFicha($empleado, $mes, $anio);

        if (in_array($mes, [7, 12], true)) {
            $mensual = (float) $empleado->sueldo_base + $asignacion + (float) ($empleado->bonificacion_cargo ?? 0);
            $semestre = $this->calcularGratificacion($empleado, $mensual, $mes, $anio);
            $ordinario += $mensual / 6 * $semestre['meses_trabajados'] * (1 + $this->valoresLegales($anio)->essalud / 100);
        }

        return $ordinario;
    }

    /** El sueldo de ese mes según su ficha (prorrateado si entró o cesó a mitad). */
    private function sueldoFicha(Empleado $empleado, int $mes, int $anio): float
    {
        return $this->recordar("sueldo-{$empleado->id}-{$anio}-{$mes}", fn () => (float) ($this->sueldoDelMes($empleado, $mes, $anio) ?? 0));
    }

    private function bonifFicha(Empleado $empleado, int $mes, int $anio): float
    {
        return $this->recordar("bonif-{$empleado->id}-{$anio}-{$mes}", fn () => $this->bonificacionCargoDelMes($empleado, $mes, $anio));
    }

    /** Lo que tiene una línea de su planilla del mes (0 si no hay planilla o línea). */
    private function lineaDe(Empleado $empleado, int $mes, int $anio, string $concepto): float
    {
        return (float) \App\Models\PayrollDetalle::whereHas('planilla', fn ($q) => $q
                ->where('empleado_id', $empleado->id)->where('anio', $anio)->where('mes', $mes))
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', $concepto))
            ->sum('monto_calculado');
    }
}
