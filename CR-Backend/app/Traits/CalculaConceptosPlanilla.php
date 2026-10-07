<?php
namespace App\Traits;

trait CalculaConceptosPlanilla
{
    /**
     * Los montos de ley (UIT, asignación familiar, % de pensión y EsSalud)
     * del año de la planilla. Ya no están fijos aquí: cambian con los años,
     * y una planilla de 2025 armada hoy tiene que usar los de 2025. Ver
     * App\Models\ValorLegal. Sin año, los del año en curso.
     */
    /**
     * Los montos calculados (pensión, EsSalud, diezmo, Renta de 5ta) se
     * guardan con 6 decimales, como los deja el Excel del colegio: 44.89485
     * y no 44.89. Se redondea solo al sumar el total y al mostrarlos. Ver la
     * migración 2026_10_05_000003.
     */
    public const DECIMALES = 6;

    protected function valoresLegales(?int $anio = null): \App\Models\ValorLegal
    {
        return \App\Models\ValorLegal::delAnio($anio ?? (int) now()->year);
    }

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

    /**
     * Si ya no paga la prima del seguro de la AFP: el seguro de invalidez y
     * sobrevivencia cubre hasta los 65 años, así que a quien ya los cumplió
     * no se le cobra. Cuenta el mes ENTERO: quien cumple 65 a mitad de mes
     * paga ese mes y deja de pagar desde el siguiente.
     *
     * Así lo hace el PLAME del colegio: SONCO RAMOS (69 años, Integra) sale
     * con prima 0.00 mientras los demás afiliados pagan el 1.37%.
     */
    protected function exentoDePrimaAfp($empleado, ?int $mes = null, ?int $anio = null): bool
    {
        if (empty($empleado->fecha_nacimiento)) {
            return false;
        }

        $inicioDelMes = $mes && $anio
            ? \Carbon\Carbon::create($anio, $mes, 1)->startOfDay()
            : now()->startOfMonth();

        return \Carbon\Carbon::parse($empleado->fecha_nacimiento)->startOfDay()->addYears(65)->lte($inicioDelMes);
    }

