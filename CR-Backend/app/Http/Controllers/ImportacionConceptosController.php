<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\ConceptoAlias;
use App\Models\Empleado;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Support\LectorDeCeldas;
use App\Support\Meses;
use App\Support\ModelosDeImportacion;
use App\Support\ReconocedorDeColumnas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Importar conceptos de pago desde un Excel: todas las planillas del mes de
 * una sola vez, en vez de entrar a cada una.
 *
 * El archivo NO llega al servidor. Lo lee el navegador y manda solo los
 * títulos y las celdas: un Excel de planilla lleva el sueldo de todo el
 * personal, y lo que no se sube no se puede filtrar.
 *
 * Tres pasos, y el último no confía en los anteriores:
 *
 *   reconocer       qué concepto es cada columna (propone, no decide)
 *   previsualizar   qué cambiaría en cada planilla, sin tocar nada
 *   aplicar         vuelve a revisarlo TODO y guarda todo o nada
 *
 * Cómo se leen las celdas:
 *
 *   vacía      no se toca lo que ya tenga esa persona
 *   0          se le quita el concepto
 *   un monto   se le pone ese monto (fijo)
 *
 * Así, si alguien borra una columna por error, no le quita un concepto a
 * todo el colegio.
 */
class ImportacionConceptosController extends Controller
{
    private const MAX_FILAS    = 3000;
    private const MAX_COLUMNAS = 80;
    private const MONTO_MAXIMO = 999999.99;
    private const TIPOS        = ['bonificacion', 'descuento', 'adelanto', 'aportacion'];

    /** Cómo mueve el neto cada tipo: lo mismo que Planilla::recalcularTotal(). */
    private const SIGNO = ['bonificacion' => 1, 'descuento' => -1, 'adelanto' => -1, 'aportacion' => 0];

    /**
     * GET /importacion-conceptos/modelo?mes=&anio= — el Excel del mes para
     * llenar: la gente con planilla ya puesta y una columna por concepto.
     */
    public function modelo(Request $request)
    {
        $datos = $request->validate([
            'mes'  => 'required|integer|between:1,12',
            'anio' => 'required|integer|between:2000,2100',
        ]);
        $mes  = (int) $datos['mes'];
        $anio = (int) $datos['anio'];

        return ModelosDeImportacion::conceptos($mes, $anio)
            ->descargar('Modelo de conceptos ' . Meses::nombre($mes) . " {$anio}.xlsx");
    }

    /** POST /importacion-conceptos/reconocer — qué es cada columna. */
    public function reconocer(Request $request)
    {
        $datos = $request->validate([
            'columnas'   => 'required|array|min:1|max:' . self::MAX_COLUMNAS,
            'columnas.*' => 'nullable|string|max:255',
        ], [
            'columnas.required' => 'El archivo no tiene títulos de columna.',
            'columnas.max'      => 'El archivo tiene más de ' . self::MAX_COLUMNAS . ' columnas.',
        ]);

        $reconocedor = new ReconocedorDeColumnas();

        return response()->json([
            'success' => true,
            'data'    => [
                'columnas' => $reconocedor->reconocer($datos['columnas']),
                'catalogo' => $reconocedor->catalogoEditable(),
            ],
        ]);
    }

