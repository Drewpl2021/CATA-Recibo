<?php

namespace App\Support;

use App\Models\ConceptoAlias;
use App\Models\PaymentConcept;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lee los títulos de las columnas de un Excel y dice qué es cada una.
 *
 * La regla que manda sobre todo lo demás: el reconocedor PROPONE, RR.HH.
 * confirma. Nunca elige entre dos conceptos parecidos: si "Bonificación" se
 * parece a tres del catálogo, lo dice y pregunta. Y nunca crea nada: marca la
 * columna como nueva y es la pantalla la que pide el tipo.
 *
 * Cómo decide, en este orden:
 *
 *   1. Sin título                     → se ignora.
 *   2. "DNI", "Nro. documento"...     → identifica al trabajador.
 *   3. "Detalle", "Glosa"...          → texto para la boleta del concepto de al lado.
 *   4. Un nombre ya confirmado antes  → reconocida (la tabla concepto_alias).
 *   5. Igual al nombre del catálogo   → reconocida.
 *   6. "Nombre", "Cargo", "Total"...  → de referencia, no se importa.
 *   7. Algo que calcula el sistema    → protegida (ONP, AFP, EsSalud, 5ta...).
 *   8. Parecido a UN solo concepto    → reconocida, avisando a cuál se parece.
 *   9. Parecido a varios              → dudosa: RR.HH. elige.
 *  10. Nada parecido                  → nueva: RR.HH. la crea, la asigna o la ignora.
 *
 * El parecido se mide sin inteligencia artificial, comparando palabras:
 * se limpian tildes y signos, se quitan las de relleno ("descuento",
 * "autorizado", "planilla de"), se pasa a singular y se mira cuántas
 * coinciden. Una distancia de edición cubre los errores de tipeo
 * ("Movilidd").
 */
final class ReconocedorDeColumnas
{
    public const SIN_TITULO  = 'sin_titulo';
    public const DNI         = 'dni';
    public const DETALLE     = 'detalle';
    public const RECONOCIDA  = 'reconocida';
    public const INFORMATIVA = 'informativa';
    public const PROTEGIDA   = 'protegida';
    public const DUDOSA      = 'dudosa';
    public const NUEVA       = 'nueva';

    /** Desde aquí un parecido se da por seguro... */
    private const SEGURO = 0.85;
    /** ...siempre que ningún otro concepto pase de aquí. */
    private const RIVAL = 0.75;
    /** Por debajo de esto ni se propone. */
    private const CANDIDATO = 0.6;

    /**
     * Palabras que no dicen qué concepto es. "Descuento Autorizado -
     * Tardanzas y Faltas" y "Tardanzas" tienen que acabar pareciéndose.
     */
    private const RELLENO = [
        'de', 'del', 'la', 'el', 'los', 'las', 'por', 'y', 'a', 'en', 'para', 'con', 'al',
        'descuento', 'descuentos', 'dscto', 'dsct', 'dcto', 'autorizado', 'autorizada',
        'planilla', 'serv', 'servicio', 'servicios', 'pago', 'ley', 'concepto', 'conceptos',
    ];

    /** Columnas que acompañan al Excel pero no son dinero que importar. */
    private const INFORMATIVAS = [
        'nombre', 'nombres', 'apellido', 'apellidos', 'apellidos y nombres', 'nombres y apellidos',
        'trabajador', 'empleado', 'colaborador', 'personal', 'cargo', 'area', 'sede', 'planilla',
        'item', 'n', 'no', 'nro', 'numero', 'correo', 'email', 'neto', 'neto a pagar', 'categoria',
        'regimen', 'sistema de pension', 'cuspp', 'banco', 'cuenta', 'cci',
    ];

    /** Palabras que delatan algo que calcula el sistema por cada trabajador. */
    private const PALABRAS_PROTEGIDAS = ['onp', 'afp', 'spp', 'essalud', 'salud', 'quinta', '5ta', 'renta', 'basico', 'basica'];

    /** Palabras que dicen de qué tipo es, para desempatar. */
    private const PISTAS_DE_TIPO = [
        'descuento' => 'descuento', 'descuentos' => 'descuento', 'dscto' => 'descuento',
        'dsct' => 'descuento', 'dcto' => 'descuento',
        'ingreso' => 'bonificacion', 'ingresos' => 'bonificacion',
        'adelanto' => 'adelanto', 'adelantos' => 'adelanto',
        'aporte' => 'aportacion', 'aportes' => 'aportacion', 'aportacion' => 'aportacion',
    ];