    protected function calcularDescuentoPension($empleado, $sueldoBase, ?int $anio = null, ?int $mes = null): array
    {
        $sueldoBase = (float) $sueldoBase;
        $ley        = $this->valoresLegales($anio);

        if ($empleado->sistema_pensiones === 'AFP' && $empleado->afp) {
            $aporte = round($sueldoBase * ($ley->aporte_afp / 100), self::DECIMALES);

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
            $prima = $this->exentoDePrimaAfp($empleado, $mes, $anio)
                ? 0.0
                : round($sueldoBase * ($ley->prima_seguro_afp / 100), self::DECIMALES);

            /*
             * Comisión "Mixta" (afiliado de antes del 2013): la AFP la cobra
             * directo del fondo acumulado, no de la planilla. El PLAME real
             * del colegio confirma esto —columna "TIPO COMISIÓN"—, y en
             * marzo 2026 el 81% de los afiliados a AFP del colegio (57 de
             * 70) está en este esquema: cobrarles la comisión "por flujo"
             * igual que al resto era un descuento que no les corresponde.
             */
            $comisionAfp = $empleado->tipo_comision_afp === 'mixta' ? 0 : $ley->comisionAfp($empleado->afp);
            $comision    = round($sueldoBase * ($comisionAfp / 100), self::DECIMALES);

            $detalle = [
                ['concepto' => \App\Support\ConceptosDePago::SPP_FONDO, 'monto' => $aporte],
            ];
            // Con 65 años o más la prima no se cobra: la línea ni aparece.
            if ($prima > 0) {
                $detalle[] = ['concepto' => \App\Support\ConceptosDePago::SPP_PRIMA_SEGURO, 'monto' => $prima];
            }
            if ($comision > 0) {
                $detalle[] = ['concepto' => \App\Support\ConceptosDePago::SPP_COMISION, 'monto' => $comision];
            }

            return [
                'tipo'    => 'AFP - ' . $empleado->afp,
                // Las mismas etiquetas que el catálogo, para que la boleta y la
                // pantalla de Conceptos de Pago no se llamen distinto.
                'detalle' => $detalle,
                'total'   => round($aporte + $prima + $comision, self::DECIMALES),
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

        $monto = round($sueldoBase * ($ley->onp / 100), self::DECIMALES);
        return [
            'tipo'    => 'ONP',
            'detalle' => [
                ['concepto' => \App\Support\ConceptosDePago::ONP, 'monto' => $monto],
            ],
            'total' => $monto,
        ];
    }

    protected function calcularAsignacionFamiliar($empleado, ?int $anio = null): float
    {
        return $empleado->tiene_hijos ? $this->valoresLegales($anio)->asignacion_familiar : 0.00;
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

    /**
     * Lo que ya tenga la planilla de Bonificación por Cargo y de Vacaciones
     * Truncas también suma a la base de AFP/ONP/EsSalud y Diezmo — igual
     * que la Asignación Familiar, y por la misma razón: ninguna de las dos
     * sale de una fórmula del motor, así que solo se puede leer de la línea
     * que ya exista en la planilla (o 0, si todavía no la agregaron).
     *
     * Confirmado contra el PLAME real: columnas O+P+Q+R de la hoja
     * PLANILLA son exactamente Sueldo + Bonificación por Cargo +
     * Asignación Familiar + Vacaciones Truncas, y esa es la base que usan
     * sus fórmulas de ONP, AFP, EsSalud Y Diezmo, las cuatro por igual.
     */
    protected function otrosIngresosAfectosDeLaPlanilla($planilla): float
    {
        return (float) $planilla->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->whereIn('nombre', [
                \App\Support\ConceptosDePago::BONIFICACION_CARGO,
                \App\Support\ConceptosDePago::VACACIONES_TRUNCAS,
            ]))
            ->sum('monto_calculado');
    }


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

        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado, $anio);

        $montoBase               = round(($sueldoBase * $mesesTrabajados) / 6, 2);
        $asignacionProrrateada   = round(($asignacionFamiliar * $mesesTrabajados) / 6, 2);
        $subtotal                = $montoBase + $asignacionProrrateada;
        // Ley 30334: la bonificación es lo que el colegio habría aportado a
        // EsSalud sobre la gratificación, así que usa la misma tasa del año.
        $bonificacionExtraordinaria = round($subtotal * ($this->valoresLegales($anio)->essalud / 100), 2);

        return [
            'aplica'                    => true,
            'meses_trabajados'          => $mesesTrabajados,
            'monto_base'                => $montoBase,
            'asignacion_familiar'       => $asignacionProrrateada,
            'bonificacion_extraordinaria' => $bonificacionExtraordinaria,
            'total'                     => round($subtotal + $bonificacionExtraordinaria, 2),
        ];
    }

    /**
     * EsSalud: el % del año sobre la base, pero nunca sobre menos que la
     * remuneración mínima (RMV). Así lo hace el PLAME del colegio: SONCO
     * RAMOS gana 582.80 y su EsSalud es 101.70 (9% de 1 130), no 52.45.
     *
     * Para quien trabajó solo parte del mes (entró o cesó a mitad), el piso
     * es la parte de la RMV que le toca: $proporcionDelMes, la misma con que
     * se prorratea su sueldo. Sin remuneración no hay aporte.
     */
    protected function calcularEssalud($sueldoBase, ?int $anio = null, float $proporcionDelMes = 1.0): float
    {
        $sueldoBase = (float) $sueldoBase;
        if ($sueldoBase <= 0) {
            return 0.00;
        }

        $ley  = $this->valoresLegales($anio);
        $base = max($sueldoBase, $ley->rmv * $proporcionDelMes);

        return round($base * ($ley->essalud / 100), self::DECIMALES);
    }

