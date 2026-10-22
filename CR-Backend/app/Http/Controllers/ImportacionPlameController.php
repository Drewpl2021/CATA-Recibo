<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Empleado;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Services\AltaDeEmpleado;
use App\Support\ColumnasDeEmpleado;
use App\Support\ConceptosDePago;
use App\Support\LectorDeCeldas;
use App\Support\Meses;
use App\Support\Plame\ColumnasDelPlame;
use App\Support\Plame\LectorPlame;
use App\Support\ReconocedorDeColumnas;
use App\Support\Renta5ta\RecalculoRenta5ta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Importar el PLAME de RR.HH.: la planilla de un mes tal como se pagó.
 *
 * Sirve para los meses que no pasaron por el sistema (enero a julio de
 * 2026). Cada mes queda con su planilla cerrada y marcada «PLAME», con los
 * montos del Excel tal cual: no se recalcula nada, porque eso es lo que se
 * pagó y se declaró. Con eso la 5ta de los meses siguientes parte de lo
 * retenido de verdad, la CTS usa la gratificación de julio que se pagó, y el
 * historial de cada trabajador queda completo.
 *
 * Qué hace según el mes:
 *
 *   crear        el mes no tiene planillas: se crean.
 *   reemplazar   el mes ya se importó del PLAME: la importación nueva
 *                reemplaza a la anterior (se corrigió el Excel).
 *   comparar     el mes ya tiene planillas del sistema (agosto en adelante):
 *                no se toca nada, solo se muestran las diferencias.
 *
 * Cada fila tiene que cuadrar: la suma de sus columnas tiene que dar la
 * REMUNERACIÓN NETA del PLAME. Si una fila no cuadra es que se leyó mal una
 * columna, y con un solo error no se guarda nada.
 *
 * Quien está en el PLAME y no en el sistema se registra con lo que trae el
 * PLAME. Su contrato decide: si su fecha de fin ya pasó, entra como cesado;
 * si sigue vigente (o es indeterminado), como activo. RR.HH. puede cambiarlo
 * fila por fila en la revisión.
 *
 * El archivo se lee en el servidor y no se guarda: el PLAME es un .xls
 * antiguo que el navegador no abre, y lleva el sueldo de todo el colegio.
 */
class ImportacionPlameController extends Controller
{
    private const TOLERANCIA = 0.015;
    private const MONTO_MAXIMO = 999999.99;
    private const NOMBRE_CORRIDA = 'Planilla General (PLAME)';