    /** POST /importacion-conceptos/previsualizar — qué cambiaría. No guarda nada. */
    public function previsualizar(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->paraPantalla($this->analizar($request)),
        ]);
    }

    /**
     * POST /importacion-conceptos/aplicar — lo guarda.
     *
     * Recibe lo mismo que previsualizar y lo vuelve a analizar entero: entre
     * que RR.HH. vio la vista previa y confirmó pudo cerrarse una planilla, o
     * alguien pudo mandar otra cosa por la API. Con un solo error no se guarda
     * nada.
     */
    public function aplicar(Request $request)
    {
        $r = $this->analizar($request);

        if ($r['errores']) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo tiene errores. Corrígelos y vuelve a revisarlo: no se guardó nada.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }

        if (! $r['trabajadores']) {
            return response()->json([
                'success' => false,
                'message' => 'No hay nada que cambiar: los montos del archivo ya están así en las planillas.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }

        $usuario = $request->user();
        $creados = [];

        DB::transaction(function () use ($r, $usuario, &$creados) {
            // 1. Los conceptos nuevos, antes que las líneas que los usan.
            $idsNuevos = [];
            foreach ($r['nuevos'] as $indice => $nuevo) {
                $concepto = PaymentConcept::create([
                    'nombre'         => $nuevo['nombre'],
                    'tipo'           => $nuevo['tipo'],
                    'calculo'        => null,
                    'valor'          => null,
                    'aplica_a_todos' => false,
                    'descripcion'    => "Creado al importar desde Excel ({$r['periodo']}).",
                ]);
                $idsNuevos[$indice] = $concepto->id;
                $creados[] = $nuevo['nombre'];
            }

            // 2. Las líneas de cada planilla, y su neto.
            foreach ($r['trabajadores'] as $trabajador) {
                $planilla = $trabajador['_planilla'];

                foreach ($trabajador['cambios'] as $cambio) {
                    $conceptoId = $cambio['_concepto_id'] ?? $idsNuevos[$cambio['_indice']];

                    if ($cambio['accion'] === 'quitar') {
                        PayrollDetalle::where('planilla_id', $planilla->id)
                            ->where('payment_concept_id', $conceptoId)
                            ->delete();
                        continue;
                    }

                    $valores = [
                        'monto_calculado' => $cambio['despues'],
                        'calculo'         => 'fijo',
                        'valor'           => $cambio['despues'],
                    ];
                    // Sin detalle en el Excel se conserva el que ya tuviera.
                    if ($cambio['descripcion'] !== null) {
                        $valores['descripcion'] = $cambio['descripcion'];
                    }

                    PayrollDetalle::updateOrCreate(
                        ['planilla_id' => $planilla->id, 'payment_concept_id' => $conceptoId],
                        $valores
                    );
                }

                $planilla->recalcularTotal();
            }

            // 3. Los nombres que RR.HH. confirmó: la próxima vez se reconocen solos.
            foreach ($r['plan'] as $indice => $columna) {
                $alias = mb_substr(ReconocedorDeColumnas::normalizar($columna['titulo_original']), 0, 150);

                if ($alias === '' || $alias === ReconocedorDeColumnas::normalizar($columna['nombre'])) {
                    continue;
                }

                ConceptoAlias::updateOrCreate(
                    ['alias' => $alias],
                    [
                        'payment_concept_id' => $columna['concepto_id'] ?? $idsNuevos[$indice],
                        'confirmado_por'     => $usuario?->name,
                    ]
                );
            }
        });

        $resumen = $this->paraPantalla($r)['resumen'];
        $lineas  = $resumen['lineas_nuevas'] + $resumen['lineas_cambiadas'] + $resumen['lineas_quitadas'];

        Auditoria::registrar(
            'importó',
            'planilla',
            null,
            "Importó {$lineas} cambios de conceptos desde Excel en {$resumen['trabajadores']} planillas de {$r['periodo']}",
            [
                'archivo'           => $r['archivo'],
                'lineas_nuevas'     => $resumen['lineas_nuevas'],
                'lineas_cambiadas'  => $resumen['lineas_cambiadas'],
                'lineas_quitadas'   => $resumen['lineas_quitadas'],
                'conceptos_creados' => $creados,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'resumen' => ['conceptos_creados' => $creados] + $resumen,
                'mensaje' => "Listo: {$lineas} cambios en {$resumen['trabajadores']} planillas de {$r['periodo']}.",
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Revisa el archivo entero y arma lo que habría que hacer.
     *
     * Nunca escribe. Lo usan la vista previa y la aplicación, así que las dos
     * ven exactamente lo mismo.
     */
    private function analizar(Request $request): array
    {
        $datos = $request->validate([
            'mes'                           => 'required|integer|between:1,12',
            'anio'                          => 'required|integer|between:2000,2100',
            'archivo'                       => 'nullable|string|max:150',
            'columnas'                      => 'required|array|min:1|max:' . self::MAX_COLUMNAS,
            'columnas.*.indice'             => 'required|integer|min:0|max:' . (self::MAX_COLUMNAS - 1),
            'columnas.*.titulo'             => 'nullable|string|max:255',
            'columnas.*.accion'             => 'required|in:dni,usar,crear,detalle,ignorar',
            'columnas.*.payment_concept_id' => 'nullable|uuid',
            'columnas.*.nuevo'              => 'nullable|array',
            'columnas.*.nuevo.nombre'       => 'nullable|string|max:150',
            'columnas.*.nuevo.tipo'         => 'nullable|string',
            'columnas.*.de_columna'         => 'nullable|integer|min:0',
            'filas'                         => 'required|array|min:1|max:' . self::MAX_FILAS,
            'filas.*.numero'                => 'required|integer|min:1',
            'filas.*.celdas'                => 'present|array|max:' . self::MAX_COLUMNAS,
        ], [
            'filas.required'          => 'El archivo no tiene filas con datos.',
            'filas.max'               => 'El archivo tiene más de ' . self::MAX_FILAS . ' filas: pártelo en dos.',
            'columnas.max'            => 'El archivo tiene más de ' . self::MAX_COLUMNAS . ' columnas.',
            'columnas.*.accion.in'    => 'Hay columnas sin decidir: elige qué hacer con cada una.',
        ]);

        $mes     = (int) $datos['mes'];
        $anio    = (int) $datos['anio'];
        $periodo = Meses::nombre($mes) . ' ' . $anio;

        $r = [
            'mes' => $mes, 'anio' => $anio, 'periodo' => $periodo,
            'archivo' => $datos['archivo'] ?? null,
            'errores' => [], 'advertencias' => [], 'trabajadores' => [],
            'nuevos' => [], 'plan' => [], 'filas_leidas' => 0,
        ];

        $columnas = collect($datos['columnas'])->map(fn ($c) => $c + [
            'titulo' => null, 'payment_concept_id' => null, 'nuevo' => null, 'de_columna' => null,
        ]);

        if ($columnas->pluck('indice')->duplicates()->isNotEmpty()) {
            $r['errores'][] = $this->aviso(null, null, null, 'Hay dos columnas en la misma posición.');
            return $r;
        }

        // ── Las columnas ─────────────────────────────────────────
        $columnasDni = $columnas->where('accion', 'dni');
        if ($columnasDni->count() !== 1) {
            $r['errores'][] = $this->aviso(null, null, null, $columnasDni->isEmpty()
                ? 'Marca cuál es la columna del DNI: sin ella no se sabe de quién es cada fila.'
                : 'Solo una columna puede ser la del DNI.');
        }
        $indiceDni = $columnasDni->first()['indice'] ?? null;

        $dinero = $columnas->whereIn('accion', ['usar', 'crear']);
        if ($dinero->isEmpty()) {
            $r['errores'][] = $this->aviso(null, null, null, 'Ninguna columna se va a importar: asigna al menos una a un concepto de pago.');
        }

        $catalogo = PaymentConcept::whereIn('id', $dinero->pluck('payment_concept_id')->filter()->all())->get()->keyBy('id');
        $existentes = PaymentConcept::pluck('nombre')
            ->mapWithKeys(fn ($nombre) => [ReconocedorDeColumnas::normalizar($nombre) => $nombre]);

        $nombresNuevos = [];
        foreach ($dinero as $col) {
            $indice = $col['indice'];
            $titulo = $this->tituloDe($col);

            if ($col['accion'] === 'usar') {
                $concepto = $catalogo->get($col['payment_concept_id']);

                if (! $concepto) {
                    $r['errores'][] = $this->aviso(null, null, $titulo, "La columna «{$titulo}» no apunta a ningún concepto del catálogo.");
                    continue;
                }
                if (in_array($concepto->nombre, ConceptosDePago::NO_EDITABLES, true)) {
                    $r['errores'][] = $this->aviso(null, null, $titulo, "«{$concepto->nombre}» lo calcula el sistema para cada trabajador: no se puede importar.");
                    continue;
                }

                $r['plan'][$indice] = [
                    'titulo' => $titulo, 'titulo_original' => (string) $col['titulo'],
                    'concepto_id' => $concepto->id, 'nombre' => $concepto->nombre,
                    'tipo' => $concepto->tipo, 'porcentaje' => $concepto->calculo === 'porcentaje',
                ];
                continue;
            }

            // Crear uno nuevo: pide nombre y, sobre todo, tipo.
            $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($col['nuevo']['nombre'] ?? '')));
            $tipo   = $col['nuevo']['tipo'] ?? null;
            $normal = ReconocedorDeColumnas::normalizar($nombre);

            if ($nombre === '') {
                $r['errores'][] = $this->aviso(null, null, $titulo, "Ponle nombre al concepto nuevo de la columna «{$titulo}».");
            } elseif (! in_array($tipo, self::TIPOS, true)) {
                $r['errores'][] = $this->aviso(null, null, $titulo, "Elige si «{$nombre}» es un ingreso, un descuento, un adelanto o una aportación: de eso depende si suma o resta del neto.");
            } elseif ($existentes->has($normal)) {
                $r['errores'][] = $this->aviso(null, null, $titulo, "Ya existe «{$existentes[$normal]}» en el catálogo: asigna la columna «{$titulo}» a ese concepto en vez de crear otro igual.");
            } elseif (isset($nombresNuevos[$normal])) {
                $r['errores'][] = $this->aviso(null, null, $titulo, "Dos columnas quieren crear el mismo concepto «{$nombre}».");
            } else {
                $nombresNuevos[$normal] = true;
                $r['nuevos'][$indice] = ['nombre' => $nombre, 'tipo' => $tipo];
                $r['plan'][$indice] = [
                    'titulo' => $titulo, 'titulo_original' => (string) $col['titulo'],
                    'concepto_id' => null, 'nombre' => $nombre, 'tipo' => $tipo, 'porcentaje' => false,
                ];
            }
        }

        // Dos columnas al mismo concepto: ¿cuál de los dos montos vale?
        $porConcepto = [];
        foreach ($r['plan'] as $columna) {
            if (! $columna['concepto_id']) {
                continue;
            }
            if (isset($porConcepto[$columna['concepto_id']])) {
                $r['errores'][] = $this->aviso(null, null, $columna['titulo'],
                    "Las columnas «{$porConcepto[$columna['concepto_id']]}» y «{$columna['titulo']}» van al mismo concepto «{$columna['nombre']}»: deja una sola.");
            }
            $porConcepto[$columna['concepto_id']] = $columna['titulo'];
        }

        $detalles = [];
        foreach ($columnas->where('accion', 'detalle') as $col) {
            $de = $col['de_columna'];

            if ($de === null || ! $dinero->contains('indice', $de)) {
                $r['errores'][] = $this->aviso(null, null, $this->tituloDe($col), "La columna de detalle «{$this->tituloDe($col)}» no dice de qué concepto es.");
            } elseif (isset($detalles[$de])) {
                $r['errores'][] = $this->aviso(null, null, $this->tituloDe($col), 'Un mismo concepto tiene dos columnas de detalle.');
            } else {
                $detalles[$de] = $col['indice'];
            }
        }

        // Con las columnas mal armadas no tiene sentido leer las filas.
        if ($r['errores']) {
            return $r;
        }

        // ── Lo que conviene saber antes de confirmar ─────────────
        if (collect($r['plan'])->contains('tipo', 'bonificacion')) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                'Los ingresos que importes no vuelven a calcular la pensión, EsSalud ni la renta de 5ta: esos montos se calcularon al generar la planilla.');
        }
        foreach ($r['plan'] as $columna) {
            if ($columna['porcentaje']) {
                $r['advertencias'][] = $this->aviso(null, null, $columna['titulo'],
                    "«{$columna['nombre']}» se calcula por porcentaje. A quien le pongas un monto en el Excel le queda ese monto fijo.");
            }
        }

        // ── Las planillas del mes ────────────────────────────────
        $idsUsados = collect($r['plan'])->pluck('concepto_id')->filter()->values()->all();

        $planillas = Planilla::with([
                'empleado:id,dni,nombre,apellido',
                'corrida:id,nombre,estado',
                'payrollDetalles' => fn ($q) => $q->whereIn('payment_concept_id', $idsUsados ?: ['-']),
            ])
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->where('estado_registro', 'activo')
            ->get();

        if ($planillas->isEmpty()) {
            $r['errores'][] = $this->aviso(null, null, null, "No hay planillas en {$periodo}. Genera primero la planilla del mes y vuelve a importar.");
            return $r;
        }

        $porDni = $planillas->filter(fn ($p) => $p->empleado)->groupBy(fn ($p) => LectorDeCeldas::claveDni($p->empleado->dni));
        $empleados = Empleado::pluck('dni')->mapWithKeys(fn ($dni) => [LectorDeCeldas::claveDni($dni) => true])->all();

        // ── Fila por fila ────────────────────────────────────────
        $vistos = [];
        foreach ($datos['filas'] as $fila) {
            $numero = (int) $fila['numero'];
            $celdas = array_values($fila['celdas'] ?? []);

            if (LectorDeCeldas::filaVacia($celdas)) {
                continue;
            }
            $r['filas_leidas']++;

            $dni = LectorDeCeldas::dni($celdas[$indiceDni] ?? null);
            if ($dni['vacio']) {
                $r['errores'][] = $this->aviso($numero, null, 'DNI', 'Falta el DNI.');
                continue;
            }

            $clave = collect($dni['claves'])->first(fn ($c) => $porDni->has($c) || isset($empleados[$c]));
            if (! $clave) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], 'DNI', ctype_digit($dni['claves'][0])
                    ? "No hay ningún trabajador con el DNI {$dni['texto']}."
                    : "«{$dni['texto']}» no es un DNI válido.");
                continue;
            }

            if (isset($vistos[$clave])) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], 'DNI', "Este DNI ya aparece en la fila {$vistos[$clave]}: cada trabajador va una sola vez.");
                continue;
            }
            $vistos[$clave] = $numero;

            $suyas = $porDni->get($clave);
            if (! $suyas) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], 'DNI', "Este trabajador no tiene planilla en {$periodo}.");
                continue;
            }
            if ($suyas->count() > 1) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], 'DNI', "Tiene más de una planilla en {$periodo}: revísalo desde la pantalla de Planillas.");
                continue;
            }

            $planilla = $suyas->first();
            if ($planilla->corrida?->estaCerrada()) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], null, "Su planilla «{$planilla->corrida->nombre}» está cerrada: ya se pagó y no se modifica.");
                continue;
            }

            $cambios  = [];
            $conError = false;
            $delta    = 0.0;

            foreach ($r['plan'] as $indice => $columna) {
                $crudo   = $celdas[$indice] ?? null;
                $detalle = isset($detalles[$indice]) ? LectorDeCeldas::texto($celdas[$detalles[$indice]] ?? null) : null;

                if (LectorDeCeldas::vacia($crudo)) {
                    if ($detalle !== null) {
                        $r['advertencias'][] = $this->aviso($numero, $dni['texto'], $columna['titulo'], "Hay un detalle para «{$columna['titulo']}» pero no un monto: se ignora.");
                    }
                    continue;
                }

                $monto = LectorDeCeldas::monto($crudo);
                $problema = match (true) {
                    $monto === null                         => '«' . mb_substr((string) $crudo, 0, 40) . '» no es un monto.',
                    $monto < 0                              => 'No se aceptan montos negativos. Para quitar el concepto, pon 0.',
                    $monto > self::MONTO_MAXIMO             => 'El monto es demasiado grande.',
                    $detalle !== null && mb_strlen($detalle) > 255 => 'El detalle pasa de 255 caracteres.',
                    default                                 => null,
                };
                if ($problema) {
                    $r['errores'][] = $this->aviso($numero, $dni['texto'], $columna['titulo'], $problema);
                    $conError = true;
                    continue;
                }

                $linea = $columna['concepto_id']
                    ? $planilla->payrollDetalles->firstWhere('payment_concept_id', $columna['concepto_id'])
                    : null;
                $antes = $linea ? round((float) $linea->monto_calculado, 2) : null;

                if ($monto == 0.0) {
                    if (! $linea) {
                        continue;
                    }
                    $accion = 'quitar';
                } elseif (! $linea) {
                    $accion = 'agregar';
                } elseif (abs($antes - $monto) >= 0.005 || ($detalle !== null && $detalle !== $linea->descripcion)) {
                    $accion = 'cambiar';
                } else {
                    continue; // ya está así
                }

                $despues = $accion === 'quitar' ? 0.0 : $monto;
                $delta  += (self::SIGNO[$columna['tipo']] ?? 0) * ($despues - ($antes ?? 0));

                $cambios[] = [
                    'columna'      => $columna['titulo'],
                    'concepto'     => $columna['nombre'],
                    'tipo'         => $columna['tipo'],
                    'accion'       => $accion,
                    'antes'        => $antes,
                    'despues'      => $despues,
                    'descripcion'  => $accion === 'quitar' ? null : $detalle,
                    '_indice'      => $indice,
                    '_concepto_id' => $columna['concepto_id'],
                ];
            }

            if ($conError || ! $cambios) {
                continue;
            }

            $neto = round((float) $planilla->total, 2);
            $r['trabajadores'][] = [
                'fila'         => $numero,
                'dni'          => $planilla->empleado->dni,
                'nombre'       => trim($planilla->empleado->nombre . ' ' . $planilla->empleado->apellido),
                'planilla'     => $planilla->corrida?->nombre ?? 'Sin agrupar',
                'neto_antes'   => $neto,
                'neto_despues' => round($neto + $delta, 2),
                'cambios'      => $cambios,
                '_planilla'    => $planilla,
            ];
        }

        return $r;
    }

    /** Lo que ve la pantalla: sin los modelos ni las claves internas. */
    private function paraPantalla(array $r): array
    {
        $sinInternos = fn (array $fila) => array_filter($fila, fn ($clave) => ! str_starts_with($clave, '_'), ARRAY_FILTER_USE_KEY);
        $cambios = collect($r['trabajadores'])->flatMap(fn ($t) => $t['cambios']);

        return [
            'resumen' => [
                'mes'              => $r['mes'],
                'anio'             => $r['anio'],
                'periodo'          => $r['periodo'],
                'filas_leidas'     => $r['filas_leidas'],
                'trabajadores'     => count($r['trabajadores']),
                'lineas_nuevas'    => $cambios->where('accion', 'agregar')->count(),
                'lineas_cambiadas' => $cambios->where('accion', 'cambiar')->count(),
                'lineas_quitadas'  => $cambios->where('accion', 'quitar')->count(),
                'errores'          => count($r['errores']),
                'advertencias'     => count($r['advertencias']),
                'conceptos_nuevos' => array_values($r['nuevos']),
            ],
            'trabajadores' => collect($r['trabajadores'])->map(fn ($t) => [
                'cambios' => array_map($sinInternos, $t['cambios']),
            ] + $sinInternos($t))->values()->all(),
            'errores'      => $r['errores'],
            'advertencias' => $r['advertencias'],
        ];
    }

    private function aviso(?int $fila, ?string $dni, ?string $columna, string $mensaje): array
    {
        return ['fila' => $fila, 'dni' => $dni, 'columna' => $columna, 'mensaje' => $mensaje];
    }

    private function tituloDe(array $columna): string
    {
        return trim((string) ($columna['titulo'] ?? '')) ?: 'Columna ' . ($columna['indice'] + 1);
    }
}