    /** Qué parte del mes trabajó (1 = el mes entero), para el piso de EsSalud. */
    protected function proporcionDelMes($empleado, int $mes, int $anio): float
    {
        return (float) $this->repartoDeDiasDelMes($empleado, $mes, $anio)['proporcion'];
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
     * Calcula la retención de Renta de 5ta Categoría del mes indicado.
     *
     * De MARZO a DICIEMBRE manda el Excel de RR.HH. ("Calculo 5ta.xlsx"): ver
     * renta5taComoElColegio(). Lo que sigue describe enero y febrero, que van con
     * el procedimiento de SUNAT (no un promedio simplificado ×12 fijo):
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

        // Por defecto, el procedimiento de SUNAT (Art. 40) con lo cobrado y lo
        // retenido de verdad cada mes: ver MotorRenta5ta. Lo de abajo es el
        // método de la hoja de RR.HH., que queda solo para comparar (Ajustes).
        if (! \App\Support\Renta5ta\MetodoRenta5ta::comoHojaDeRrhh()) {
            return (new \App\Support\Renta5ta\MotorRenta5ta())->retencionDelMes(
                $empleado, $mes, $anio, $sueldoBase, $bonificaciones > 0 ? $bonificaciones : null
            )['retencion'];
        }

        // De marzo a diciembre, como el Excel de RR.HH. (ver renta5taComoElColegio).
        // Enero y febrero siguen con la proyección de SUNAT: todavía no se sabe
        // cuánto va a ganar en el año, así que se proyecta con lo de ese mes.
        if ($mes >= 3) {
            // $bonificaciones, si viene, es la Bonificación por Cargo que RR.HH.
            // está escribiendo en la vista previa; si no, la de la planilla o la ficha.
            return $this->renta5taComoElColegio($empleado, $sueldoBase, $mes, $anio, $bonificaciones > 0 ? $bonificaciones : null);
        }

        $tramo = self::TRAMOS_RENTA_5TA[$mes] ?? self::TRAMOS_RENTA_5TA[1];

        // La Asignación Familiar es remunerativa y también afecta a Renta de 5ta Categoría.
        $remuneracionOrdinaria = $sueldoBase + $bonificaciones + $this->calcularAsignacionFamiliar($empleado, $anio);

        $gratificacionJulio     = $this->calcularGratificacion($empleado, $sueldoBase, 7, $anio);
        $gratificacionDiciembre = $this->calcularGratificacion($empleado, $sueldoBase, 12, $anio);
        $gratificacionesDelEjercicio = $gratificacionJulio['total'] + $gratificacionDiciembre['total'];

        $ingresosExtraordinarios = $this->ingresosExtraordinariosAcumulados($empleado->id, $anio, $mes);

        $multiplicador = $mes === 12 ? 12 : $tramo['multiplicador'];
        $ingresoAnual = ($remuneracionOrdinaria * $multiplicador)
            + $gratificacionesDelEjercicio
            + $ingresosExtraordinarios;

        // La UIT del año de la planilla: la de 2025 no es la de 2026.
        $uit    = $this->valoresLegales($anio)->uit;
        $exento = $uit * 7;
        if ($ingresoAnual <= $exento) {
            return 0.00;
        }

        $impuestoAnual = $this->aplicarTramosImpuestoRenta($ingresoAnual - $exento, $uit);
        $retencionesAcumuladas = $this->retencionesRenta5taAcumuladas($empleado->id, $anio, $tramo['corte']);

        if ($mes === 12) {
            return round(max(0, $impuestoAnual - $retencionesAcumuladas), 2);
        }

        return round(max(0, ($impuestoAnual - $retencionesAcumuladas) / $tramo['divisor']), 2);
    }

