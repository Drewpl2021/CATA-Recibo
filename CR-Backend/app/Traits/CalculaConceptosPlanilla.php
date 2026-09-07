<?php
namespace App\Traits;

trait CalculaConceptosPlanilla
{
    private array $comisionesAfp = [
        'Habitat'   => 1.47,
        'Integra'   => 1.55,
        'Prima'     => 1.60,
        'Profuturo' => 1.69,
    ];

    private float $aporteObligatorioAfp = 10.00;
    private float $primaSeguroAfp = 1.37;
    private float $porcentajeOnp = 13.00;
    private float $porcentajeEssalud = 9.00;
    private float $asignacionFamiliarMonto = 113.00;
    private float $uitValor = 5500.00;

    /**
     * Estos conceptos SIEMPRE se calculan con su propia lógica especial por empleado
     * (arriba en generarConceptosAutomaticos) — nunca deben procesarse por el motor
     * genérico de "aplica_a_todos", así ese campo quede marcado true por error.
     */
    private const CONCEPTOS_CON_CALCULO_ESPECIAL = [
        'ONP', 'SPP Fondo de Pensiones', 'SPP Prima de Seguro', 'SPP Comisión',
        'ESSALUD', 'I.R. 5ta Categoría',
    ];

    protected function calcularDescuentoPension($empleado, $sueldoBase): array
    {
        $sueldoBase = (float) $sueldoBase;

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $comisionAfp = $this->comisionesAfp[$empleado->afp] ?? 0;

            $aporte   = round($sueldoBase * ($this->aporteObligatorioAfp / 100), 2);
            // $primaSeguroAfp (1.37%, igual para todas las AFP) y $comisionAfp (varía por AFP)
            // son los montos; los nombres "Prima de Seguro" / "Comisión" abajo van
            // intercambiados a propósito respecto a esas variables — así es como el
            // colegio los llama en su boleta oficial (verificado contra el documento físico).
            $prima    = round($sueldoBase * ($this->primaSeguroAfp / 100), 2);
            $comision = round($sueldoBase * ($comisionAfp / 100), 2);

            return [
                'tipo'    => 'AFP - ' . $empleado->afp,
                'detalle' => [
                    ['concepto' => 'SPP Fondo Pensiones', 'monto' => $aporte],
                    ['concepto' => 'SPP Prima de Seguro', 'monto' => $comision],
                    ['concepto' => 'SPP Comisión', 'monto' => $prima],
                ],
                'total' => round($aporte + $prima + $comision, 2),
            ];
        }

