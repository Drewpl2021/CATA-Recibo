<?php

namespace App\Support\Plame;

use App\Models\ConceptoAlias;
use App\Models\PaymentConcept;
use App\Support\ConceptosDePago;
use App\Support\LectorDeCeldas;
use App\Support\ReconocedorDeColumnas;

/**
 * Qué es cada columna del PLAME.
 *
 * A diferencia de «Importar conceptos», aquí el vocabulario es conocido: es
 * la hoja PLANILLA de RR.HH., con las mismas columnas todos los meses. Se
 * reconocen solas y RR.HH. solo decide las que no son de siempre (el curso,
 * las corbatas). Comprobado contra el PLAME de marzo de 2026:
 *
 *   TOTAL REMUNERACIÓN BRUTA = de SUELDO BÁSICO a PLANILLA DE MOVILIDAD
 *   TOTAL DESCUENTO          = ONP + AFP (aporte, comisión, prima) + 5ta
 *                              + de Diezmo a ADELANTO
 *   REMUNERACIÓN NETA        = bruta − descuentos
 *
 * El segundo «Aporte Obligatorio» (después de la 5ta) es el total de la
 * pensión: un subtotal, no un descuento más. Si se sumara, el neto bajaría
 * dos veces.
 *
 * Una columna que no es de siempre va por defecto a «Otros Conceptos»,
 * según dónde esté: antes de la bruta es un ingreso; entre la bruta y el
 * total de descuentos, un descuento. Es lo que RR.HH. hizo a mano en
 * setiembre con el curso de IA y las corbatas: una sola línea con el detalle
 * «Curso IA (S/ 60.00) y Corbatas y polos (S/ 12.00)».
 */
final class ColumnasDelPlame
{
    public const OTROS_INGRESOS   = 'otros_ingresos';
    public const OTROS_DESCUENTOS = 'otros_descuentos';
    public const IGNORAR          = 'ignorar';

