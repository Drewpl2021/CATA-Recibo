<?php

namespace App\Support;

/**
 * Los nombres de los conceptos que el código conoce, en UN solo sitio.
 *
 * El motor de cálculo busca los conceptos por nombre exacto
 * (`PaymentConcept::where('nombre', ...)`), y cuando no lo encuentra **no
 * falla: no hace nada**. Una letra de diferencia entre el seeder y el código
 * y la planilla sale sin su descuento de pensión, sin que nadie se entere
 * hasta que el trabajador cobra de más.
 *
 * Con los nombres escritos a mano en ocho archivos, renombrar uno era
 * exactamente eso. Acá están una vez: el seeder los siembra desde aquí y el
 * motor los busca desde aquí, así que no pueden separarse.
 *
 * Solo están los que el CÓDIGO necesita nombrar. Los demás conceptos del
 * catálogo (alimentación, diezmo, escolaridad...) son datos puros: se
 * agregan y se renombran desde la pantalla sin tocar nada de esto.
 */
final class ConceptosDePago
{
    // ── Los que se calculan solos, por empleado ───────────────────
    public const ONP                 = 'ONP 13%';
    public const SPP_FONDO           = 'SPP. Fondo Pensiones';
    public const SPP_PRIMA_SEGURO    = 'SPP. Prima de Seguro';
    public const SPP_COMISION        = 'SPP. Comisión';
    public const RENTA_5TA           = 'I.R. 5ta Categoría';
    public const ESSALUD             = 'ESSALUD 9%';
    /**
     * Se le crea la línea a quien tiene `tiene_hijos = 1` en su ficha, y a
     * nadie más. El código necesita nombrarla porque el monto no sale del
     * catálogo sino de esa casilla, igual que EsSalud sale de su base.
     */
    public const ASIGNACION_FAMILIAR = 'Asignación Familiar';

    /** El sueldo del mes: sale de la ficha del trabajador, nunca es una línea. */
    public const REMUNERACION_BASICA = 'Remuneración Básica';

    // ── Las dos bolsas genéricas ──────────────────────────────────
    public const OTROS_INGRESOS      = 'Otros Conceptos (Ingresos)';
    public const OTROS_DESCUENTOS    = 'Otros Conceptos (Descuentos)';

    /**
     * Los seis que NUNCA pasan por el motor genérico ni por "aplicar a
     * grupo": dependen de la ficha de cada quien —su sistema de pensión, su
     * AFP concreta, su asignación familiar, su historial del año— y una
     * fórmula plana les pisaría el valor correcto con uno malo.
     */
    public const CALCULO_ESPECIAL = [
        self::ONP,
        self::SPP_FONDO,
        self::SPP_PRIMA_SEGURO,
        self::SPP_COMISION,
        self::ESSALUD,
        self::RENTA_5TA,
    ];

    /**
     * Los que la boleta imprime en su propia fila, fuera del listado de
     * conceptos: la pensión va arriba del bloque de Descuentos con su
     * desglose, y la Renta de 5ta al final. Si además salieran en el
     * listado, aparecerían dos veces.
     */
    public const MOSTRADOS_APARTE = [
        self::ONP,
        self::SPP_FONDO,
        self::SPP_PRIMA_SEGURO,
        self::SPP_COMISION,
        self::RENTA_5TA,
    ];

    /**
     * Los que nadie escribe a mano: ni desde una pantalla ni desde un Excel.
     *
     * Los seis de cálculo especial, más la Asignación Familiar (sale de
     * "tiene hijos") y la Remuneración Básica (sale de la ficha). La
     * importación masiva los rechaza aunque una columna se llame igual.
     */
    public const NO_EDITABLES = [
        ...self::CALCULO_ESPECIAL,
        self::ASIGNACION_FAMILIAR,
        self::REMUNERACION_BASICA,
    ];
}