        $monto = round($sueldoBase * ($this->porcentajeOnp / 100), 2);
        return [
            'tipo'    => 'ONP',
            'detalle' => [
                ['concepto' => 'ONP 13%', 'monto' => $monto],
            ],
            'total' => $monto,
        ];
    }

    protected function calcularAsignacionFamiliar($empleado): float
    {
        return $empleado->tiene_hijos ? $this->asignacionFamiliarMonto : 0.00;
    }

    private float $bonificacionExtraordinariaEssalud = 9.00;

    protected function calcularGratificacion($empleado, $sueldoBase, $mes, $anio): array
    {
        $mes  = (int) $mes;
        $anio = (int) $anio;

        if (!in_array($mes, [7, 12])) {
            return [
                'aplica'                    => false,
                'meses_trabajados'          => 0,
                'monto_base'                => 0.00,
                'asignacion_familiar'       => 0.00,
                'bonificacion_extraordinaria' => 0.00,
                'total'                     => 0.00,
            ];
        }

        $sueldoBase = (float) $sueldoBase;

        if ($mes === 7) {
            $inicioSemestre = \Carbon\Carbon::create($anio, 1, 1);
            $finSemestre    = \Carbon\Carbon::create($anio, 6, 30);
        } else {
            $inicioSemestre = \Carbon\Carbon::create($anio, 7, 1);
            $finSemestre    = \Carbon\Carbon::create($anio, 12, 31);
        }

        $fechaIngreso = \Carbon\Carbon::parse($empleado->fecha_ingreso);

        // Si ingresó después del semestre correspondiente, no le corresponde esta gratificación
        if ($fechaIngreso->gt($finSemestre)) {
            return [
                'aplica'                    => false,
                'meses_trabajados'          => 0,
                'monto_base'                => 0.00,
                'asignacion_familiar'       => 0.00,
                'bonificacion_extraordinaria' => 0.00,
                'total'                     => 0.00,
            ];
        }

        $inicioEfectivo = $fechaIngreso->gt($inicioSemestre) ? $fechaIngreso : $inicioSemestre;

        $mesesTrabajados = $finSemestre->month - $inicioEfectivo->month + 1;
        $mesesTrabajados = min(6, max(0, $mesesTrabajados));

        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);

        $montoBase               = round(($sueldoBase * $mesesTrabajados) / 6, 2);
        $asignacionProrrateada   = round(($asignacionFamiliar * $mesesTrabajados) / 6, 2);
        $subtotal                = $montoBase + $asignacionProrrateada;
        $bonificacionExtraordinaria = round($subtotal * ($this->bonificacionExtraordinariaEssalud / 100), 2);

        return [
            'aplica'                    => true,
            'meses_trabajados'          => $mesesTrabajados,
            'monto_base'                => $montoBase,
            'asignacion_familiar'       => $asignacionProrrateada,
            'bonificacion_extraordinaria' => $bonificacionExtraordinaria,
            'total'                     => round($subtotal + $bonificacionExtraordinaria, 2),
        ];
    }

    protected function calcularEssalud($sueldoBase): float
    {
        return round((float) $sueldoBase * ($this->porcentajeEssalud / 100), 2);
    }

    /**
     * Procedimiento oficial de retención de Renta de 5ta Categoría — Art. 40 del
     * Reglamento de la Ley del Impuesto a la Renta (D.S. 122-94-EF). Cada mes usa un
     * multiplicador distinto para proyectar el ingreso anual, y un divisor distinto
     * (agrupado por tramos) para convertir el impuesto anual en la retención de ese mes.
     * Fuente verificada: orientacion.sunat.gob.pe y casos prácticos de contadores (2026).
     *
     *   mes  → multiplicador de proyección | divisor de la retención | mes de "corte"
     *          (hasta qué mes se suma lo YA retenido este año, para restarlo)
     */
    private const TRAMOS_RENTA_5TA = [
        1  => ['multiplicador' => 12, 'divisor' => 12, 'corte' => 0],
        2  => ['multiplicador' => 11, 'divisor' => 12, 'corte' => 0],
        3  => ['multiplicador' => 10, 'divisor' => 12, 'corte' => 0],
        4  => ['multiplicador' => 9,  'divisor' => 9,  'corte' => 3],
        5  => ['multiplicador' => 8,  'divisor' => 8,  'corte' => 4],
        6  => ['multiplicador' => 7,  'divisor' => 8,  'corte' => 4],
        7  => ['multiplicador' => 6,  'divisor' => 8,  'corte' => 4],
        8  => ['multiplicador' => 5,  'divisor' => 5,  'corte' => 7],
        9  => ['multiplicador' => 4,  'divisor' => 4,  'corte' => 8],
        10 => ['multiplicador' => 3,  'divisor' => 4,  'corte' => 8],
        11 => ['multiplicador' => 2,  'divisor' => 4,  'corte' => 8],
        12 => ['multiplicador' => 1,  'divisor' => 1,  'corte' => 11], // regularización final
    ];

    /**
     * Calcula la retención de Renta de 5ta Categoría del mes indicado, siguiendo el
     * procedimiento real de SUNAT (no un promedio simplificado ×12 fijo):
     *  - Proyecta el ingreso anual usando los meses que REALMENTE faltan para terminar
     *    el año (funciona igual para un empleado que ingresó a mitad de año, porque el
     *    "corte" siempre es respecto al año calendario, no a su fecha de ingreso).
     *  - Incluye las gratificaciones que este empleado va a recibir de verdad (prorrateadas
     *    si ingresó a mitad de semestre — reutiliza calcularGratificacion()).
     *  - Suma los ingresos extraordinarios (bonos, subsidios puntuales) ya percibidos
     *    este año, vía PayrollDetalle tipo "bonificacion".
     *  - Resta lo que ya se retuvo en meses anteriores del mismo año (a partir de abril),
     *    consultando los PayrollDetalle de "I.R. 5ta Categoría" ya persistidos.
     *  - Diciembre regulariza con el ingreso REAL de los 12 meses, no proyectado.
     *
     * Simplificación consciente: los ingresos extraordinarios del propio mes actual se
     * suman a la proyección igual que los de meses anteriores, en vez de aplicarles el
     * sub-procedimiento aparte de "retención adicional" que exige la norma para pagos
     * extraordinarios del mismo mes — la diferencia se autocorrige en la regularización
     * de diciembre.
     */
    protected function calcularRenta5taCategoria($empleado, $sueldoBase, $bonificaciones, $mes, $anio): float
    {
        $mes            = (int) $mes;
        $anio           = (int) $anio;
        $sueldoBase     = (float) $sueldoBase;
        $bonificaciones = (float) $bonificaciones;

        $tramo = self::TRAMOS_RENTA_5TA[$mes] ?? self::TRAMOS_RENTA_5TA[1];

        // La Asignación Familiar es remunerativa y también afecta a Renta de 5ta Categoría.
        $remuneracionOrdinaria = $sueldoBase + $bonificaciones + $this->calcularAsignacionFamiliar($empleado);

        $gratificacionJulio     = $this->calcularGratificacion($empleado, $sueldoBase, 7, $anio);
        $gratificacionDiciembre = $this->calcularGratificacion($empleado, $sueldoBase, 12, $anio);
        $gratificacionesDelEjercicio = $gratificacionJulio['total'] + $gratificacionDiciembre['total'];

        $ingresosExtraordinarios = $this->ingresosExtraordinariosAcumulados($empleado->id, $anio, $mes);

        $multiplicador = $mes === 12 ? 12 : $tramo['multiplicador'];
        $ingresoAnual = ($remuneracionOrdinaria * $multiplicador)
            + $gratificacionesDelEjercicio
            + $ingresosExtraordinarios;

        $exento = $this->uitValor * 7;
        if ($ingresoAnual <= $exento) {
            return 0.00;
        }

        $impuestoAnual = $this->aplicarTramosImpuestoRenta($ingresoAnual - $exento);
        $retencionesAcumuladas = $this->retencionesRenta5taAcumuladas($empleado->id, $anio, $tramo['corte']);

        if ($mes === 12) {
            return round(max(0, $impuestoAnual - $retencionesAcumuladas), 2);
        }

        return round(max(0, ($impuestoAnual - $retencionesAcumuladas) / $tramo['divisor']), 2);
    }

    /**
     * Tramos progresivos acumulativos vigentes (8/14/17/20/30% sobre 5/20/35/45 UIT).
     */
    private function aplicarTramosImpuestoRenta(float $rentaNeta): float
    {
        $uit      = $this->uitValor;
        $impuesto = 0.00;

        $tramo1 = $uit * 5; // 8% hasta 5 UIT
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo1);
            $impuesto += $base * 0.08;
            $rentaNeta -= $base;
        }

        $tramo2 = $uit * 15; // 14% de 5 a 20 UIT
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo2);
            $impuesto += $base * 0.14;
            $rentaNeta -= $base;
        }

        $tramo3 = $uit * 15; // 17% de 20 a 35 UIT
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo3);
            $impuesto += $base * 0.17;
            $rentaNeta -= $base;
        }

        $tramo4 = $uit * 10; // 20% de 35 a 45 UIT
        if ($rentaNeta > 0) {
            $base = min($rentaNeta, $tramo4);
            $impuesto += $base * 0.20;
            $rentaNeta -= $base;
        }

        if ($rentaNeta > 0) { // 30% sobre el exceso de 45 UIT
            $impuesto += $rentaNeta * 0.30;
        }

        return $impuesto;
    }

    /**
     * Suma lo ya retenido por "I.R. 5ta Categoría" en los meses 1..$mesCorte de este año
     * para este empleado. $mesCorte = 0 significa "nada que restar" (tramo enero-marzo).
     */
    private function retencionesRenta5taAcumuladas($empleadoId, int $anio, int $mesCorte): float
    {
        if ($mesCorte <= 0) {
            return 0.0;
        }

        return (float) \App\Models\PayrollDetalle::whereHas('planilla', function ($q) use ($empleadoId, $anio, $mesCorte) {
                $q->where('empleado_id', $empleadoId)->where('anio', $anio)->where('mes', '<=', $mesCorte);
            })
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', 'I.R. 5ta Categoría'))
            ->sum('monto_calculado');
    }

    /**
     * Suma los ingresos "extraordinarios" (cualquier PayrollDetalle tipo bonificacion:
     * bonos, subsidios puntuales, etc.) ya percibidos este año hasta el mes indicado.
     */
    private function ingresosExtraordinariosAcumulados($empleadoId, int $anio, int $mesHasta): float
    {
        return (float) \App\Models\PayrollDetalle::whereHas('planilla', function ($q) use ($empleadoId, $anio, $mesHasta) {
                $q->where('empleado_id', $empleadoId)->where('anio', $anio)->where('mes', '<=', $mesHasta);
            })
            ->whereHas('paymentConcept', fn ($q) => $q->where('tipo', 'bonificacion'))
            ->sum('monto_calculado');
    }

    /**
     * Calcula la retención de Renta de 5ta de este mes y la deja registrada como
     * PayrollDetalle (para que los meses siguientes puedan "restar lo ya retenido").
     * Se llama tanto al crear la planilla como cada vez que se genera su boleta, así
     * el monto siempre refleja los conceptos más recientes de ese mes.
     */
    protected function generarYPersistirRenta5ta($planilla, $empleado): float
    {
        $renta5ta = $this->calcularRenta5taCategoria(
            $empleado,
            $planilla->sueldo_base,
            $planilla->bonificaciones,
            $planilla->mes,
            $planilla->anio
        );

        $concepto = \App\Models\PaymentConcept::where('nombre', 'I.R. 5ta Categoría')->first();
        if ($concepto) {
            if ($renta5ta > 0) {
                \App\Models\PayrollDetalle::updateOrCreate(
                    ['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id],
                    [
                        'monto_calculado' => $renta5ta,
                        'descripcion'     => 'Calculado automáticamente (Art. 40 Reglamento LIR)',
                    ]
                );
            } else {
                \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                    ->where('payment_concept_id', $concepto->id)
                    ->delete();
            }
            $planilla->recalcularTotal();
        }

        return $renta5ta;
    }

    /**
     * Genera TODOS los conceptos automáticos de una planilla recién creada:
     *  1) Pensión según ONP/AFP del empleado + EsSalud (siempre, dependen de cada
     *     empleado, no hay forma de marcarlos "aplica_a_todos" desde el catálogo).
     *  2) Cualquier PaymentConcept marcado aplica_a_todos=true — un bono, descuento,
     *     etc. que el colegio decide que le toca a TODOS: si es "fijo" se aplica el
     *     mismo monto a cada empleado, si es "porcentaje" se calcula sobre SU sueldo.
     *  3) Renta de 5ta Categoría (Art. 40 Reglamento LIR).
     *
     * La usan tanto la creación individual (PlanillaController::store) como la
     * generación masiva por Periodo (PeriodoController::generarPlanilla), para que
     * ambos caminos generen exactamente los mismos conceptos de la misma forma.
     *
     * Conceptos como Diezmo, Adelantos, Escolaridad, etc. siguen siendo manuales
     * porque dependen de una autorización puntual del empleado, no de una regla fija.
     */
    protected function generarConceptosAutomaticos($planilla, $empleado): void
    {
        $sueldoBase         = (float) $planilla->sueldo_base;
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);
        $baseAfecta         = $sueldoBase + $asignacionFamiliar;

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $this->crearDetalleAutomatico($planilla, 'SPP Fondo de Pensiones', $baseAfecta * ($this->aporteObligatorioAfp / 100));
            // OJO: "Prima de Seguro" / "Comisión" van intercambiadas a propósito respecto
            // a las variables del trait — así las llama la boleta oficial del colegio.
            $this->crearDetalleAutomatico($planilla, 'SPP Comisión', $baseAfecta * ($this->primaSeguroAfp / 100));

            $comisionAfp = $this->comisionesAfp[$empleado->afp] ?? 0;
            if ($comisionAfp > 0) {
                $this->crearDetalleAutomatico($planilla, 'SPP Prima de Seguro', $baseAfecta * ($comisionAfp / 100), "AFP {$empleado->afp} ({$comisionAfp}%)");
            }
        } else {
            $this->crearDetalleAutomatico($planilla, 'ONP', $baseAfecta * ($this->porcentajeOnp / 100));
        }

        $this->crearDetalleAutomatico($planilla, 'ESSALUD', $this->calcularEssalud($baseAfecta));

        // Conceptos marcados como "fijo para todos" en el catálogo — EXCLUYENDO siempre
        // los de pensión/EsSalud/Renta 5ta, que arriba ya reciben su cálculo especial
        // por empleado (varía según ONP/AFP, AFP específica, o el historial del año).
        // Si alguno de estos quedara marcado aplica_a_todos=true por error desde el
        // panel, este bloqueo evita que el motor genérico le pise el valor correcto
        // con uno incorrecto (una fórmula plana que no conoce la asignación familiar
        // ni la AFP de cada quien).
        $conceptosFijos = \App\Models\PaymentConcept::where('aplica_a_todos', true)
            ->whereNotIn('nombre', self::CONCEPTOS_CON_CALCULO_ESPECIAL)
            ->get();
        foreach ($conceptosFijos as $concepto) {
            $monto = $concepto->calculo === 'porcentaje'
                ? $sueldoBase * ((float) $concepto->valor / 100)
                : (float) $concepto->valor;

            $this->crearDetalleAutomatico($planilla, $concepto->nombre, $monto, 'Aplicado automáticamente a todos los empleados');
        }

        // Se recalcula también cada vez que se genera la boleta (por si RRHH agrega
        // bonos después de crear la planilla), pero se estima ya aquí para que
        // Planilla.total esté lo más correcto posible desde el primer momento.
        $this->generarYPersistirRenta5ta($planilla, $empleado);
    }

    private function crearDetalleAutomatico($planilla, string $nombreConcepto, float $monto, ?string $descripcion = null): void
    {
        $concepto = \App\Models\PaymentConcept::where('nombre', $nombreConcepto)->first();
        if (!$concepto || $monto <= 0) {
            return;
        }

        \App\Models\PayrollDetalle::updateOrCreate(
            ['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id],
            [
                'monto_calculado' => round($monto, 2),
                'descripcion'     => $descripcion ?? 'Generado automáticamente al crear la planilla',
            ]
        );
    }

    /**
     * Datos de cabecera de la boleta que no vienen directos de Empleado/Planilla,
     * sino que hay que derivarlos: la categoría real (del Contrato vigente, no del
     * campo suelto y potencialmente desactualizado Empleado.tipo_contrato), la fecha
     * de cese (del último Contrato finalizado, si lo hay), y el rango de fechas del
     * mes de la boleta. "Días no trabajados" queda en 0 porque el sistema todavía no
     * tiene un módulo de asistencia — es un valor honesto, no inventado.
     */
    protected function datosCabeceraBoleta($empleado, int $mes, int $anio): array
    {
        $inicioMes = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth();

        $contratoVigente    = $empleado->contratos()->where('estado', 'vigente')->latest('fecha_inicio')->first();
        $contratoFinalizado = $empleado->contratos()->where('estado', 'finalizado')->latest('fecha_fin')->first();

        $tipoContrato = $contratoVigente->tipo_contrato ?? $empleado->tipo_contrato;
        $categoria    = $tipoContrato ? ucfirst(str_replace('_', ' ', $tipoContrato)) : '-';

        return [
            'dias_trabajados'    => $inicioMes->daysInMonth,
            'dias_no_trabajados' => 0,
            'fecha_cese'         => $contratoFinalizado?->fecha_fin,
            'categoria'          => $categoria,
            'rango_inicio'       => $inicioMes->format('d/m/Y'),
            'rango_fin'          => $finMes->format('d/m/Y'),
        ];
    }
}