    /**
     * Título normalizado → qué es. Gana la frase MÁS LARGA que aparezca en
     * el título: «bonificacion por cargo» le gana a «cargo», y «adelanto de
     * bonificacion» a «adelanto». Cada frase acepta su plural.
     *
     * rol: dato (de la ficha), informativa, sueldo, concepto, total.
     */
    private const VOCABULARIO = [
        ['apellidos y nombres', 'dato', 'nombre'],
        ['apellidos nombres', 'dato', 'nombre'],
        ['dni', 'dato', 'dni'],
        ['cargo', 'dato', 'cargo'],
        ['ocupacion', 'dato', 'cargo'],
        ['centro de costo', 'dato', 'centro_costos'],
        ['categoria', 'dato', 'categoria'],
        ['fecha ingreso', 'dato', 'fecha_ingreso'],
        ['fecha cese', 'dato', 'fecha_cese'],
        ['codigo cussp', 'dato', 'cuspp'],
        ['codigo cuspp', 'dato', 'cuspp'],
        ['cussp', 'dato', 'cuspp'],
        ['cuspp', 'dato', 'cuspp'],
        ['tipo comision', 'dato', 'tipo_comision'],
        ['numero de cuenta', 'dato', 'cuenta'],
        ['n de cuenta', 'dato', 'cuenta'],
        ['detalle', 'dato', 'detalle'],
        ['observacion', 'dato', 'detalle'],
        ['glosa', 'dato', 'detalle'],
        ['orden', 'informativa', 'orden'],
        ['codigo', 'informativa', 'codigo'],
        ['dias trabajados', 'informativa', 'dias'],
        ['dias no laborados', 'informativa', 'dias'],
        ['dias laborados', 'informativa', 'dias'],
        ['total remuneracion bruta', 'total', 'bruto'],
        ['remuneracion bruta', 'total', 'bruto'],
        ['total ingresos', 'total', 'bruto'],
        ['total descuento', 'total', 'descuentos'],
        ['remuneracion neta', 'total', 'neto'],
        ['neto a pagar', 'total', 'neto'],
        ['total aportes', 'total', 'aportes'],
        ['sueldo basico', 'sueldo', null],
        ['remuneracion basica', 'sueldo', null],
        ['haber basico', 'sueldo', null],
        ['bonificacion por funcion', 'concepto', ConceptosDePago::BONIFICACION_CARGO],
        ['bonificacion por cargo', 'concepto', ConceptosDePago::BONIFICACION_CARGO],
        ['vacaciones truncas', 'concepto', ConceptosDePago::VACACIONES_TRUNCAS],
        ['bonificacion extraordinaria', 'concepto', ConceptosDePago::BONIF_EXTRAORDINARIA],
        // Errata del PLAME de julio y agosto de 2026: «BOFINICACIÓN EXTRAORDINARIA».
        ['bofinicacion extraordinaria', 'concepto', ConceptosDePago::BONIF_EXTRAORDINARIA],
        ['bonif extraord', 'concepto', ConceptosDePago::BONIF_EXTRAORDINARIA],
        ['gratificacion', 'concepto', ConceptosDePago::GRATIFICACION],
        ['compensacion por tiempo de servicios', 'concepto', 'Compensación por Tiempo de Servicios'],
        ['movilidad', 'concepto', ConceptosDePago::MOVILIDAD],
        ['comision', 'concepto', ConceptosDePago::SPP_COMISION],
        ['prima de seguro', 'concepto', ConceptosDePago::SPP_PRIMA_SEGURO],
        ['prima seguro', 'concepto', ConceptosDePago::SPP_PRIMA_SEGURO],
        ['5ta categoria', 'concepto', ConceptosDePago::RENTA_5TA],
        ['quinta categoria', 'concepto', ConceptosDePago::RENTA_5TA],
        ['diezmo', 'concepto', ConceptosDePago::DIEZMO],
        ['ensenanza', 'concepto', 'Descuento - Pago de Escolaridad Mensual'],
        ['escolaridad', 'concepto', 'Descuento - Pago de Escolaridad Mensual'],
        ['comedor', 'concepto', 'Descuento Serv. Alimentación'],
        ['alimentacion', 'concepto', 'Descuento Serv. Alimentación'],
        ['copias', 'concepto', 'Descuento Serv. Copias'],
        ['bazar', 'concepto', 'Descuento Serv. Bazar'],
        ['tardanza', 'concepto', 'Descuento Autorizado - Tardanzas y Faltas'],
        // Cada adelanto con su frase: la genérica «adelanto» los juntaba todos
        // en «Adelanto de Sueldo» (mayo traía el de sueldo Y el de CTS), y
        // «adelanto de gratificaciones» caía en «gratificacion», que es más larga.
        ['adelanto de bonificacion', 'concepto', 'Adelanto de Bonificaciones'],
        // Errata del PLAME de junio de 2026: «ADELANTO DE BONOFICACIÓN».
        ['adelanto de bonoficacion', 'concepto', 'Adelanto de Bonificaciones'],
        ['adelanto de gratificacion', 'concepto', ConceptosDePago::ADELANTO_GRATIFICACION],
        ['adelanto de cts', 'concepto', ConceptosDePago::ADELANTO_CTS],
        ['adelanto de sueldo', 'concepto', 'Adelanto de Sueldo'],
        ['adelanto', 'concepto', 'Adelanto de Sueldo'],
        // Una bonificación sin apellido (junio: «BONOFICACIÓN», con su errata).
        ['bonificacion', 'concepto', 'Bonificaciones'],
        ['bonoficacion', 'concepto', 'Bonificaciones'],
        ['essalud', 'concepto', ConceptosDePago::ESSALUD],
        ['sctr', 'concepto', 'SCTR'],
    ];