    /** @var Collection<int, array> */
    private Collection $catalogo;

    /** @var array<string, string> alias normalizado => payment_concept_id */
    private array $alias;

    public function __construct()
    {
        $this->catalogo = PaymentConcept::orderBy('nombre')->get()->map(fn (PaymentConcept $c) => [
            'id'        => $c->id,
            'nombre'    => $c->nombre,
            'tipo'      => $c->tipo,
            'protegido' => in_array($c->nombre, ConceptosDePago::NO_EDITABLES, true),
            'normales'  => array_values(array_unique(array_filter([
                self::normalizar($c->nombre),
                self::normalizar($c->etiqueta_boleta),
            ]))),
        ]);

        $this->alias = ConceptoAlias::pluck('payment_concept_id', 'alias')->all();
    }

    /**
     * "  Dscto. Tardanzas (S/)" → "dscto tardanzas s"
     *
     * Es la forma con que se compara todo, y con la que se guardan los alias.
     */
    public static function normalizar(?string $texto): string
    {
        $t = Str::lower(Str::ascii((string) $texto));
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /**
     * De 0 a 1, cuánto se parecen dos textos, con las mismas reglas que las
     * columnas: sin tildes, sin palabras de relleno y en singular. Lo usa
     * también la importación de empleados para sugerir "¿quisiste decir…?".
     */
    public static function similitud(string $a, string $b): float
    {
        $nucleoA = self::nucleo(self::normalizar($a));
        $nucleoB = self::nucleo(self::normalizar($b));

        return self::parecido($nucleoA, implode(' ', $nucleoA), $nucleoB, implode(' ', $nucleoB));
    }

    /** Los conceptos que sí se pueden importar, para los desplegables. */
    public function catalogoEditable(): array
    {
        $orden = ['bonificacion' => 1, 'descuento' => 2, 'adelanto' => 3, 'aportacion' => 4];

        return $this->catalogo
            ->reject(fn ($c) => $c['protegido'])
            ->sortBy(fn ($c) => sprintf('%d-%s', $orden[$c['tipo']] ?? 9, $c['nombre']))
            ->map(fn ($c) => ['id' => $c['id'], 'nombre' => $c['nombre'], 'tipo' => $c['tipo']])
            ->values()
            ->all();
    }

    /**
     * Qué es cada columna.
     *
     * @param  array<int, string|null> $titulos  en el orden del Excel
     * @return array<int, array>
     */
    public function reconocer(array $titulos): array
    {
        $columnas = [];
        $yaHayDni = false;

        foreach (array_values($titulos) as $indice => $titulo) {
            $columna = $this->reconocerUna((string) $titulo, $yaHayDni);
            $columna['indice'] = $indice;
            $columna['titulo'] = trim((string) $titulo);

            // Para uno nuevo se propone el título tal cual, sin espacios de más.
            if ($columna['estado'] === self::NUEVA) {
                $columna['nuevo']['nombre'] = mb_substr(preg_replace('/\s+/u', ' ', $columna['titulo']), 0, 150);
            }

            if ($columna['estado'] === self::DNI) {
                $yaHayDni = true;
            }

            $columnas[] = $columna;
        }

        // El detalle es de la columna de dinero que tiene a su izquierda.
        foreach ($columnas as $i => $columna) {
            if ($columna['estado'] !== self::DETALLE) {
                continue;
            }

            for ($j = $i - 1; $j >= 0; $j--) {
                if (in_array($columnas[$j]['accion'], ['usar', 'crear', 'elegir'], true)) {
                    $columnas[$i]['de_columna'] = $j;
                    $columnas[$i]['motivo'] = "Detalle para la boleta de «{$columnas[$j]['titulo']}».";
                    break;
                }
            }

            if ($columnas[$i]['de_columna'] === null) {
                $columnas[$i]['accion'] = 'ignorar';
                $columnas[$i]['motivo'] = 'Es un detalle, pero no tiene ninguna columna de montos a su izquierda.';
            }
        }

        return $columnas;
    }

    private function reconocerUna(string $titulo, bool $yaHayDni): array
    {
        $normal = self::normalizar($titulo);
        $base = [
            'estado' => null, 'accion' => 'ignorar', 'origen' => null,
            'payment_concept_id' => null, 'candidatos' => [], 'nuevo' => null,
            'de_columna' => null, 'motivo' => '',
        ];

        if ($normal === '') {
            return ['estado' => self::SIN_TITULO, 'motivo' => 'La columna no tiene título: se ignora.'] + $base;
        }

        if ($this->esDni($normal)) {
            return $yaHayDni
                ? ['estado' => self::INFORMATIVA, 'motivo' => 'Ya hay otra columna de DNI: esta se ignora.'] + $base
                : ['estado' => self::DNI, 'accion' => 'dni', 'motivo' => 'Identifica a cada trabajador.'] + $base;
        }

        $palabras = explode(' ', $normal);

        if (in_array($palabras[0], ['detalle', 'descripcion', 'glosa', 'motivo', 'observacion', 'observaciones'], true)) {
            return ['estado' => self::DETALLE, 'accion' => 'detalle'] + $base;
        }

        // Lo que RR.HH. ya confirmó en otra importación.
        if (isset($this->alias[$normal])) {
            $concepto = $this->catalogo->firstWhere('id', $this->alias[$normal]);

            if ($concepto) {
                return $concepto['protegido']
                    ? $this->protegida($base, $concepto)
                    : $this->reconocida($base, $concepto, 'recordado', 'Ya lo confirmaste en una importación anterior.');
            }
        }

        $exactos = $this->catalogo->filter(fn ($c) => in_array($normal, $c['normales'], true))->values();

        if ($exactos->count() === 1) {
            $concepto = $exactos->first();

            return $concepto['protegido']
                ? $this->protegida($base, $concepto)
                : $this->reconocida($base, $concepto, 'exacto', 'Coincide con el nombre del catálogo.');
        }

        if (in_array($normal, self::INFORMATIVAS, true) || in_array($palabras[0], ['total', 'neto', 'sub'], true)) {
            return ['estado' => self::INFORMATIVA, 'motivo' => 'Es solo de referencia: no se importa.'] + $base;
        }

        if (array_intersect($palabras, self::PALABRAS_PROTEGIDAS)) {
            return ['estado' => self::PROTEGIDA, 'motivo' => 'Lo calcula el sistema para cada trabajador: no se puede importar.'] + $base;
        }

        return $this->porParecido($base, $normal, $palabras, $exactos);
    }

    private function porParecido(array $base, string $normal, array $palabras, Collection $exactos): array
    {
        $nucleo = self::nucleo($normal);
        $unido  = implode(' ', $nucleo);

        $puntuados = $this->catalogo->map(function ($c) use ($nucleo, $unido) {
            $mejor = 0.0;
            foreach ($c['normales'] as $normalConcepto) {
                $nucleoConcepto = self::nucleo($normalConcepto);
                $mejor = max($mejor, self::parecido($nucleo, $unido, $nucleoConcepto, implode(' ', $nucleoConcepto)));
            }

            return $c + ['puntaje' => round($mejor, 2)];
        });

        // Algo que calcula el sistema, aunque esté escrito distinto.
        $protegidoMasParecido = $puntuados->where('protegido', true)->sortByDesc('puntaje')->first();
        $editables = $puntuados->where('protegido', false);

        if ($protegidoMasParecido && $protegidoMasParecido['puntaje'] >= self::SEGURO
            && $protegidoMasParecido['puntaje'] >= ($editables->max('puntaje') ?? 0)) {
            return $this->protegida($base, $protegidoMasParecido);
        }

        // Dos conceptos con el mismo nombre en la boleta ("Otros Conceptos"):
        // entran como candidatos con puntaje completo.
        $candidatos = $editables
            ->map(fn ($c) => $exactos->contains('id', $c['id']) ? ['puntaje' => 1.0] + $c : $c)
            ->filter(fn ($c) => $c['puntaje'] >= self::CANDIDATO);

        // "Otros descuentos" se parece a los dos "Otros": la palabra
        // descuento decide cuál.
        $pista = collect($palabras)->map(fn ($p) => self::PISTAS_DE_TIPO[$p] ?? null)->filter()->first();
        if ($pista && $candidatos->where('tipo', $pista)->isNotEmpty()) {
            $candidatos = $candidatos->where('tipo', $pista);
        }

        $candidatos = $candidatos->sortByDesc('puntaje')->values();
        $primero = $candidatos->get(0);
        $segundo = $candidatos->get(1);

        if ($primero && $primero['puntaje'] >= self::SEGURO && (! $segundo || $segundo['puntaje'] < self::RIVAL)) {
            return $this->reconocida($base, $primero, 'parecido', "Se parece a «{$primero['nombre']}».");
        }

        if ($candidatos->isNotEmpty()) {
            return [
                'estado'     => self::DUDOSA,
                'accion'     => 'elegir',
                'candidatos' => $candidatos->take(5)->map(fn ($c) => [
                    'id' => $c['id'], 'nombre' => $c['nombre'], 'tipo' => $c['tipo'], 'puntaje' => $c['puntaje'],
                ])->all(),
                'motivo'     => $candidatos->count() > 1
                    ? 'Se parece a varios conceptos: elige cuál es.'
                    : "Podría ser «{$primero['nombre']}», pero no es seguro: confírmalo.",
            ] + $base;
        }

        return [
            'estado' => self::NUEVA,
            'accion' => 'crear',
            'nuevo'  => [
                'nombre' => null, // lo pone reconocer(), que es quien tiene el título
                'tipo'   => $pista,
            ],
            'motivo' => 'No hay ningún concepto parecido en el catálogo.',
        ] + $base;
    }

    private function reconocida(array $base, array $concepto, string $origen, string $motivo): array
    {
        return [
            'estado'             => self::RECONOCIDA,
            'accion'             => 'usar',
            'origen'             => $origen,
            'payment_concept_id' => $concepto['id'],
            'motivo'             => $motivo,
        ] + $base;
    }

    private function protegida(array $base, array $concepto): array
    {
        return [
            'estado' => self::PROTEGIDA,
            'motivo' => "«{$concepto['nombre']}» lo calcula el sistema para cada trabajador: no se puede importar.",
        ] + $base;
    }

    private function esDni(string $normal): bool
    {
        $palabras = explode(' ', $normal);

        return in_array('dni', $palabras, true)
            || $normal === 'doc'
            || (bool) preg_match('/^((n|no|nro|num|numero) (de )?)?documento( de identidad)?$/', $normal);
    }

    /** Las palabras que de verdad dicen qué concepto es, en singular. */
    private static function nucleo(string $normal): array
    {
        $palabras = [];

        foreach (explode(' ', $normal) as $p) {
            if ($p === '' || ctype_digit($p) || in_array($p, self::RELLENO, true)) {
                continue;
            }
            $palabras[] = self::singular($p);
        }

        return array_values(array_unique($palabras));
    }

    /** "bonificaciones" → "bonificacion", "tardanzas" → "tardanza". */
    private static function singular(string $p): string
    {
        if (strlen($p) > 5 && str_ends_with($p, 'ones')) {
            return substr($p, 0, -2);
        }

        return strlen($p) > 3 && str_ends_with($p, 's') ? substr($p, 0, -1) : $p;
    }

    /**
     * De 0 a 1, cuánto se parece un título a un concepto.
     *
     * Si TODAS las palabras del título están en el concepto, es muy parecido
     * ("Tardanza" dentro de "Tardanzas y Faltas"). Si solo algunas, algo. Y
     * la distancia de edición de las frases enteras salva los errores de
     * tipeo. Gana el mayor de los dos.
     */
    private static function parecido(array $titulo, string $tituloUnido, array $concepto, string $conceptoUnido): float
    {
        if (! $titulo || ! $concepto) {
            return 0.0;
        }

        $coinciden = count(array_filter($titulo, fn ($t) => self::algunaIgual($t, $concepto)));
        $cubiertas = count(array_filter($concepto, fn ($c) => self::algunaIgual($c, $titulo)));

        $porPalabras = 0.0;
        if ($coinciden === count($titulo)) {
            $porPalabras = 0.85 + 0.15 * ($cubiertas / count($concepto));
        } elseif ($coinciden > 0) {
            $porPalabras = 0.5 + 0.35 * ($coinciden / count($titulo));
        }

        $porEdicion = 0.0;
        $largo = max(strlen($tituloUnido), strlen($conceptoUnido));
        if ($largo > 0 && $largo <= 255) {
            $porEdicion = 1 - levenshtein($tituloUnido, $conceptoUnido) / $largo;
        }

        return max($porPalabras, $porEdicion);
    }

    private static function algunaIgual(string $palabra, array $otras): bool
    {
        foreach ($otras as $otra) {
            if (self::mismaPalabra($palabra, $otra)) {
                return true;
            }
        }

        return false;
    }

    /** Igual, abreviada ("bonif" de "bonificacion") o con una letra mal. */
    private static function mismaPalabra(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $corta = strlen($a) <= strlen($b) ? $a : $b;
        $larga = $corta === $a ? $b : $a;

        if (strlen($corta) >= 4 && str_starts_with($larga, $corta)) {
            return true;
        }

        return strlen($corta) >= 6 && levenshtein($a, $b) <= 1;
    }
}