    /** POST /plame-import/preview — qué pasaría. No guarda nada. */
    public function previsualizar(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->paraPantalla($this->analizar($request)),
        ]);
    }

    /** POST /plame-import/apply — lo guarda todo, o nada. */
    public function aplicar(Request $request)
    {
        $r = $this->analizar($request);

        if ($r['modo'] === 'comparar') {
            return response()->json([
                'success' => false,
                'message' => "{$r['periodo']} ya tiene planillas del sistema: el PLAME solo se compara con ellas, no las reemplaza.",
                'data'    => $this->paraPantalla($r),
            ], 422);
        }
        if ($r['errores']) {
            return response()->json([
                'success' => false,
                'message' => 'El PLAME tiene errores. Corrígelos y vuelve a revisarlo: no se guardó nada.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }
        if (! $r['trabajadores']) {
            return response()->json([
                'success' => false,
                'message' => 'El PLAME no tiene trabajadores para importar.',
                'data'    => $this->paraPantalla($r),
            ], 422);
        }

        $usuario = $request->user();
        $creados = ['activos' => 0, 'cesados' => 0];

        $empleados = DB::transaction(function () use ($r, $usuario, &$creados) {
            // Reemplazar: lo importado antes de este mes se va entero.
            if ($r['modo'] === 'reemplazar') {
                foreach ($r['_anteriores'] as $planilla) {
                    $planilla->eliminarDeVerdad();
                }
                PlanillaCorrida::whereIn('id', $r['_anteriores']->pluck('corrida_id')->filter()->unique())->get()
                    ->each(fn (PlanillaCorrida $c) => $c->delete());
            }

            $corrida = PlanillaCorrida::create([
                'nombre'         => self::NOMBRE_CORRIDA,
                'mes'            => $r['mes'],
                'anio'           => $r['anio'],
                'estado'         => 'cerrada',
                'origen'         => PlanillaCorrida::ORIGEN_PLAME,
                'archivo_origen' => $r['archivo'],
                'observaciones'  => mb_substr('Importada del PLAME «' . ($r['archivo'] ?? 'sin nombre') . '» el '
                    . now()->format('d/m/Y') . ($usuario ? " por {$usuario->name}" : '') . '.', 0, 255),
            ]);

            $empleados = [];
            $progreso = \App\Support\Progreso::actual()->etapa('Guardando la planilla de cada trabajador', count($r['trabajadores']));
            foreach ($r['trabajadores'] as $t) {
                $progreso->avanzar();
                $empleado = $t['_empleado'];
                if (! $empleado) {
                    $empleado = AltaDeEmpleado::crear($t['_alta']);
                    $creados[$t['situacion'] === 'nuevo_cesado' ? 'cesados' : 'activos']++;
                }
                $empleados[] = $empleado;

                $planilla = Planilla::create([
                    'empleado_id'     => $empleado->id,
                    'mes'             => $r['mes'],
                    'anio'            => $r['anio'],
                    'corrida_id'      => $corrida->id,
                    'sueldo_base'     => $t['sueldo'],
                    'total'           => 0,
                    'estado_registro' => 'activo',
                ]);
                foreach ($t['_lineas'] as $linea) {
                    PayrollDetalle::create([
                        'planilla_id'        => $planilla->id,
                        'payment_concept_id' => $linea['concepto_id'],
                        'monto_calculado'    => $linea['monto'],
                        'calculo'            => 'fijo',
                        'valor'              => round($linea['monto'], 2),
                        'descripcion'        => $linea['descripcion'],
                    ]);
                }
                $planilla->recalcularTotal();
            }

            return $empleados;
        });

        // La 5ta de los meses que siguen resta lo retenido en este: se
        // recalcula en las planillas que todavía se pueden tocar.
        $recalculadas = 0;
        if ($r['mes'] < 12) {
            $recalculo = new RecalculoRenta5ta();
            $progreso = \App\Support\Progreso::actual()->etapa('Recalculando la 5ta de los meses siguientes', count($empleados));
            foreach ($empleados as $empleado) {
                $progreso->avanzar();
                $recalculadas += $recalculo->empleado($empleado, $r['anio'], $r['mes'] + 1)['recalculadas'];
            }
        }

        $resumen = $this->paraPantalla($r)['resumen'];
        Auditoria::registrar(
            'importó',
            'planilla',
            null,
            "Importó el PLAME de {$r['periodo']}: {$resumen['trabajadores']} planillas"
                . ($creados['activos'] + $creados['cesados'] ? ", {$creados['activos']} trabajador(es) nuevo(s) activo(s) y {$creados['cesados']} cesado(s)" : '')
                . ($r['modo'] === 'reemplazar' ? ' (reemplazó la importación anterior)' : ''),
            [
                'archivo'       => $r['archivo'],
                'modo'          => $r['modo'],
                'planillas'     => $resumen['trabajadores'],
                'neto_total'    => $resumen['neto_total'],
                'nuevos'        => $creados,
                'recalculadas'  => $recalculadas,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'resumen' => $resumen + ['recalculadas' => $recalculadas],
                'mensaje' => "Listo: {$resumen['trabajadores']} planillas de {$r['periodo']} cargadas desde el PLAME"
                    . ($recalculadas ? ". Se recalculó la 5ta de {$recalculadas} planilla(s) de los meses siguientes." : '.'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    /** Revisa el PLAME entero y arma lo que habría que hacer. Nunca escribe. */
    private function analizar(Request $request): array
    {
        $datos = $request->validate([
            'archivo'    => 'required|file|mimes:xls,xlsx|max:10240',
            'mes'        => 'required|integer|between:1,12',
            'anio'       => 'required|integer|between:2000,2100',
            'decisiones' => 'nullable|json|max:20000',
        ], [
            'archivo.required' => 'Elige el PLAME del mes.',
            'archivo.mimes'    => 'El PLAME tiene que ser un Excel (.xls o .xlsx).',
            'archivo.max'      => 'El archivo pasa de 10 MB.',
        ]);

        $mes  = (int) $datos['mes'];
        $anio = (int) $datos['anio'];
        $decisiones = json_decode($datos['decisiones'] ?? '{}', true) ?: [];
        $decisionesColumnas = collect($decisiones['columnas'] ?? [])->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
        $decisionesPersonas = collect($decisiones['personas'] ?? [])
            ->filter(fn ($v) => in_array($v, ['activo', 'cesado'], true))->all();

        $r = [
            'mes' => $mes, 'anio' => $anio, 'periodo' => Meses::nombre($mes) . " {$anio}",
            'archivo' => mb_substr($request->file('archivo')->getClientOriginalName(), 0, 150),
            'hoja' => null, 'modo' => 'crear', 'columnas' => [],
            'errores' => [], 'advertencias' => [], 'trabajadores' => [],
            'solo_en_sistema' => [], 'filas_leidas' => 0,
            '_anteriores' => collect(),
        ];

        if (Carbon::create($anio, $mes, 1)->gt(now()->startOfMonth())) {
            $r['errores'][] = $this->aviso(null, null, null, "{$r['periodo']} todavía no llega: el PLAME es de un mes ya pagado.");
            return $r;
        }

        try {
            $hoja = LectorPlame::leer($request->file('archivo')->getRealPath());
        } catch (\App\Exceptions\AvisoParaElUsuario $e) {
            $r['errores'][] = $this->aviso(null, null, null, $e->getMessage());
            return $r;
        }
        $r['hoja'] = $hoja['hoja'];

        // ── Las columnas ─────────────────────────────────────────
        $columnas = ColumnasDelPlame::reconocer($hoja['columnas'], $hoja['filas'], $decisionesColumnas);
        $r['columnas'] = $columnas;

        $dato = [];
        $total = [];
        $deDinero = [];
        $sueldo = null;
        foreach ($columnas as $c) {
            match ($c['rol']) {
                'dato'    => $dato[$c['clave']] ??= $c['indice'],
                'total'   => $total[$c['clave']] ??= $c['indice'],
                'sueldo'  => $sueldo ??= $c['indice'],
                'concepto', 'otros' => $deDinero[] = $c,
                'falta'   => $r['errores'][] = $this->aviso(null, null, "{$c['letra']}: {$c['titulo']}", $c['motivo']),
                default   => null,
            };
        }

        if (! isset($dato['dni'])) {
            $r['errores'][] = $this->aviso(null, null, null, 'No se encontró la columna del DNI.');
        }
        if ($sueldo === null) {
            $r['errores'][] = $this->aviso(null, null, null, 'No se encontró la columna «SUELDO BÁSICO».');
        }
        $porConcepto = collect($deDinero)->where('rol', 'concepto')->groupBy('concepto_id')->filter(fn ($g) => $g->count() > 1);
        foreach ($porConcepto as $grupo) {
            $r['errores'][] = $this->aviso(null, null, null, 'Las columnas ' . $grupo->map(fn ($c) => "«{$c['letra']}: {$c['titulo']}»")->implode(' y ')
                . " van las dos a «{$grupo->first()['concepto']}». Elige otra cosa para una de ellas.");
        }
        if (! isset($total['neto'])) {
            $r['advertencias'][] = $this->aviso(null, null, null, 'El PLAME no tiene la columna «REMUNERACIÓN NETA»: no se puede comprobar que cada fila cuadre.');
        }
        if ($r['errores']) {
            return $r;
        }

        // ── Qué hay ya en ese mes ────────────────────────────────
        $existentes = Planilla::with(['corrida', 'empleado', 'payrollDetalles.paymentConcept', 'documentoBoleta'])
            ->where('mes', $mes)->where('anio', $anio)->where('estado_registro', 'activo')->get();
        if ($existentes->isNotEmpty()) {
            $todasDelPlame = $existentes->every(fn (Planilla $p) => $p->corrida?->vieneDelPlame());
            $r['modo'] = $todasDelPlame ? 'reemplazar' : 'comparar';
            if ($todasDelPlame) {
                $r['_anteriores'] = $existentes;
                if ($firmadas = $existentes->filter(fn (Planilla $p) => $p->boletaFirmada())->count()) {
                    $r['errores'][] = $this->aviso(null, null, null,
                        "Ya se importó el PLAME de {$r['periodo']} y {$firmadas} boleta(s) de ese mes están firmadas: no se puede reemplazar.");
                }
            }
        }
        $planillaDe = $existentes->filter(fn ($p) => $p->empleado)->keyBy(fn ($p) => LectorDeCeldas::claveDni($p->empleado->dni));

        $fichas = Empleado::all()->keyBy(fn (Empleado $e) => LectorDeCeldas::claveDni($e->dni));
        $catalogos = ColumnasDeEmpleado::catalogos();
        $rolEmpleado = $catalogos['rol']['empleado']['id'] ?? null;

        // ── Fila por fila ────────────────────────────────────────
        $vistos = [];
        $celda = fn (array $celdas, ?int $i) => $i === null ? null : ($celdas[$i] ?? null);
        $progreso = \App\Support\Progreso::actual()->etapa('Revisando las filas del PLAME', count($hoja['filas']));

        foreach ($hoja['filas'] as $fila) {
            $progreso->avanzar();
            $numero = $fila['numero'];
            $celdas = $fila['celdas'];
            $r['filas_leidas']++;
            $nombre = LectorDeCeldas::texto($celda($celdas, $dato['nombre'] ?? null)) ?? '';

            $dni = LectorDeCeldas::dni($celda($celdas, $dato['dni']));
            if ($dni['vacio']) {
                $r['errores'][] = $this->aviso($numero, null, 'DNI', 'Falta el DNI' . ($nombre ? " de {$nombre}" : '') . '.');
                continue;
            }
            $clave = collect($dni['claves'])->first(fn ($c) => $fichas->has($c)) ?? $dni['claves'][count($dni['claves']) - 1];
            if (! preg_match('/^[0-9]{8,9}$/', $clave)) {
                $r['errores'][] = $this->aviso($numero, $dni['texto'], 'DNI', "«{$dni['texto']}» no es un DNI.");
                continue;
            }
            if (isset($vistos[$clave])) {
                $r['errores'][] = $this->aviso($numero, $clave, 'DNI', "Este DNI ya aparece en la fila {$vistos[$clave]}.");
                continue;
            }
            $vistos[$clave] = $numero;

            // Los montos de la fila.
            $montoSueldo = LectorDeCeldas::monto($celda($celdas, $sueldo)) ?? 0.0;
            $porConceptoId = [];
            $otros = [];
            $conError = false;
            foreach ($deDinero as $c) {
                $crudo = $celda($celdas, $c['indice']);
                if (LectorDeCeldas::vacia($crudo)) {
                    continue;
                }
                $monto = LectorDeCeldas::monto($crudo);
                $problema = match (true) {
                    $monto === null              => '«' . mb_substr((string) $crudo, 0, 30) . '» no es un monto.',
                    $monto < 0                   => 'Hay un monto negativo.',
                    $monto > self::MONTO_MAXIMO  => 'El monto es demasiado grande.',
                    default                      => null,
                };
                if ($problema) {
                    $r['errores'][] = $this->aviso($numero, $clave, "{$c['letra']}: {$c['titulo']}", $problema);
                    $conError = true;
                    continue;
                }
                if ($monto == 0.0) {
                    continue;
                }
                if ($c['rol'] === 'otros') {
                    $otros[$c['clave']][] = ['etiqueta' => ColumnasDelPlame::etiqueta($c['titulo']), 'monto' => $monto, 'concepto_id' => $c['concepto_id'], 'concepto' => $c['concepto'], 'tipo' => $c['tipo']];
                } else {
                    $porConceptoId[$c['concepto_id']] = ['concepto_id' => $c['concepto_id'], 'concepto' => $c['concepto'], 'tipo' => $c['tipo'], 'monto' => $monto, 'descripcion' => null];
                }
            }
            if ($conError) {
                continue;
            }

            // «Otros»: una sola línea por bolsa, con cada columna en el detalle.
            $detalle = LectorDeCeldas::texto($celda($celdas, $dato['detalle'] ?? null));
            foreach ($otros as $partes) {
                $suma = round(array_sum(array_column($partes, 'monto')), 6);
                $texto = $detalle ?: $this->detalleDeOtros($partes);
                if (! $partes[0]['concepto_id']) {
                    $r['errores'][] = $this->aviso($numero, $clave, null, "Falta «{$partes[0]['concepto']}» en el catálogo de conceptos.");
                    $conError = true;
                    continue;
                }
                $porConceptoId[$partes[0]['concepto_id']] = [
                    'concepto_id' => $partes[0]['concepto_id'], 'concepto' => $partes[0]['concepto'], 'tipo' => $partes[0]['tipo'],
                    'monto' => $suma, 'descripcion' => mb_substr($texto, 0, 255),
                ];
            }
            if ($conError) {
                continue;
            }
            $lineas = array_values($porConceptoId);

            // ¿Cuadra? Lo mismo que Planilla::recalcularTotal(): se redondea al final.
            $ingresos = $montoSueldo + collect($lineas)->where('tipo', 'bonificacion')->sum('monto');
            $descuentos = collect($lineas)->whereIn('tipo', ['descuento', 'adelanto'])->sum('monto');
            $neto = round($ingresos - $descuentos, 2);
            $netoPlame = isset($total['neto']) ? LectorDeCeldas::monto($celda($celdas, $total['neto'])) : null;

            if ($netoPlame !== null && abs($neto - $netoPlame) > self::TOLERANCIA) {
                $pistas = [];
                if (isset($total['bruto']) && ($bruto = LectorDeCeldas::monto($celda($celdas, $total['bruto']))) !== null && abs($bruto - $ingresos) > self::TOLERANCIA) {
                    $pistas[] = 'los ingresos suman ' . $this->soles($ingresos) . ' y la bruta del PLAME dice ' . $this->soles($bruto);
                }
                if (isset($total['descuentos']) && ($desc = LectorDeCeldas::monto($celda($celdas, $total['descuentos']))) !== null && abs($desc - $descuentos) > self::TOLERANCIA) {
                    $pistas[] = 'los descuentos suman ' . $this->soles($descuentos) . ' y el total del PLAME dice ' . $this->soles($desc);
                }
                $r['errores'][] = $this->aviso($numero, $clave, 'REMUNERACIÓN NETA',
                    "No cuadra: el PLAME dice " . $this->soles($netoPlame) . ' de neto y sus columnas dan ' . $this->soles($neto)
                    . ($pistas ? ' (' . implode('; ', $pistas) . ').' : '.')
                    . ' Revisa si falta importar una columna o si una suma dos veces.');
                continue;
            }

            $trabajador = [
                'fila' => $numero, 'dni' => $clave, 'nombre' => $nombre,
                'sueldo' => round($montoSueldo, 6), 'neto' => $neto, 'neto_plame' => $netoPlame,
                'lineas' => array_map(fn ($l) => ['concepto' => $l['concepto'], 'tipo' => $l['tipo'], 'monto' => round($l['monto'], 2), 'descripcion' => $l['descripcion']], $lineas),
                'situacion' => 'existe', 'avisos' => [], 'diferencias' => [], 'neto_sistema' => null,
                '_empleado' => $fichas->get($clave), '_lineas' => $lineas, '_alta' => null,
            ];

            if ($ficha = $trabajador['_empleado']) {
                $trabajador['nombre'] = trim($ficha->apellido . ', ' . $ficha->nombre);
                $trabajador['avisos'] = $this->diferenciasConLaFicha($ficha, $celdas, $dato, $lineas);
                if ($ficha->fecha_ingreso && Carbon::parse($ficha->fecha_ingreso)->gt(Carbon::create($anio, $mes, 1)->endOfMonth())) {
                    $trabajador['avisos'][] = 'Su ficha dice que entró el ' . Carbon::parse($ficha->fecha_ingreso)->format('d/m/Y') . ', después de este mes.';
                }
            } elseif ($r['modo'] === 'comparar') {
                $trabajador['situacion'] = 'sin_ficha';
            } else {
                if (! $this->armarAlta($r, $trabajador, $celdas, $dato, $lineas, $catalogos, $rolEmpleado, $decisionesPersonas[$clave] ?? null)) {
                    continue;
                }
            }

            if ($r['modo'] === 'comparar') {
                $this->compararConElSistema($trabajador, $planillaDe->get($clave));
            }

            $r['trabajadores'][] = $trabajador;
        }

        if ($r['modo'] === 'comparar') {
            foreach ($planillaDe as $clave => $planilla) {
                if (! isset($vistos[$clave])) {
                    $r['solo_en_sistema'][] = [
                        'dni' => $planilla->empleado->dni,
                        'nombre' => trim($planilla->empleado->apellido . ', ' . $planilla->empleado->nombre),
                        'neto_sistema' => round((float) $planilla->total, 2),
                    ];
                }
            }
        }

        // ── Lo que conviene saber ────────────────────────────────
        $lista = collect($r['trabajadores']);
        if ($n = $lista->where('situacion', 'nuevo_activo')->count()) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                "{$n} trabajador(es) no están en el sistema y su contrato sigue vigente: se registran como activos, sin cuenta de acceso. "
                . 'Complétales correo, teléfono, sede y área en Empleados. Si alguno ya dejó de trabajar, márcalo como cesado aquí.');
        }
        if ($n = $lista->where('situacion', 'nuevo_cesado')->count()) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                "{$n} trabajador(es) no están en el sistema y su contrato ya terminó: se registran como cesados, para guardar su planilla.");
        }
        if ($n = $lista->filter(fn ($t) => $t['avisos'] && ! str_starts_with($t['situacion'], 'nuevo'))->count()) {
            $r['advertencias'][] = $this->aviso(null, null, null,
                "{$n} trabajador(es) tienen en el PLAME algo distinto de su ficha (asignación familiar, pensión). No se cambia la ficha: revísalo.");
        }
        if ($r['modo'] === 'reemplazar') {
            $r['advertencias'][] = $this->aviso(null, null, null,
                "{$r['periodo']} ya se había importado del PLAME: esta importación reemplaza a la anterior.");
        }

        return $r;
    }

    /**
     * Arma el alta de quien está en el PLAME y no en el sistema.
     * Devuelve false si la fila no alcanza para registrarlo (y anota por qué).
     */
    private function armarAlta(array &$r, array &$t, array $celdas, array $dato, array $lineas, array &$catalogos, ?string $rolEmpleado, ?string $decision): bool
    {
        $celda = fn (?int $i) => $i === null ? null : ($celdas[$i] ?? null);
        $texto = fn (string $clave) => LectorDeCeldas::texto($celda($dato[$clave] ?? null));
        $numero = $t['fila'];

        [$apellido, $nombre] = $this->partirNombre($t['nombre']);
        $ingreso = LectorDeCeldas::fecha($celda($dato['fecha_ingreso'] ?? null));
        $finCrudo = $celda($dato['fecha_cese'] ?? null);
        $fin = LectorDeCeldas::fecha($finCrudo);

        $problemas = [];
        if ($apellido === '' || $nombre === '') {
            $problemas[] = 'Falta su nombre completo (apellidos y nombres).';
        }
        if (! $ingreso) {
            $problemas[] = 'Falta su fecha de ingreso: sin ella no se puede registrar.';
        }
        if ($fin === null && ! LectorDeCeldas::vacia($finCrudo) && ! in_array(ReconocedorDeColumnas::normalizar((string) $finCrudo), ['indeterminado', 'indeterminada', 'plazo indeterminado'], true)) {
            $problemas[] = '«' . mb_substr((string) $finCrudo, 0, 30) . '» no es una fecha de cese.';
        }

        $tipoContrato = null;
        if ($categoria = $texto('categoria')) {
            $lectura = ColumnasDeEmpleado::leer('tipo_contrato', $categoria, $catalogos);
            $lectura['error'] ? $problemas[] = $lectura['error'] : $tipoContrato = $lectura['valor'];
        }

        $hoy = now()->toDateString();
        $finDelMes = Carbon::create($r['anio'], $r['mes'], 1)->endOfMonth()->toDateString();
        $estado = $decision ?? ($fin && $fin < $hoy ? 'cesado' : 'activo');
        if ($estado === 'cesado' && (! $fin || $fin >= $hoy)) {
            // Marcado como cesado sin fecha: lo último que se sabe es que cobró este mes.
            $fin = min($finDelMes, $hoy);
        }

        if ($problemas) {
            foreach ($problemas as $p) {
                $r['errores'][] = $this->aviso($numero, $t['dni'], null, "No está en el sistema y no se puede registrar: {$p}");
            }
            return false;
        }

        // Área y sede, solo cuando el centro de costos lo dice sin dudas.
        $centro = ReconocedorDeColumnas::normalizar($texto('centro_costos') ?? '');
        $areaId = null;
        foreach (['inicial', 'primaria', 'secundaria'] as $nivel) {
            if (str_contains($centro, $nivel)) {
                $areas = array_filter($catalogos['area'], fn ($a, $k) => str_contains($k, $nivel), ARRAY_FILTER_USE_BOTH);
                $areaId = count($areas) === 1 ? reset($areas)['id'] : null;
            }
        }
        $sedeId = null;
        if (str_contains($centro, 'jerusalen')) {
            $sedes = array_filter($catalogos['sede'], fn ($s, $k) => str_contains($k, 'jerusalen'), ARRAY_FILTER_USE_BOTH);
            $sedeId = count($sedes) === 1 ? reset($sedes)['id'] : null;
        }
        // El cargo, solo si ya está en el catálogo: el PLAME lo escribe a mano
        // («Docende de Primaria», «Asitente de TIC») y crearlo llenaría
        // Cargos de copias mal escritas.
        $cargoId = null;
        if ($cargo = $texto('cargo')) {
            $lectura = ColumnasDeEmpleado::leer('cargo', $cargo, $catalogos);
            $lectura['error']
                ? $t['avisos'][] = "Su cargo en el PLAME es «{$cargo}», que no está en el catálogo: elígelo en su ficha."
                : $cargoId = $lectura['valor'];
        }

        // Pensión: la marca de ONP, o la AFP con su CUSPP.
        $montoDe = fn (string $nombre) => (float) (collect($lineas)->firstWhere('concepto', $nombre)['monto'] ?? 0);
        $afp = ColumnasDeEmpleado::leer('afp', $texto('afp') ?? '', $catalogos);
        $aportaOnp = isset($dato['aporta_onp']) ? LectorDeCeldas::siNo($celda($dato['aporta_onp'])) : null;
        $pension = match (true) {
            $aportaOnp === true || $montoDe(ConceptosDePago::ONP) > 0 => 'ONP',
            ! $afp['error'] && $afp['valor']                           => 'AFP',
            default                                                    => null,
        };
        $cuspp = strtoupper(preg_replace('/\s+/', '', (string) ($texto('cuspp') ?? '')));
        if ($pension === 'AFP' && ! preg_match('/^[A-ZÑ0-9]{12}$/u', $cuspp)) {
            $t['avisos'][] = 'El PLAME dice que está en AFP, pero su CUSPP no tiene 12 caracteres: se registra sin sistema de pensión. Complétalo en su ficha.';
            $pension = null;
        }
        $comision = ColumnasDeEmpleado::leer('tipo_comision_afp', $texto('tipo_comision') ?? '', $catalogos);

        $tieneHijos = isset($dato['tiene_hijos'])
            ? (bool) LectorDeCeldas::siNo($celda($dato['tiene_hijos']))
            : $montoDe(ConceptosDePago::ASIGNACION_FAMILIAR) > 0;
        $cuenta = $texto('cuenta');

        $alta = [
            'dni'                => $t['dni'],
            'nombre'             => $nombre,
            'apellido'           => $apellido,
            'fecha_ingreso'      => $ingreso,
            'fecha_cese'         => $fin,
            'estado'             => $estado === 'cesado' ? 'inactivo' : 'activo',
            'tipo_contrato_id'   => $tipoContrato,
            'cargo_id'           => $cargoId,
            'area_id'            => $areaId,
            'sede_id'            => $sedeId,
            'sueldo_base'        => round($t['sueldo'], 2),
            'bonificacion_cargo' => round($montoDe(ConceptosDePago::BONIFICACION_CARGO), 2),
            'sistema_pensiones'  => $pension,
            'afp'                => $pension === 'AFP' ? $afp['valor'] : null,
            'cuspp'              => $pension === 'AFP' ? $cuspp : null,
            'tipo_comision_afp'  => $pension === 'AFP' && ! $comision['error'] ? ($comision['valor'] ?? 'flujo') : null,
            'tiene_hijos'        => $tieneHijos,
            'numero_cuenta'      => $cuenta ? mb_substr($cuenta, 0, 50) : null,
            'forma_pago'         => $cuenta ? 'banco' : null,
            'rol_id'             => $rolEmpleado,
        ];

        $reglas = $this->sinConsultas(AltaDeEmpleado::reglasDeCesado());
        $reglas['fecha_cese'] = $estado === 'cesado'
            ? 'required|date|after_or_equal:fecha_ingreso|before_or_equal:today'
            : 'nullable|date|after_or_equal:fecha_ingreso';
        $reglas['fecha_ingreso'] = 'required|date|before_or_equal:today';
        // Entra sin cuenta (el PLAME no trae correo): el rol se le da al crearla.
        $reglas['rol_id'] = 'nullable|uuid';
        $validador = Validator::make($alta, $reglas, AltaDeEmpleado::mensajes(), ColumnasDeEmpleado::atributos());
        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $p) {
                $r['errores'][] = $this->aviso($numero, $t['dni'], null, "No está en el sistema y no se puede registrar: {$p}");
            }
            return false;
        }

        $t['nombre'] = trim("{$apellido}, {$nombre}");
        $t['situacion'] = $estado === 'cesado' ? 'nuevo_cesado' : 'nuevo_activo';
        $t['fecha_cese'] = $fin;
        $t['_alta'] = $alta;

        return true;
    }

    /** Lo que el PLAME dice distinto de su ficha. No se cambia nada: se avisa. */
    private function diferenciasConLaFicha(Empleado $ficha, array $celdas, array $dato, array $lineas): array
    {
        $avisos = [];
        $asignacion = collect($lineas)->firstWhere('concepto', ConceptosDePago::ASIGNACION_FAMILIAR)['monto'] ?? 0;
        $hijosPlame = isset($dato['tiene_hijos'])
            ? LectorDeCeldas::siNo($celdas[$dato['tiene_hijos']] ?? null)
            : ($asignacion > 0);
        if ($hijosPlame !== null && $hijosPlame !== (bool) $ficha->tiene_hijos) {
            $avisos[] = $hijosPlame
                ? 'El PLAME le paga asignación familiar, pero su ficha dice que no tiene hijos.'
                : 'Su ficha dice que tiene hijos, pero el PLAME no le paga asignación familiar.';
        }

        $onp = collect($lineas)->firstWhere('concepto', ConceptosDePago::ONP);
        $afp = collect($lineas)->firstWhere('concepto', ConceptosDePago::SPP_FONDO);
        if ($onp && $ficha->sistema_pensiones !== 'ONP') {
            $avisos[] = 'El PLAME le descuenta ONP, pero su ficha dice ' . ($ficha->sistema_pensiones ?: 'que no aporta') . '.';
        } elseif ($afp && $ficha->sistema_pensiones !== 'AFP') {
            $avisos[] = 'El PLAME le descuenta AFP, pero su ficha dice ' . ($ficha->sistema_pensiones ?: 'que no aporta') . '.';
        }

        return $avisos;
    }

    /** Concepto por concepto, lo del PLAME contra la planilla del sistema. */
    private function compararConElSistema(array &$t, ?Planilla $planilla): void
    {
        if (! $planilla) {
            $t['situacion'] = $t['situacion'] === 'sin_ficha' ? 'sin_ficha' : 'sin_planilla';
            return;
        }

        $sistema = [ConceptosDePago::REMUNERACION_BASICA => round((float) $planilla->sueldo_base, 2)];
        foreach ($planilla->payrollDetalles as $d) {
            $nombre = $d->paymentConcept?->nombre ?? '—';
            $sistema[$nombre] = round(($sistema[$nombre] ?? 0) + (float) $d->monto_calculado, 2);
        }
        $plame = [ConceptosDePago::REMUNERACION_BASICA => round($t['sueldo'], 2)];
        foreach ($t['lineas'] as $l) {
            $plame[$l['concepto']] = round(($plame[$l['concepto']] ?? 0) + $l['monto'], 2);
        }

        foreach (array_unique([...array_keys($plame), ...array_keys($sistema)]) as $concepto) {
            $a = $plame[$concepto] ?? 0.0;
            $b = $sistema[$concepto] ?? 0.0;
            if (abs($a - $b) > 0.005) {
                $t['diferencias'][] = ['concepto' => $concepto, 'plame' => $a, 'sistema' => $b, 'diferencia' => round($a - $b, 2)];
            }
        }
        $t['neto_sistema'] = round((float) $planilla->total, 2);
        $t['situacion'] = $t['diferencias'] ? 'con_diferencias' : 'igual';
    }

    /**
     * «Curso IA (S/ 60.00) y Corbatas y polos (S/ 12.00)»: como lo escribió
     * RR.HH. a mano en setiembre.
     */
    private function detalleDeOtros(array $partes): string
    {
        $textos = array_map(fn ($p) => $p['etiqueta'] . ' (S/ ' . number_format($p['monto'], 2) . ')', $partes);
        $ultimo = array_pop($textos);

        return $textos ? implode(', ', $textos) . ' y ' . $ultimo : $ultimo;
    }

    /**
     * «QUISPE MAMANI, JUAN CARLOS» o «QUISPE MAMANI JUAN CARLOS»: con coma,
     * la coma separa; sin ella, los dos primeros son los apellidos.
     *
     * @return array{0: string, 1: string} apellidos y nombres
     */
    private function partirNombre(string $completo): array
    {
        $completo = trim(preg_replace('/\s+/u', ' ', $completo));
        $bonito = fn (string $s) => mb_convert_case(mb_strtolower(trim($s)), MB_CASE_TITLE, 'UTF-8');

        if (str_contains($completo, ',')) {
            [$apellidos, $nombres] = array_map('trim', explode(',', $completo, 2));

            return [$bonito($apellidos), $bonito($nombres)];
        }

        $palabras = explode(' ', $completo);
        if (count($palabras) < 2) {
            return [$bonito($completo), ''];
        }
        $cuantos = count($palabras) >= 3 ? 2 : 1;

        return [$bonito(implode(' ', array_slice($palabras, 0, $cuantos))), $bonito(implode(' ', array_slice($palabras, $cuantos)))];
    }

    /** Las reglas del alta sin consultar la base fila por fila: el DNI y los catálogos ya se revisaron. */
    private function sinConsultas(array $reglas): array
    {
        return array_map(
            fn ($regla) => is_string($regla)
                ? implode('|', array_filter(explode('|', $regla), fn ($p) => ! str_starts_with($p, 'unique:') && ! str_starts_with($p, 'exists:') && $p !== 'unique'))
                : array_values(array_filter($regla, fn ($p) => ! is_string($p) || (! str_starts_with($p, 'unique') && ! str_starts_with($p, 'exists')))),
            $reglas
        );
    }

    /** Lo que ve la pantalla: sin los modelos ni las claves internas. */
    private function paraPantalla(array $r): array
    {
        $lista = collect($r['trabajadores']);
        $sinInternos = fn (array $fila) => array_filter($fila, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);

        return [
            'resumen' => [
                'mes'             => $r['mes'],
                'anio'            => $r['anio'],
                'periodo'         => $r['periodo'],
                'archivo'         => $r['archivo'],
                'hoja'            => $r['hoja'],
                'modo'            => $r['modo'],
                'filas_leidas'    => $r['filas_leidas'],
                'trabajadores'    => $lista->count(),
                'existentes'      => $lista->where('situacion', '!=', 'nuevo_activo')->where('situacion', '!=', 'nuevo_cesado')->count(),
                'nuevos_activos'  => $lista->where('situacion', 'nuevo_activo')->count(),
                'nuevos_cesados'  => $lista->where('situacion', 'nuevo_cesado')->count(),
                'con_diferencias' => $lista->where('situacion', 'con_diferencias')->count(),
                'con_avisos'      => $lista->filter(fn ($t) => $t['avisos'])->count(),
                'neto_total'      => round($lista->sum('neto'), 2),
                'errores'         => count($r['errores']),
                'advertencias'    => count($r['advertencias']),
            ],
            'columnas'        => array_map(fn ($c) => array_intersect_key($c, array_flip(['indice', 'letra', 'titulo', 'grupo', 'rol', 'clave', 'concepto', 'concepto_id', 'tipo', 'estado', 'motivo', 'editable', 'ejemplo'])),
                array_values(array_filter($r['columnas'], fn ($c) => $c['titulo'] !== '' || $c['rol'] !== ColumnasDelPlame::IGNORAR))),
            'trabajadores'    => $lista->map($sinInternos)->values()->all(),
            'solo_en_sistema' => $r['solo_en_sistema'],
            'errores'         => $r['errores'],
            'advertencias'    => $r['advertencias'],
        ];
    }

    private function aviso(?int $fila, ?string $dni, ?string $columna, string $mensaje): array
    {
        return ['fila' => $fila, 'dni' => $dni, 'columna' => $columna, 'mensaje' => $mensaje];
    }

    private function soles(float $monto): string
    {
        return 'S/ ' . number_format($monto, 2);
    }
}