    /** Los títulos que valen enteros (una palabra suelta dentro de otro título no). */
    private const EXACTOS = [
        'cts' => ['concepto', 'Compensación por Tiempo de Servicios'],
        'afp' => ['dato', 'afp'],
        'vac' => ['informativa', 'dias'],
        'n'   => ['informativa', 'orden'],
        'item' => ['informativa', 'orden'],
    ];

    /**
     * Qué es cada columna.
     *
     * @param  list<array{indice:int, letra:string, titulo:string, grupo:string}> $columnas
     * @param  list<array{numero:int, celdas:list<mixed>}> $filas  para mirar el contenido
     * @param  array<int, string> $decisiones  indice => ignorar | otros_ingresos | otros_descuentos | id de concepto
     * @return list<array>
     */
    public static function reconocer(array $columnas, array $filas, array $decisiones = []): array
    {
        $catalogo = PaymentConcept::get(['id', 'nombre', 'tipo'])->keyBy(fn ($c) => ReconocedorDeColumnas::normalizar($c->nombre));
        $porNombre = $catalogo->keyBy('nombre');
        $alias = ConceptoAlias::pluck('payment_concept_id', 'alias');
        $porId = $catalogo->keyBy('id');

        $salida = [];
        $aportesObligatorios = 0;
        $zona = 'ingresos';

        foreach ($columnas as $columna) {
            $indice = $columna['indice'];
            $normal = ReconocedorDeColumnas::normalizar($columna['titulo']);
            $base = $columna + [
                'rol' => self::IGNORAR, 'clave' => null, 'concepto' => null, 'concepto_id' => null, 'tipo' => null,
                'estado' => 'informativa', 'motivo' => '', 'editable' => false, 'ejemplo' => self::ejemplo($filas, $indice),
            ];

            if ($normal === '') {
                if (self::tieneDatos($filas, $indice)) {
                    $salida[] = self::comoOtros($base, $zona, $decisiones, $porId, $porNombre, 'La columna no tiene título.');
                } else {
                    $salida[] = $base + ['motivo' => 'Columna vacía.'];
                }
                continue;
            }

            [$rol, $clave] = self::queEs($normal);

            // Dos que se llaman igual y se distinguen por lo que traen.
            if ($normal === 'asignacion familiar') {
                [$rol, $clave] = self::esSiNo($filas, $indice) ? ['dato', 'tiene_hijos'] : ['concepto', ConceptosDePago::ASIGNACION_FAMILIAR];
            } elseif (in_array($normal, ['snp onp', 'onp', 'snp'], true)) {
                [$rol, $clave] = self::esSiNo($filas, $indice) ? ['dato', 'aporta_onp'] : ['concepto', ConceptosDePago::ONP];
            } elseif ($normal === 'aporte obligatorio') {
                // El primero es el aporte al fondo; el segundo, el total de la pensión.
                [$rol, $clave] = ++$aportesObligatorios === 1 ? ['concepto', ConceptosDePago::SPP_FONDO] : ['total', 'pension'];
            }

            if ($rol === 'total') {
                if ($clave === 'bruto') {
                    $zona = 'descuentos';
                } elseif ($clave === 'descuentos' || $clave === 'neto') {
                    $zona = 'despues';
                }
                $salida[] = array_merge($base, [
                    'rol' => 'total', 'clave' => $clave, 'estado' => 'total',
                    'motivo' => $clave === 'pension'
                        ? 'Es el total de la pensión (ONP o AFP): sirve para revisar, no se descuenta otra vez.'
                        : 'Total del PLAME: con él se comprueba que cada fila cuadre.',
                ]);
                continue;
            }

            if ($rol === 'dato' || $rol === 'informativa') {
                $salida[] = array_merge($base, [
                    'rol' => $rol, 'clave' => $clave, 'estado' => $rol === 'dato' ? 'dato' : 'informativa',
                    'motivo' => $rol === 'dato' ? 'Dato del trabajador.' : 'De referencia: no se importa.',
                ]);
                continue;
            }

            if ($rol === 'sueldo') {
                $salida[] = array_merge($base, [
                    'rol' => 'sueldo', 'estado' => 'reconocida', 'concepto' => ConceptosDePago::REMUNERACION_BASICA, 'tipo' => 'bonificacion',
                    'motivo' => 'El sueldo del mes, tal como se pagó.',
                ]);
                continue;
            }

            if ($rol === 'concepto') {
                $concepto = $porNombre->get($clave);
                if (! $concepto) {
                    // Nunca a «Otros» callado: la ONP o la 5ta ahí quedarían
                    // sin su nombre en la boleta y en la declaración.
                    $salida[] = array_merge($base, [
                        'rol' => 'falta', 'concepto' => $clave, 'estado' => 'falta',
                        'motivo' => "Es «{$clave}», pero ese concepto no está en el catálogo. Créalo en Conceptos de pago con ese nombre.",
                    ]);
                    continue;
                }
                $salida[] = array_merge($base, [
                    'rol' => 'concepto', 'estado' => 'reconocida', 'concepto' => $concepto->nombre,
                    'concepto_id' => $concepto->id, 'tipo' => $concepto->tipo,
                    'motivo' => "Va como «{$concepto->nombre}».",
                ]);
                continue;
            }

            // Lo que no es de siempre: un concepto del catálogo con ese
            // nombre (o un nombre que RR.HH. ya confirmó), o «Otros».
            if (! self::tieneDatos($filas, $indice) || $zona === 'despues') {
                $salida[] = self::comoOtros($base, $zona, $decisiones, $porId, $porNombre, $zona === 'despues'
                    ? 'Está después del neto: no se importa salvo que elijas qué es.'
                    : 'No tiene montos este mes.');
                continue;
            }

            $conocido = $catalogo->get($normal) ?? (($id = $alias->get($normal)) ? $porId->get($id) : null);
            if ($conocido && ! in_array($conocido->nombre, ConceptosDePago::NO_EDITABLES, true) && ! isset($decisiones[$indice])) {
                $salida[] = array_merge($base, [
                    'rol' => 'concepto', 'estado' => 'reconocida', 'concepto' => $conocido->nombre,
                    'concepto_id' => $conocido->id, 'tipo' => $conocido->tipo, 'editable' => true,
                    'motivo' => "Va como «{$conocido->nombre}».",
                ]);
                continue;
            }

            $salida[] = self::comoOtros($base, $zona, $decisiones, $porId, $porNombre, 'No es una columna de siempre.');
        }

        return $salida;
    }

