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
    /**
     * Los seis que se calculan por empleado. La lista vive en
     * App\Support\ConceptosDePago junto a los nombres, para que no puedan
     * separarse del seeder: el motor los busca por nombre exacto y cuando no
     * los encuentra NO falla, simplemente no crea la línea.
     */
    private const CONCEPTOS_CON_CALCULO_ESPECIAL = \App\Support\ConceptosDePago::CALCULO_ESPECIAL;

    protected function calcularDescuentoPension($empleado, $sueldoBase): array
    {
        $sueldoBase = (float) $sueldoBase;

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $comisionAfp = $this->comisionesAfp[$empleado->afp] ?? 0;

            $aporte   = round($sueldoBase * ($this->aporteObligatorioAfp / 100), 2);

            /*
             * Cada nombre con SU monto: la prima es la fija, la comisión la
             * que cambia según la AFP.
             *
             * Hasta el 2026-09-13 iban intercambiados a propósito, siguiendo
             * la boleta física del colegio. Se deshizo porque el PLAME del
             * propio colegio usa el criterio contrario, que además es el
             * estándar: en la hoja PLANILLA, columna AC "COMISIÓN % SOBRE
             * R.A." lleva el 1.69 de Profuturo y la AD "PRIMA DE SEGURO" el
             * 1.37 fijo (comprobado contra la fila de AGUIRRE VELAZCO).
             *
             * NO volver a cruzarlos: el total descontado es el mismo en los
             * dos casos, así que el error no salta en ninguna suma — solo
             * queda mal el nombre en la boleta y en la declaración.
             */
            $prima    = round($sueldoBase * ($this->primaSeguroAfp / 100), 2);
            $comision = round($sueldoBase * ($comisionAfp / 100), 2);

            return [
                'tipo'    => 'AFP - ' . $empleado->afp,
                // Las mismas etiquetas que el catálogo, para que la boleta y la
                // pantalla de Conceptos de Pago no se llamen distinto.
                'detalle' => [
                    ['concepto' => \App\Support\ConceptosDePago::SPP_FONDO, 'monto' => $aporte],
                    ['concepto' => \App\Support\ConceptosDePago::SPP_PRIMA_SEGURO, 'monto' => $prima],
                    ['concepto' => \App\Support\ConceptosDePago::SPP_COMISION, 'monto' => $comision],
                ],
                'total' => round($aporte + $prima + $comision, 2),
            ];
        }

        // Sin sistema de pensiones no se descuenta nada. Antes esto era un
        // "else" que caía en ONP, así que al jubilado que vuelve a dictar
        // —que por ley ya no aporta— se le descontaba el 13% igual.
        if ($empleado->sistema_pensiones !== 'ONP') {
            return [
                'tipo'    => 'No aporta',
                'detalle' => [],
                'total'   => 0.0,
            ];
        }

        $monto = round($sueldoBase * ($this->porcentajeOnp / 100), 2);
        return [
            'tipo'    => 'ONP',
            'detalle' => [
                ['concepto' => \App\Support\ConceptosDePago::ONP, 'monto' => $monto],
            ],
            'total' => $monto,
        ];
    }

    protected function calcularAsignacionFamiliar($empleado): float
    {
        return $empleado->tiene_hijos ? $this->asignacionFamiliarMonto : 0.00;
    }

    /**
     * La Asignación Familiar que se imprime en la boleta: la de la planilla.
     *
     * La boleta la muestra en su propia fila, y al mismo tiempo recorría los
     * conceptos de ingreso —donde esa misma línea ya estaba—, así que la
     * sumaba DOS VECES: el PDF decía S/ 2,290.31 donde la planilla tenía
     * S/ 2,177.31. Un documento de pago no puede decir una cifra distinta de
     * la que se paga.
     *
     * Se lee de la línea de la planilla y no de `tiene_hijos` para que, si
     * RR.HH. la corrige a mano, la boleta enseñe lo corregido. Sin línea son
     * 0: la boleta dice lo que hay en la planilla, ni más ni menos.
     */
    protected function asignacionFamiliarDeLaPlanilla($planilla): float
    {
        return (float) $planilla->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::ASIGNACION_FAMILIAR))
            ->sum('monto_calculado');
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
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::RENTA_5TA))
            ->sum('monto_calculado');
    }

    /**
     * Suma los ingresos "extraordinarios" (cualquier PayrollDetalle tipo bonificacion:
     * bonos, subsidios puntuales, etc.) ya percibidos este año hasta el mes indicado.
     *
     * La Asignación Familiar se EXCLUYE a propósito, y esto no es un detalle:
     * quien la llama ya la sumó aparte, dentro de la remuneración ordinaria
     * (`$sueldoBase + $bonificaciones + calcularAsignacionFamiliar()`), porque
     * se cobra todos los meses y no es un ingreso extraordinario.
     *
     * Mientras la asignación no existía como línea las dos vías no se
     * cruzaban. Desde que se crea como concepto —que es lo correcto, y lo que
     * hace que llegue al neto— caería en esta suma también, y la proyección
     * anual contaría esos 113 dos veces: retención de 5ta inflada para quien
     * tiene hijos.
     */
    private function ingresosExtraordinariosAcumulados($empleadoId, int $anio, int $mesHasta): float
    {
        return (float) \App\Models\PayrollDetalle::whereHas('planilla', function ($q) use ($empleadoId, $anio, $mesHasta) {
                $q->where('empleado_id', $empleadoId)->where('anio', $anio)->where('mes', '<=', $mesHasta);
            })
            ->whereHas('paymentConcept', fn ($q) => $q
                ->where('tipo', 'bonificacion')
                // Fuera la asignación familiar (es remuneración ordinaria y
                // quien llama ya la suma) y fuera las dos líneas de
                // gratificación: la proyección de arriba calcula las de julio
                // Y diciembre del año entero por fórmula, así que contar
                // además su línea inflaría la retención.
                ->whereNotIn('nombre', [
                    \App\Support\ConceptosDePago::ASIGNACION_FAMILIAR,
                    \App\Support\ConceptosDePago::GRATIFICACION,
                    \App\Support\ConceptosDePago::BONIF_EXTRAORDINARIA,
                ]))
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
        /*
         * Cero, y no la columna `bonificaciones` de la planilla.
         *
         * No es que se pierdan: las bonificaciones viven ahora como líneas de
         * concepto, y `ingresosExtraordinariosAcumulados()` —al que llama
         * calcularRenta5taCategoria— ya las suma todas. Pasar además la
         * columna las contaría dos veces, que es exactamente el error que
         * acabamos de corregir con la Asignación Familiar.
         */
        $renta5ta = $this->calcularRenta5taCategoria(
            $empleado,
            $planilla->sueldo_base,
            0.0,
            $planilla->mes,
            $planilla->anio
        );

        $concepto = \App\Models\PaymentConcept::where('nombre', \App\Support\ConceptosDePago::RENTA_5TA)->first();
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

        /*
         * La Asignación Familiar, como línea de verdad.
         *
         * ESTO FALTABA, y era un error que costaba dinero al trabajador: la
         * asignación se sumaba a $baseAfecta para calcular pensión y EsSalud,
         * pero nunca se creaba su concepto. Como `recalcularTotal()` suma los
         * conceptos de tipo bonificación, esos 113 nunca llegaban al neto: se
         * le descontaba pensión sobre un dinero que no cobraba.
         *
         * Sale de `tiene_hijos` en su ficha, no del catálogo: si esa casilla
         * está en 0 no se crea ninguna línea, igual que no se crea la de ONP
         * a quien está en AFP.
         */
        if ($asignacionFamiliar > 0) {
            $this->crearDetalleAutomatico(
                $planilla,
                \App\Support\ConceptosDePago::ASIGNACION_FAMILIAR,
                $asignacionFamiliar
            );
        }

        /*
         * Julio y diciembre: la gratificación y su bonificación extraordinaria,
         * también como líneas de la planilla.
         *
         * Antes solo existían dentro del PDF: la boleta las calculaba y las
         * sumaba, pero la planilla no las tenía. El documento de pago decía
         * S/ 5,243.48 donde el sistema guardaba S/ 2,177.31, y ni el panel, ni
         * las corridas, ni el PLAME veían ese dinero. Ahora se crean acá, igual
         * que la asignación familiar, y la boleta solo imprime lo que hay.
         *
         * No entran en $baseAfecta: están exoneradas de pensión y EsSalud
         * (Ley 29351/30334), y por eso mismo existe la bonificación del 9%.
         */
        $gratificacion = $this->calcularGratificacion($empleado, $sueldoBase, $planilla->mes, $planilla->anio);
        if ($gratificacion['aplica']) {
            $this->crearDetalleAutomatico(
                $planilla,
                \App\Support\ConceptosDePago::GRATIFICACION,
                $gratificacion['monto_base'] + $gratificacion['asignacion_familiar'],
                \App\Support\Meses::nombre((int) $planilla->mes) . ", {$gratificacion['meses_trabajados']}/6 meses"
            );

            $this->crearDetalleAutomatico(
                $planilla,
                \App\Support\ConceptosDePago::BONIF_EXTRAORDINARIA,
                $gratificacion['bonificacion_extraordinaria']
            );
        }

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_FONDO, $baseAfecta * ($this->aporteObligatorioAfp / 100));

            // La prima del seguro: 1.37%, igual para todas las AFP.
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_PRIMA_SEGURO, $baseAfecta * ($this->primaSeguroAfp / 100));

            // Y la comisión, que sí depende de cuál sea su AFP. Va con la tasa
            // escrita al lado porque es el dato que cambia de persona a
            // persona, y sin él la línea no se puede comprobar.
            //
            // Antes estos dos nombres iban cruzados; se enderezaron siguiendo
            // el PLAME del colegio. Ver el comentario largo en
            // calcularDescuentoPension() antes de tocarlo.
            $comisionAfp = $this->comisionesAfp[$empleado->afp] ?? 0;
            if ($comisionAfp > 0) {
                $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_COMISION, $baseAfecta * ($comisionAfp / 100), "AFP {$empleado->afp} ({$comisionAfp}%)");
            }
        } elseif ($empleado->sistema_pensiones === 'ONP') {
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::ONP, $baseAfecta * ($this->porcentajeOnp / 100));
        }
        // Sin sistema de pensiones no se crea ninguna línea: es el jubilado
        // que ya cobra su pensión o el extranjero con convenio. EsSalud sí se
        // le sigue aportando, que es cosa aparte.

        $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::ESSALUD, $this->calcularEssalud($baseAfecta));

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

            $this->crearDetalleAutomatico($planilla, $concepto->nombre, $monto);
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
                // Sin descripción cuando no hay nada que decir.
                //
                // La descripción se IMPRIME en la boleta pegada al nombre
                // ("Otros Conceptos: Subsidio de Maternidad"), así que meter
                // acá una nota del sistema hacía que al trabajador le llegara
                // impreso "Bono de Aniversario: Aplicado automáticamente a
                // todos los empleados". Esa nota es de la máquina, no del
                // concepto: la columna es para el detalle puntual de ESA
                // aplicación, y si no lo hay va vacía.
                'descripcion'     => $descripcion,
            ]
        );
    }

    /**
     * Datos de cabecera de la boleta que no vienen directos de Empleado/Planilla,
     * sino que hay que derivarlos: la categoría real (del Contrato vigente, no del
     * campo suelto y potencialmente desactualizado Empleado.tipo_contrato), la fecha
     * de cese (del último Contrato finalizado, si lo hay), el rango de fechas del
     * mes, y los días que el trabajador estuvo de vacaciones.
     *
     * Los días de vacaciones antes iban en 0 fijo porque no había de dónde
     * sacarlos. Ahora sí los hay, y dejarlos en cero era firmar un dato falso:
     * a quien se fue tres semanas la boleta le decía "Días Trabajados: 30".
     *
     * Ojo con lo que esto NO hace: no cambia ni un sol. Las vacaciones son
     * remuneradas — el trabajador cobra su sueldo completo el mes que las toma.
     * Acá solo se cuenta cómo se repartieron sus días.
     */
    protected function datosCabeceraBoleta($empleado, int $mes, int $anio): array
    {
        $inicioMes = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth();

        $contratoVigente    = $empleado->contratos()->where('estado', 'vigente')->latest('fecha_inicio')->first();
        $contratoFinalizado = $empleado->contratos()->where('estado', 'finalizado')->latest('fecha_fin')->first();

        $tipoContrato = $contratoVigente->tipo_contrato ?? $empleado->tipo_contrato;
        $categoria    = $tipoContrato ? ucfirst(str_replace('_', ' ', $tipoContrato)) : '-';

        $reparto = $this->repartoDeDiasDelMes($empleado, $mes, $anio);

        return [
            'dias_trabajados'  => $reparto['dias_trabajados'],
            'dias_vacaciones'  => $reparto['dias_vacaciones'],
            'fecha_cese'       => $contratoFinalizado?->fecha_fin,
            'categoria'        => $categoria,
            'rango_inicio'     => $inicioMes->format('d/m/Y'),
            'rango_fin'        => $finMes->format('d/m/Y'),
        ];
    }

    /**
     * Cómo se reparten los días de un mes para un trabajador concreto.
     *
     * Devuelve tres cosas y la proporción que sale de ellas:
     *
     *   dias_del_mes   los que tiene el mes (28, 30, 31)
     *   dias_pagados   por los que le toca cobrar: si entró el día 20, son 11,
     *                  no 30. Antes se le pagaba el mes entero aunque hubiera
     *                  entrado la semana pasada.
     *   dias_vacaciones  los que estuvo de descanso, que TAMBIÉN se pagan
     *   dias_trabajados  los que realmente vino, que es lo que va en la boleta
     *
     * La proporción es dias_pagados / dias_del_mes, y con ella se prorratea el
     * sueldo al crear la planilla. Las vacaciones NO entran en esa proporción:
     * son remuneradas, así que quien las toma cobra igual.
     *
     * Nota de lo que todavía no cubre: el cese a mitad de mes. Cuando a alguien
     * se le termina el contrato el día 12, esto le sigue pagando hasta fin de
     * mes. Hace falta lo mismo pero por el otro extremo.
     */
    protected function repartoDeDiasDelMes($empleado, int $mes, int $anio): array
    {
        $inicioMes = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth();
        $diasDelMes = $inicioMes->daysInMonth;

        $ingreso = $empleado->fecha_ingreso
            ? \Carbon\Carbon::parse($empleado->fecha_ingreso)->startOfDay()
            : null;

        // Todavía no había entrado: no le corresponde nada de este mes.
        if ($ingreso && $ingreso->gt($finMes)) {
            return [
                'dias_del_mes'    => $diasDelMes,
                'dias_pagados'    => 0,
                'dias_vacaciones' => 0,
                'dias_trabajados' => 0,
                'proporcion'      => 0.0,
                'entro_este_mes'  => false,
            ];
        }

        // Desde cuándo cuenta: su fecha de ingreso si cae dentro del mes, o el
        // día 1 si ya estaba desde antes.
        $desde = ($ingreso && $ingreso->gt($inicioMes)) ? $ingreso : $inicioMes;
        $diasPagados = (int) $desde->diffInDays($finMes) + 1;

        $diasVacaciones = $this->diasDeVacacionesEnElMes($empleado->id, $desde, $finMes);

        return [
            'dias_del_mes'    => $diasDelMes,
            'dias_pagados'    => $diasPagados,
            'dias_vacaciones' => $diasVacaciones,
            'dias_trabajados' => max(0, $diasPagados - $diasVacaciones),
            'proporcion'      => round($diasPagados / $diasDelMes, 6),
            'entro_este_mes'  => $ingreso && $ingreso->gt($inicioMes),
        ];
    }

    /**
     * El sueldo que le toca este mes, ya prorrateado si entró a mitad.
     *
     * Devuelve null cuando el trabajador todavía no había ingresado: eso no es
     * "cero soles", es que no hay planilla que armarle.
     */
    protected function sueldoDelMes($empleado, int $mes, int $anio): ?float
    {
        $reparto = $this->repartoDeDiasDelMes($empleado, $mes, $anio);

        if ($reparto['dias_pagados'] === 0) {
            return null;
        }

        return round((float) $empleado->sueldo_base * $reparto['proporcion'], 2);
    }

    /**
     * Cuántos días de este mes cayeron dentro de unas vacaciones APROBADAS.
     *
     * Solo cuentan las aprobadas y vigentes: una solicitud pendiente todavía
     * puede rechazarse, y una boleta no se arma con algo que quizá no pase.
     *
     * Se recorta contra el mes porque un periodo puede cruzar el cambio de mes
     * (del 28 de junio al 5 de julio): a la boleta de junio le tocan 3 días y a
     * la de julio 5, no los 8 a cada una.
     */
    protected function diasDeVacacionesEnElMes(string $empleadoId, \Carbon\Carbon $inicioMes, \Carbon\Carbon $finMes): int
    {
        $periodos = \App\Models\Vacacion::where('empleado_id', $empleadoId)
            ->where('estado', 'aprobado')
            ->where('estado_registro', 'activo')
            ->where('fecha_inicio', '<=', $finMes->toDateString())
            ->where('fecha_fin', '>=', $inicioMes->toDateString())
            ->get();

        $dias = 0;

        foreach ($periodos as $periodo) {
            $desde = \Carbon\Carbon::parse($periodo->fecha_inicio)->startOfDay()->max($inicioMes);
            $hasta = \Carbon\Carbon::parse($periodo->fecha_fin)->startOfDay()->min($finMes);

            // Ambos extremos incluidos, igual que al pedirlas.
            $dias += (int) $desde->diffInDays($hasta) + 1;
        }

        // Tope defensivo: si hubiera solicitudes solapadas mal grabadas, la
        // boleta no puede decir que trabajó días negativos.
        return min($dias, $inicioMes->daysInMonth);
    }
}