    /**
     * La Renta de 5ta como la calcula RR.HH. en su Excel ("Calculo 5ta.xlsx",
     * Hoja1), para las planillas de marzo a diciembre. Un solo cálculo del año
     * y el mismo monto todos los meses:
     *
     *   Ingreso mensual (I)   sueldo + asignación familiar + Bonificación por
     *                         Cargo del mes (lo que cobra cada mes).
     *   Enero y febrero       lo que de verdad cobró: sale de sus planillas de
     *                         esos meses, o si no las tiene, de lo cargado del
     *                         Excel (RentaQuintaPrevia, `renta5ta:cargar-previos`).
     *                         No se adivina; sin ninguna de las dos, es 0.
     *   Meses mar-dic (J)     los que trabaja de marzo (o desde que entró) a
     *                         diciembre (o hasta su cese). Casi siempre 10.
     *   Vacaciones truncas    I / 12 × J, solo a quien no tiene vacaciones
     *                         (contratados): se le pagan al terminar.
     *   Gratificaciones       I / 6 × meses del semestre, + la bonificación
     *                         extraordinaria (la tasa de EsSalud). Contratado
     *                         desde marzo: julio 4/6, diciembre 6/6.
     *   Otros ingresos        bonos y demás ya cargados de marzo a este mes.
     *
     *   Proyección − 7 UIT → tramos (8%, 14%, 17%...) = impuesto del año.
     *   Retención del mes = (impuesto − lo retenido en enero y febrero) ÷ J.
     */
    protected function renta5taComoElColegio($empleado, float $sueldoBase, int $mes, int $anio, ?float $bonificacionCargo = null): float
    {
        $ley = $this->valoresLegales($anio);
        [$desde, $hasta] = $this->mesesTrabajadosDelAnio($empleado, $anio, $mes);
        if ($mes < $desde || $mes > $hasta) {
            return 0.00;
        }

        $planillaDelMes = \App\Models\Planilla::where('empleado_id', $empleado->id)
            ->where('anio', $anio)->where('mes', $mes)->first();
        // La que se pidió; si no, la de la planilla del mes; sin planilla, la de su ficha.
        $bonificacionCargo ??= $planillaDelMes ? (float) $planillaDelMes->payrollDetalles()
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::BONIFICACION_CARGO))
            ->sum('monto_calculado') : (float) ($empleado->bonificacion_cargo ?? 0);

        $ingresoMensual = $sueldoBase + $this->calcularAsignacionFamiliar($empleado, $anio) + $bonificacionCargo;
        $mesesMarzoDiciembre = $hasta - max(3, $desde) + 1;

        // Enero y febrero: lo cobrado de verdad y lo ya retenido, de sus planillas.
        $eneroFebrero = \App\Models\Planilla::with('payrollDetalles.paymentConcept')
            ->where('empleado_id', $empleado->id)->where('anio', $anio)->whereIn('mes', [1, 2])->get();
        $cobradoEneroFebrero = $eneroFebrero->sum(fn ($p) => (float) $p->sueldo_base
            + $p->payrollDetalles->filter(fn ($d) => $d->paymentConcept?->tipo === 'bonificacion'
                && $d->paymentConcept->nombre !== \App\Support\ConceptosDePago::MOVILIDAD)->sum('monto_calculado'));
        $retenidoEneroFebrero = $eneroFebrero->sum(fn ($p) => $p->payrollDetalles
            ->filter(fn ($d) => $d->paymentConcept?->nombre === \App\Support\ConceptosDePago::RENTA_5TA)->sum('monto_calculado'));

        // El mes que no tiene planilla en el sistema (2026: se empezó a usar a
        // fin de año) se toma de lo cargado del Excel de RR.HH.
        $previos = \App\Models\RentaQuintaPrevia::where('empleado_id', $empleado->id)->where('anio', $anio)
            ->whereIn('mes', [1, 2])->whereNotIn('mes', $eneroFebrero->pluck('mes'))->get();
        $cobradoEneroFebrero  += $previos->sum('remuneracion');
        $retenidoEneroFebrero += $previos->sum('retencion');

        $conBonificacion = 1 + $ley->essalud / 100;
        $gratificaciones = 0.0;
        foreach ([7, 12] as $mesGratificacion) {
            $semestre = $this->calcularGratificacion($empleado, $sueldoBase, $mesGratificacion, $anio);
            $gratificaciones += $ingresoMensual / 6 * $semestre['meses_trabajados'] * $conBonificacion;
        }

        $vacacionesTruncas = $empleado->puedeTomarVacaciones() ? 0.0 : $ingresoMensual / 12 * $mesesMarzoDiciembre;

        $proyeccion = $ingresoMensual * $mesesMarzoDiciembre
            + $cobradoEneroFebrero
            + $vacacionesTruncas
            + $gratificaciones
            + $this->otrosIngresosDelAnio($empleado->id, $anio, $mes);

        $exento = $ley->uit * 7;
        if ($proyeccion <= $exento) {
            return 0.00;
        }

        $impuestoAnual = $this->aplicarTramosImpuestoRenta($proyeccion - $exento, $ley->uit);

        return round(max(0, ($impuestoAnual - $retenidoEneroFebrero) / $mesesMarzoDiciembre), self::DECIMALES);
    }

    /**
     * De qué mes a qué mes trabaja en ese año: desde enero (o el mes en que
     * entró, si fue ese año) hasta diciembre (o el mes de su cese, si es ese
     * año). Si entró después o cesó antes de ese año, el rango queda vacío.
     *
     * @return array{0:int,1:int}
     */
    protected function mesesTrabajadosDelAnio($empleado, int $anio, ?int $mes = null): array
    {
        $ingreso = $empleado->fecha_ingreso ? \Carbon\Carbon::parse($empleado->fecha_ingreso) : null;
        $cese    = $empleado->fecha_cese ? \Carbon\Carbon::parse($empleado->fecha_cese) : null;

        // A quien sigue ACTIVO, una fecha de fin que ya pasó no lo saca del
        // año: su contrato se renovó y la ficha no se actualizó (le pasaba a
        // Limachi Mamani: fin 13/04 y cobrando en setiembre). Se le proyecta
        // hasta diciembre, como hace el Excel. El que de verdad cesó está
        // "inactivo", y a ese sí se le corta en su mes.
        if ($cese && $empleado->estado !== 'inactivo' && $mes !== null
            && $cese->lt(\Carbon\Carbon::create($anio, $mes, 1))) {
            $cese = null;
        }

        $desde = match (true) {
            $ingreso === null || $ingreso->year < $anio => 1,
            $ingreso->year === $anio                    => $ingreso->month,
            default                                     => 13,
        };
        $hasta = match (true) {
            $cese === null || $cese->year > $anio => 12,
            $cese->year === $anio                 => $cese->month,
            default                               => 0,
        };

        return [$desde, $hasta];
    }

    /**
     * Las columnas "Aguinaldos y escolaridad" y "Otros" del Excel: lo que cobró
     * de marzo a este mes fuera de lo que ya entra por fórmula (sueldo,
     * asignación, bonificación por cargo, gratificaciones, vacaciones truncas).
     */
    private function otrosIngresosDelAnio($empleadoId, int $anio, int $mesHasta): float
    {
        return (float) \App\Models\PayrollDetalle::whereHas('planilla', fn ($q) => $q
                ->where('empleado_id', $empleadoId)->where('anio', $anio)
                ->whereBetween('mes', [3, $mesHasta]))
            ->whereHas('paymentConcept', fn ($q) => $q
                ->where('tipo', 'bonificacion')
                ->whereNotIn('nombre', [
                    \App\Support\ConceptosDePago::ASIGNACION_FAMILIAR,
                    \App\Support\ConceptosDePago::BONIFICACION_CARGO,
                    \App\Support\ConceptosDePago::GRATIFICACION,
                    \App\Support\ConceptosDePago::BONIF_EXTRAORDINARIA,
                    \App\Support\ConceptosDePago::VACACIONES_TRUNCAS,
                    \App\Support\ConceptosDePago::MOVILIDAD,
                ]))
            ->sum('monto_calculado');
    }

    /**
     * Tramos progresivos acumulativos vigentes (8/14/17/20/30% sobre 5/20/35/45 UIT).
     */
    private function aplicarTramosImpuestoRenta(float $rentaNeta, float $uit): float
    {
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
                        'descripcion'     => null,
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
     * El Diezmo entra en el punto 2) —10% del sueldo, aplica_a_todos=true—,
     * pero con una excepción: a quien tenga `aplica_diezmo = false` en su
     * ficha se le salta, igual que la Asignación Familiar sale de
     * `tiene_hijos`. Adelantos, Escolaridad, etc. sí siguen siendo manuales,
     * porque dependen de una autorización puntual sin regla fija detrás.
     */
    protected function generarConceptosAutomaticos($planilla, $empleado): void
    {
        $sueldoBase         = (float) $planilla->sueldo_base;
        // Todos los montos de ley de esta planilla salen de SU año.
        $anio               = (int) $planilla->anio;

        // La Bonificación por Cargo de su ficha, por los mismos días que el
        // sueldo. Va primero: suma a la base de pensión, EsSalud y Diezmo.
        // Con 0 en la ficha no se toca nada (pudo agregarse a mano ese mes).
        $bonificacionCargo = $this->bonificacionCargoDelMes($empleado, (int) $planilla->mes, $anio);
        if ($bonificacionCargo > 0) {
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::BONIFICACION_CARGO, $bonificacionCargo);
        }
        $ley                = $this->valoresLegales($anio);
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado, $anio);
        // + Bonificación por Cargo y Vacaciones Truncas si ya las tiene la
        // planilla (ver el comentario largo en otrosIngresosAfectosDeLaPlanilla).
        $baseAfecta = $sueldoBase + $asignacionFamiliar + $this->otrosIngresosAfectosDeLaPlanilla($planilla);

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
        } else {
            // Ya no le corresponde (se le quitó "Tiene hijos" en la ficha): la
            // línea que traía se borra. Antes se quedaba, y el neto seguía
            // pagándole los 113 aunque la pensión ya no los contaba.
            \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::ASIGNACION_FAMILIAR))
                ->delete();
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
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_FONDO, $baseAfecta * ($ley->aporte_afp / 100));

            // La prima del seguro: la misma para todas las AFP, salvo para
            // quien ya tiene 65 años (ver exentoDePrimaAfp). A ese se le quita
            // la línea si venía de antes, igual que la comisión en Mixta.
            if ($this->exentoDePrimaAfp($empleado, (int) $planilla->mes, $anio)) {
                \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                    ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::SPP_PRIMA_SEGURO))
                    ->delete();
            } else {
                $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_PRIMA_SEGURO, $baseAfecta * ($ley->prima_seguro_afp / 100));
            }

            // Y la comisión, que sí depende de cuál sea su AFP. Va con la tasa
            // escrita al lado porque es el dato que cambia de persona a
            // persona, y sin él la línea no se puede comprobar.
            //
            // Antes estos dos nombres iban cruzados; se enderezaron siguiendo
            // el PLAME del colegio. Ver el comentario largo en
            // calcularDescuentoPension() antes de tocarlo.
            //
            // "Mixta" no paga comisión en planilla —la AFP la cobra del
            // fondo acumulado—, igual que en calcularDescuentoPension().
            $comisionAfp = $empleado->tipo_comision_afp === 'mixta' ? 0 : $ley->comisionAfp($empleado->afp);
            if ($comisionAfp > 0) {
                $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::SPP_COMISION, $baseAfecta * ($comisionAfp / 100), "AFP {$empleado->afp} ({$comisionAfp}%)");
            } else {
                // A quien pasa a Mixta después de ya tener la línea creada
                // (o a quien cambia de AFP), se le quita: una comisión que
                // ya no corresponde no debe quedar de un mes anterior.
                \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                    ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', \App\Support\ConceptosDePago::SPP_COMISION))
                    ->delete();
            }
        } elseif ($empleado->sistema_pensiones === 'ONP') {
            $this->crearDetalleAutomatico($planilla, \App\Support\ConceptosDePago::ONP, $baseAfecta * ($ley->onp / 100));
        }
        // Sin sistema de pensiones no se crea ninguna línea: es el jubilado
        // que ya cobra su pensión o el extranjero con convenio. EsSalud sí se
        // le sigue aportando, que es cosa aparte.

        $this->crearDetalleAutomatico(
            $planilla,
            \App\Support\ConceptosDePago::ESSALUD,
            $this->calcularEssalud($baseAfecta, $anio, $this->proporcionDelMes($empleado, (int) $planilla->mes, $anio))
        );

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
            // El Diezmo es el único "aplica_a_todos" con un interruptor por
            // persona: vacío o "Sí" en su ficha sigue entrando en la planilla
            // junto con los demás, y solo "No" lo saca de este bloque.
            if ($concepto->nombre === \App\Support\ConceptosDePago::DIEZMO) {
                if (! $empleado->aplica_diezmo) {
                    // Al recalcular, a quien se le quitó el Diezmo en su ficha
                    // se le borra la línea que traía de antes.
                    \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                        ->where('payment_concept_id', $concepto->id)
                        ->delete();
                    continue;
                }

                // Va sobre $baseAfecta completa —sueldo + Asignación +
                // Bonificación por Cargo + Vacaciones Truncas—, igual que
                // el PLAME real: su fórmula es (Remuneración Bruta - CTS
                // - Gratificación - Movilidad), que es exactamente eso.
                $this->crearDetalleAutomatico($planilla, $concepto->nombre, $baseAfecta * ((float) $concepto->valor / 100));
                continue;
            }

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
                'monto_calculado' => round($monto, self::DECIMALES),
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

        // estado_registro = activo: un contrato eliminado desde la pantalla de
        // Contratos no toca 'estado' (solo se apaga estado_registro), y sin
        // este filtro la boleta le seguía imprimiendo la categoría y la fecha
        // de cese de un contrato que RR.HH. ya había quitado.
        $contratoVigente    = $empleado->contratos()->where('estado', 'vigente')->where('estado_registro', 'activo')->latest('fecha_inicio')->first();
        $contratoFinalizado = $empleado->contratos()->where('estado', 'finalizado')->where('estado_registro', 'activo')->latest('fecha_fin')->first();

        $categoria = $contratoVigente?->tipoContrato?->nombre ?? $empleado->tipoContrato?->nombre ?? '-';

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
     * Devuelve, entre otras cosas, la proporción con la que se prorratea el
     * sueldo:
     *
     *   dias_del_mes          los que tiene el mes de calendario (28, 30, 31)
     *   dias_habiles_del_mes  los que cuentan de verdad: solo lunes a viernes
     *   dias_pagados          los hábiles por los que le toca cobrar: si entró
     *                         el jueves 24, son los hábiles del 24 al fin de
     *                         mes, no el rango completo de días
     *   dias_vacaciones       los que estuvo de descanso, que TAMBIÉN se pagan
     *   dias_trabajados       los que realmente vino, que es lo que va en la boleta
     *
     * La proporción es dias_pagados / dias_habiles_del_mes —los DOS lados en
     * días hábiles, no uno en hábiles y el otro en calendario—: quien trabajó
     * el mes ENTERO tiene que seguir dando proporción 1.0 y cobrando su
     * sueldo completo. Mezclar hábiles arriba con calendario abajo le
     * recortaba el sueldo a TODO el mundo, todos los meses, en un 20-25%
     * —se detectó antes de entrar a producción, calculando a mano el caso de
     * alguien con el mes entero trabajado—.
     *
     * Decisión del colegio, no de la ley: el Perú paga el descanso semanal
     * obligatorio (D.Leg. 713) dentro del sueldo mensual fijo, así que lo
     * normal en un sistema de planilla es contar TODOS los días de
     * calendario, sábado y domingo incluidos. Acá se cuenta distinto porque
     * así se pidió expresamente (2026-09-28): si en algún momento se
     * cuestiona por qué la boleta de alguien que entró a mitad de semana no
     * le paga el fin de semana que quedó dentro del tramo, la respuesta está
     * en este comentario, no en un bug.
     *
     * El cese a mitad de mes se mide igual pero por el otro extremo: quien
     * cesó el día 12 cobra los hábiles hasta el 12. Solo para quien de verdad
     * cesó (estado inactivo): en alguien activo la fecha de cese puede ser el
     * fin programado de un contrato que luego se renovó, y recortarle el
     * sueldo por eso sería un error.
     */
    protected function repartoDeDiasDelMes($empleado, int $mes, int $anio): array
    {
        $inicioMes = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes    = \Carbon\Carbon::create($anio, $mes, 1)->endOfMonth();
        $diasDelMes = $inicioMes->daysInMonth;
        $diasHabilesDelMes = $this->diasHabilesEntre($inicioMes, $finMes);

        $ingreso = $empleado->fecha_ingreso
            ? \Carbon\Carbon::parse($empleado->fecha_ingreso)->startOfDay()
            : null;

        $cese = ($empleado->estado === 'inactivo' && $empleado->fecha_cese)
            ? \Carbon\Carbon::parse($empleado->fecha_cese)->startOfDay()
            : null;

        // Todavía no había entrado, o ya se había ido: no le corresponde nada
        // de este mes.
        if (($ingreso && $ingreso->gt($finMes)) || ($cese && $cese->lt($inicioMes))) {
            return [
                'dias_del_mes'         => $diasDelMes,
                'dias_habiles_del_mes' => $diasHabilesDelMes,
                'dias_pagados'         => 0,
                'dias_vacaciones'      => 0,
                'dias_trabajados'      => 0,
                'proporcion'           => 0.0,
                'entro_este_mes'       => false,
                'salio_este_mes'       => false,
            ];
        }

        // Desde cuándo cuenta: su fecha de ingreso si cae dentro del mes, o el
        // día 1 si ya estaba desde antes.
        $desde = ($ingreso && $ingreso->gt($inicioMes)) ? $ingreso : $inicioMes;
        // Hasta cuándo: su fecha de cese si cesó dentro del mes, o fin de mes.
        $hasta = ($cese && $cese->lt($finMes)) ? $cese : $finMes;
        $diasPagados = $this->diasHabilesEntre($desde, $hasta);

        $diasVacaciones = $this->diasDeVacacionesEnElMes($empleado->id, $desde, $hasta);

        return [
            'dias_del_mes'         => $diasDelMes,
            'dias_habiles_del_mes' => $diasHabilesDelMes,
            'dias_pagados'         => $diasPagados,
            'dias_vacaciones'      => $diasVacaciones,
            'dias_trabajados'      => max(0, $diasPagados - $diasVacaciones),
            'proporcion'           => $diasHabilesDelMes > 0 ? round($diasPagados / $diasHabilesDelMes, 6) : 0.0,
            'entro_este_mes'       => $ingreso && $ingreso->gt($inicioMes),
            'salio_este_mes'       => $cese && $cese->lt($finMes),
        ];
    }

    /** Cuántos lunes a viernes hay entre dos fechas, ambas incluidas. */
    private function diasHabilesEntre(\Carbon\Carbon $desde, \Carbon\Carbon $hasta): int
    {
        $dias   = 0;
        $cursor = $desde->copy()->startOfDay();
        $limite = $hasta->copy()->startOfDay();

        while ($cursor->lte($limite)) {
            if ($cursor->isWeekday()) {
                $dias++;
            }
            $cursor->addDay();
        }

        return $dias;
    }

    /**
     * El sueldo que le toca este mes, ya prorrateado si entró a mitad.
     *
     * Devuelve null cuando el trabajador todavía no había ingresado: eso no es
     * "cero soles", es que no hay planilla que armarle.
     */
    /** La Bonificación por Cargo de la ficha, por los días del mes que le toca (como el sueldo). */
    protected function bonificacionCargoDelMes($empleado, int $mes, int $anio): float
    {
        $monto = (float) ($empleado->bonificacion_cargo ?? 0);
        if ($monto <= 0) {
            return 0.0;
        }

        return round($monto * $this->repartoDeDiasDelMes($empleado, $mes, $anio)['proporcion'], 2);
    }

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