    /**
     * Una columna que RR.HH. decide: por defecto, a «Otros Conceptos» de
     * ingresos o descuentos según dónde esté.
     */
    private static function comoOtros(array $base, string $zona, array $decisiones, $porId, $porNombre, string $motivo): array
    {
        $indice = $base['indice'];
        $porDefecto = match ($zona) {
            'ingresos'   => self::OTROS_INGRESOS,
            'descuentos' => self::OTROS_DESCUENTOS,
            default      => self::IGNORAR,
        };
        if (! self::tieneDatosEnBase($base)) {
            $porDefecto = self::IGNORAR;
        }
        $decision = $decisiones[$indice] ?? $porDefecto;
        $base['editable'] = true;

        if ($decision === self::IGNORAR) {
            return array_merge($base, ['rol' => self::IGNORAR, 'estado' => 'ignorada', 'motivo' => $motivo . ' No se importa.']);
        }

        if ($decision === self::OTROS_INGRESOS || $decision === self::OTROS_DESCUENTOS) {
            $nombre = $decision === self::OTROS_INGRESOS ? ConceptosDePago::OTROS_INGRESOS : ConceptosDePago::OTROS_DESCUENTOS;
            $concepto = $porNombre->get($nombre);

            return array_merge($base, [
                'rol' => 'otros', 'clave' => $decision, 'estado' => 'otros', 'concepto' => $nombre,
                'concepto_id' => $concepto?->id, 'tipo' => $decision === self::OTROS_INGRESOS ? 'bonificacion' : 'descuento',
                'motivo' => $motivo . ' Se junta en «' . $nombre . '», con su nombre en el detalle.',
            ]);
        }

        $concepto = $porId->get($decision);
        if (! $concepto || in_array($concepto->nombre, ConceptosDePago::NO_EDITABLES, true)) {
            return array_merge($base, ['rol' => self::IGNORAR, 'estado' => 'ignorada', 'motivo' => 'El concepto elegido no se puede importar: elige otro.']);
        }

        return array_merge($base, [
            'rol' => 'concepto', 'estado' => 'reconocida', 'concepto' => $concepto->nombre,
            'concepto_id' => $concepto->id, 'tipo' => $concepto->tipo,
            'motivo' => "Va como «{$concepto->nombre}» (lo elegiste tú).",
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function queEs(string $normal): array
    {
        if (isset(self::EXACTOS[$normal])) {
            return self::EXACTOS[$normal];
        }

        $mejor = [null, null];
        $largo = 0;
        foreach (self::VOCABULARIO as [$frase, $rol, $clave]) {
            if (strlen($frase) > $largo && preg_match('/(^| )' . preg_quote($frase, '/') . '(e?s)?( |$)/', $normal)) {
                [$mejor, $largo] = [[$rol, $clave], strlen($frase)];
            }
        }

        return $mejor;
    }

    /**
     * «DSCTO CURSO IA» → «Curso IA», para el detalle de «Otros Conceptos».
     * Se quita la palabra de descuento del principio y queda en minúsculas,
     * menos las siglas cortas que venían en mayúsculas (IA, TIC).
     */
    public static function etiqueta(string $titulo): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $titulo));
        $t = preg_replace('/^(dscto|dsct|desc|dcto|descuento|descuentos|otros?)\.?\s+(de\s+|por\s+)?/iu', '', $t);
        $palabras = explode(' ', $t);
        foreach ($palabras as $i => $p) {
            $siglas = mb_strlen($p) <= 3 && mb_strtoupper($p) === $p && ! in_array(mb_strtolower($p), ['y', 'de', 'del', 'la', 'el', 'los', 'las', 'por', 'e'], true);
            $palabras[$i] = $siglas ? $p : mb_strtolower($p);
        }
        $t = implode(' ', $palabras);

        return $t === '' ? 'Otros' : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    private static function esSiNo(array $filas, int $indice): bool
    {
        $siNo = 0;
        $otros = 0;
        foreach ($filas as $fila) {
            $v = $fila['celdas'][$indice] ?? null;
            if (LectorDeCeldas::vacia($v)) {
                continue;
            }
            is_string($v) && LectorDeCeldas::siNo($v) !== null ? $siNo++ : $otros++;
        }

        return $siNo > 0 && $siNo >= $otros;
    }

    private static function tieneDatos(array $filas, int $indice): bool
    {
        foreach ($filas as $fila) {
            $v = $fila['celdas'][$indice] ?? null;
            if (! LectorDeCeldas::vacia($v) && (LectorDeCeldas::monto($v) ?? 0) != 0) {
                return true;
            }
        }

        return false;
    }

    private static function tieneDatosEnBase(array $base): bool
    {
        return ($base['ejemplo'] ?? '') !== '';
    }

    /** El primer valor de la columna, para reconocerla de un vistazo. */
    private static function ejemplo(array $filas, int $indice): string
    {
        foreach ($filas as $fila) {
            $v = $fila['celdas'][$indice] ?? null;
            if (! LectorDeCeldas::vacia($v)) {
                $t = is_float($v) ? number_format($v, 2, '.', '') : (string) $v;

                return mb_strlen($t) > 30 ? mb_substr($t, 0, 29) . '…' : $t;
            }
        }

        return '';
    }
}
