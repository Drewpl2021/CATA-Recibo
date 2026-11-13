<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CtsInasistencia;
use App\Models\CtsLiquidacion;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\ValorLegal;
use App\Support\Cts\MotorCts;
use App\Support\Firma\FirmaDelColegio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * El módulo «CTS»: la Compensación por Tiempo de Servicios de cada semestre.
 *
 *   GET    /cts?anio=&periodo=          la lista del semestre: lo calculado o, si aún no, una vista previa
 *   POST   /cts/calculate               calcular y guardar la CTS de todo el personal del semestre
 *   POST   /cts/deposit                 registrar el depósito de una o varias (con su hoja en el expediente)
 *   DELETE /cts/{liquidacion}/deposit   deshacer un depósito registrado por error
 *   GET    /cts/{liquidacion}/sheet     la hoja de liquidación en PDF
 *   GET    /cts/export                  la lista en Excel, con los filtros de la pantalla
 *   GET    /cts/{empleado}/history      lo que ganó mes por mes en el semestre, de sus planillas
 *   PUT    /cts/absences                anotar sus días de falta injustificada del semestre
 *   PUT    /cts/absences/batch          los de varios a la vez, como la columna de la hoja de RR.HH.
 *   POST   /cts/sign-here               «Firmar aquí»: la firma digital del colegio en las hojas del depósito
 *
 * La hoja de cada depósito se firma digitalmente por el colegio, como la
 * boleta (App\Support\Firma\FirmaDelColegio): el trabajador la ve en Mis
 * Documentos recién cuando tiene las firmas que pide el colegio.
 *
 * Ver App\Support\Cts\MotorCts para el cálculo.
 */
class CtsController extends Controller
{
    use \App\Traits\DescargaEnZip;

    /** El año y el periodo pedidos; sin ellos, el próximo depósito. */
    private function periodoPedido(Request $request): array
    {
        $datos = $request->validate([
            'anio'    => 'nullable|integer|min:2000|max:2100',
            'periodo' => ['nullable', Rule::in(MotorCts::PERIODOS)],
        ]);

        $hoy = now();
        $porDefecto = match (true) {
            $hoy->month <= 5  => [(int) $hoy->year, 'mayo'],
            $hoy->month <= 11 => [(int) $hoy->year, 'noviembre'],
            default           => [(int) $hoy->year + 1, 'mayo'],
        };

        return [(int) ($datos['anio'] ?? $porDefecto[0]), $datos['periodo'] ?? $porDefecto[1]];
    }

    /** Quien pudo trabajar en el semestre: los activos, los que cesaron dentro de él y quien ya tenga su CTS guardada. */
    private function personalDelSemestre(int $anio, string $periodo): Collection
    {
        $semestre = MotorCts::semestre($anio, $periodo);

        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre', 'foto')
            ->where('fecha_ingreso', '<=', $semestre['hasta']->toDateString())
            ->where(fn ($q) => $q->where('estado', 'activo')
                ->orWhere('fecha_cese', '>=', $semestre['desde']->toDateString())
                ->orWhereIn('id', CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->select('empleado_id')))
            ->orderBy('apellido')->orderBy('nombre')->get();
    }

    public function index(Request $request)
    {
        [$anio, $periodo] = $this->periodoPedido($request);
        $semestre = MotorCts::semestre($anio, $periodo);
        $filas = $this->filas($anio, $periodo);
        $conDerecho = $filas->where('aplica', true);

        return response()->json(['success' => true, 'data' => [
            'anio'    => $anio,
            'anios'   => \App\Support\AniosConDatos::cts($anio),
            'periodo' => $periodo,
            'desde'   => $semestre['desde']->toDateString(),
            'hasta'   => $semestre['hasta']->toDateString(),
            'limite'  => $semestre['limite']->toDateString(),
            'filas'   => $filas->values(),
            'resumen' => [
                'trabajadores'  => $filas->count(),
                'con_derecho'   => $conDerecho->count(),
                'por_calcular'  => $filas->where('estado_cts', 'por_calcular')->count(),
                'calculadas'    => $filas->where('estado_cts', 'calculada')->count(),
                'depositadas'   => $filas->where('estado_cts', 'depositada')->count(),
                'desactualizadas' => $filas->where('desactualizada', true)->count(),
                'total'         => round($conDerecho->sum('monto'), 2),
                'depositado'    => round($filas->where('estado_cts', 'depositada')->sum('monto'), 2),
            ],
            // Cómo va la firma digital del colegio en las hojas de este depósito.
            'firma'   => FirmaDelColegio::resumen($request, $this->hojasDelDeposito($anio, $periodo)),
        ]]);
    }

    /**
     * GET /cts/deposit-report — la relación de depósitos de CTS del semestre:
     * a qué entidad y cuenta CTS va la de cada uno (nunca a la del sueldo),
     * con lo que no se deposita (el cesado cobra con su liquidación, quien no
     * tiene cuenta CTS) y lo que hay que revisar. En PDF o en Excel.
     */
    public function relacionDeDepositos(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorCts::PERIODOS)],
            'formato' => 'nullable|in:pdf,xlsx',
        ]);
        $anio = (int) $datos['anio'];
        $periodo = $datos['periodo'];

        $liquidaciones = CtsLiquidacion::with('empleado')->where('anio', $anio)->where('periodo', $periodo)->get()
            ->filter(fn (CtsLiquidacion $l) => $l->empleado);
        if ($liquidaciones->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Todavía no hay CTS calculadas de ese semestre: calcúlalas primero.'], 422);
        }

        $r = \App\Support\Pagos\RelacionDePagos::armar($liquidaciones->map(fn (CtsLiquidacion $l) => [
            'dni'     => $l->empleado->dni,
            'nombre'  => trim($l->empleado->apellido . ' ' . $l->empleado->nombre),
            'entidad' => $l->entidad_financiera ?: $l->empleado->cts_entidad_financiera,
            'cuenta'  => $l->numero_cuenta ?: $l->empleado->cts_numero_cuenta,
            'monto'   => (float) $l->monto,
            'cesado'  => $l->empleado->estado === 'inactivo',
        ])->values()->all(), esCts: true);

        $depositadas = $liquidaciones->filter->depositada()->count();
        $semestre = ucfirst($periodo) . ' ' . $anio;
        $info = [
            'titulo'    => 'RELACIÓN DE DEPÓSITOS DE CTS',
            'subtitulo' => "Depósito de {$semestre} · {$r['personas']} trabajador(es)"
                . ($depositadas ? " · {$depositadas} ya registrada(s) como depositada(s)" : ''),
            'borrador'  => null,
            'r'         => $r,
            'conCci'    => false,
            'quien'     => $request->user()?->name,
        ];
        $archivo = "Relación de depósitos CTS - {$semestre}";

        Auditoria::registrar('descargó', 'cts', null, "Descargó la relación de depósitos de CTS de {$semestre} ({$r['personas']} trabajadores, S/ " . number_format($r['total_abonar'], 2) . ' por depósito).');

        if (($datos['formato'] ?? null) === 'xlsx') {
            $libro = new \App\Support\Listados\LibroExcel();
            \App\Support\Pagos\RelacionDePagos::hojas($libro, $r, false);

            return $libro->descargar($archivo . '.xlsx');
        }

        return Pdf::loadView('relacion-de-pagos', $info)->setPaper('a4', 'portrait')->download($archivo . '.pdf');
    }

    /**
     * GET /cts/export — la lista del depósito en Excel, con los mismos filtros
     * que la pantalla (situación, sede, área, cargo y búsqueda): la
     * remuneración computable desglosada, el tiempo, la CTS, el depósito con
     * su cuenta y cómo va la firma de su hoja.
     */
    public function exportar(Request $request)
    {
        [$anio, $periodo] = $this->periodoPedido($request);
        $f = $request->validate([
            'situacion' => 'nullable|in:por_calcular,calculada,depositada,sin_derecho',
            'sede_id'   => 'nullable|string|max:64',
            'area_id'   => 'nullable|string|max:64',
            'cargo_id'  => 'nullable|string|max:64',
            'search'    => 'nullable|string|max:100',
        ]);
        $todas = $this->filas($anio, $periodo);
        $filas = \App\Support\Listados\FiltroDeLista::aplicar($todas, $f, fn (array $x, string $s) => $x['estado_cts'] === $s);

        $estados = ['por_calcular' => 'Por calcular', 'calculada' => 'Por depositar', 'depositada' => 'Depositada', 'sin_derecho' => 'Sin CTS'];
        $firma = ['pendiente' => 'Por firmar', 'parcial' => 'Una firma de dos', 'completa' => 'Firmada y entregada'];
        $titulo = "CTS - depósito de {$periodo} {$anio}";
        $fecha = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';

        $hoja = [
            [$titulo],
            [\App\Support\Listados\FiltroDeLista::describir($f, $todas, $estados) . ' · ' . $filas->count() . ' de ' . $todas->count() . ' trabajador(es)'],
            [''],
            ['N°', 'DNI', 'Apellidos y nombres', 'Cargo', 'Área', 'Sede', 'Estado', 'Ingreso',
                'Sueldo', 'Asignación familiar', 'Bonificación por cargo', '1/6 gratificación', 'Remuneración computable',
                'Meses', 'Días', 'Días no laborados', 'CTS', 'Situación', 'Fecha de depósito', 'Entidad CTS', 'N° de cuenta CTS', 'Hoja'],
        ];
        foreach ($filas->values() as $n => $x) {
            $aplica = (bool) $x['aplica'];
            $hoja[] = [$n + 1, (string) $x['dni'], $x['nombre'], $x['cargo'], $x['area'], $x['sede'],
                $x['estado'] === 'activo' ? 'Activo' : 'Cesado', $fecha($x['fecha_ingreso']),
                $aplica ? $x['sueldo'] : null, $aplica ? $x['asignacion_familiar'] : null, $aplica ? $x['bonificacion_cargo'] : null,
                $aplica ? $x['sexto_gratificacion'] : null, $aplica ? $x['remuneracion_computable'] : null,
                $aplica ? $x['meses'] : null, $aplica ? $x['dias'] : null, $aplica ? (((int) $x['faltas'] + (int) ($x['dias_licencia'] ?? 0)) ?: null) : null,
                $aplica ? $x['monto'] : null,
                $estados[$x['estado_cts']] ?? $x['estado_cts'], $fecha($x['fecha_deposito']),
                $x['entidad_financiera'] ?: $x['entidad_ficha'], (string) ($x['numero_cuenta'] ?: $x['cuenta_ficha']),
                $x['firma_colegio'] ? $firma[$x['firma_colegio']] : ''];
        }
        $montos = [8, 9, 10, 11, 12, 16];
        $total = array_fill(0, 22, '');
        $total[2] = 'TOTAL';
        $total[16] = round($filas->where('aplica', true)->sum('monto'), 2);
        $hoja[] = $total;

        $estiloTotal = array_fill(0, 22, \App\Support\Listados\LibroExcel::TOTAL_TEXTO);
        foreach ($montos as $c) {
            $estiloTotal[$c] = \App\Support\Listados\LibroExcel::TOTAL_MONTO;
        }
        $libro = new \App\Support\Listados\LibroExcel();
        $libro->hoja('CTS', $hoja, [
            'anchos' => [6, 12, 36, 26, 22, 18, 10, 12, 12, 12, 12, 12, 14, 8, 8, 10, 13, 15, 13, 18, 22, 20],
            'estiloColumnas' => [1 => \App\Support\Listados\LibroExcel::TEXTO, 20 => \App\Support\Listados\LibroExcel::TEXTO] + array_fill_keys($montos, \App\Support\Listados\LibroExcel::MONTO),
            'estiloFilas' => [0 => \App\Support\Listados\LibroExcel::ENCABEZADO, 1 => \App\Support\Listados\LibroExcel::PARRAFO,
                3 => array_fill(0, 22, \App\Support\Listados\LibroExcel::TITULO), count($hoja) - 1 => $estiloTotal],
        ]);

        return $libro->descargar($titulo . (array_filter($f) ? ' (filtrado)' : '') . '.xlsx');
    }

    /** Las hojas de un depósito que están en el expediente. */
    private function hojasDelDeposito(int $anio, string $periodo): \Illuminate\Database\Eloquent\Builder
    {
        return Documento::where('tipo', 'cts')->where('estado_registro', 'activo')
            ->where('periodo_anio', $anio)->where('periodo_mes', $periodo === 'mayo' ? 5 : 11);
    }

    /**
     * POST /cts/sign-here — «Firmar aquí»: firma una tanda de las hojas del
     * depósito que esperan la firma de quien lo pide, con su certificado.
     * La que queda completa le llega al trabajador.
     */
    public function firmarAqui(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorCts::PERIODOS)],
        ]);
        $deposito = "{$datos['periodo']} {$datos['anio']}";

        return FirmaDelColegio::firmarTanda(
            $request,
            $this->hojasDelDeposito((int) $datos['anio'], $datos['periodo']),
            "hojas de CTS de {$deposito}",
            fn (Documento $d) => "Liquidación de CTS, depósito de {$deposito}",
            fn (Documento $d) => FirmaDelColegio::avisarAlTrabajador($d, 'hoja de CTS', $deposito),
            fn (Documento $d) => null,
            fn (int $firmadas, string $detalle) => Auditoria::registrar('firmó', 'cts', null,
                "Firmó desde el sistema {$firmadas} hoja(s) de CTS de {$deposito} con su certificado ({$detalle}).",
                ['firmadas' => $firmadas]),
        );
    }

    /**
     * Una fila por trabajador del semestre. Si ya tiene su CTS guardada,
     * manda lo guardado (es lo que se depositó o se va a depositar); si no,
     * la vista previa del motor. Una guardada que no se depositó y ya no
     * coincide con la vista previa (le cambiaron el sueldo, se cargó la
     * planilla de la gratificación) sale marcada como desactualizada.
     */
    private function filas(int $anio, string $periodo): Collection
    {
        $motor = new MotorCts();
        $guardadas = CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->get()->keyBy('empleado_id');
        $faltas = CtsInasistencia::where('anio', $anio)->where('periodo', $periodo)->get()->keyBy('empleado_id');
        // Cómo va la firma del colegio en la hoja de cada uno.
        $firmas = Documento::whereIn('id', $guardadas->pluck('documento_id')->filter()->values())->pluck('firma_colegio', 'id');

        return $this->personalDelSemestre($anio, $periodo)->map(function (Empleado $e) use ($motor, $anio, $periodo, $guardadas, $faltas, $firmas) {
            $previa = $motor->calcular($e, $anio, $periodo, (int) ($faltas->get($e->id)?->dias ?? 0));
            $guardada = $guardadas->get($e->id);
            $calculo = $guardada ? $this->desglose($guardada) : $previa;

            $estado = match (true) {
                $guardada?->depositada() => 'depositada',
                (bool) $guardada         => 'calculada',
                $previa['aplica']        => 'por_calcular',
                default                  => 'sin_derecho',
            };

            return [
                'id'                 => $e->id,
                'liquidacion_id'     => $guardada?->id,
                'dni'                => $e->dni,
                'nombre'             => trim("{$e->apellido} {$e->nombre}"),
                'foto'               => $e->foto,
                // Aparte, para el botón «Ordenar» (apellidos o nombres).
                'apellido'           => $e->apellido,
                'nombres'            => $e->nombre,
                'cargo_id'           => $e->cargo_id,
                'cargo'              => $e->cargo?->nombre,
                'area_id'            => $e->area_id,
                'area'               => $e->area?->nombre,
                'sede_id'            => $e->sede_id,
                'sede'               => $e->sede?->nombre,
                'estado'             => $e->estado,
                'fecha_ingreso'      => $e->fecha_ingreso,
                // La cuenta CTS de su ficha, para proponerla al registrar el
                // depósito. Nunca la del sueldo: la CTS va a otra cuenta.
                'entidad_ficha'      => $e->cts_entidad_financiera,
                'cuenta_ficha'       => $e->cts_numero_cuenta,
                ...$calculo,
                'aplica'             => $guardada ? true : $previa['aplica'],
                'estado_cts'         => $estado,
                'desactualizada'     => $estado === 'calculada' && abs($previa['monto'] - $guardada->monto) > 0.005,
                'fecha_deposito'     => $guardada?->fecha_deposito?->toDateString(),
                'entidad_financiera' => $guardada?->entidad_financiera,
                'numero_cuenta'      => $guardada?->numero_cuenta,
                'documento_id'       => $guardada?->documento_id,
                // pendiente | parcial | completa; null si su hoja no va por firma digital o no hay hoja.
                'firma_colegio'      => $guardada?->documento_id ? $firmas->get($guardada->documento_id) : null,
                // Lo anotado ahora (puede diferir de lo que lleva una ya calculada).
                'faltas_registradas' => (int) ($faltas->get($e->id)?->dias ?? 0),
                'faltas_observacion' => $faltas->get($e->id)?->observacion,
            ];
        });
    }

    /**
     * Anota los días no laborados (faltas injustificadas) de un trabajador en
     * un semestre, como la columna «Días no laborados» de la hoja de RR.HH.
     *
     * Es un dato solo de la CTS: no sale de la planilla ni la toca. El
     * descuento por tardanzas y faltas de la boleta es otra cosa y se queda
     * en la boleta. Cada día le quita 1/360 de su CTS.
     * Con 0 se borra el registro. No se toca una CTS ya depositada: eso es lo
     * que se pagó, y para corregirla primero se anula el depósito. Si ya
     * estaba calculada se recalcula al momento, para que cuadre sin otro paso.
     */
    public function registrarFaltas(Request $request)
    {
        $datos = $request->validate([
            'empleado_id' => 'required|uuid|exists:empleados,id',
            'anio'        => 'required|integer|min:2000|max:2100',
            'periodo'     => ['required', Rule::in(MotorCts::PERIODOS)],
            'dias'        => 'required|integer|min:0|max:180',
            'observacion' => 'nullable|string|max:255',
        ], self::MENSAJES_FALTAS);
        $anio = (int) $datos['anio'];
        $periodo = $datos['periodo'];

        $liquidacion = CtsLiquidacion::where('empleado_id', $datos['empleado_id'])->where('anio', $anio)->where('periodo', $periodo)->first();
        if ($liquidacion?->depositada()) {
            return response()->json(['success' => false, 'message' => 'Su CTS de este semestre ya está depositada. Para cambiar sus faltas, primero anula el depósito.'], 422);
        }

        $empleado = Empleado::findOrFail($datos['empleado_id']);
        [$antes, $calculo] = DB::transaction(fn () => $this->guardarFaltasDe(
            $empleado, $anio, $periodo, (int) $datos['dias'], $datos['observacion'] ?? null, $request->user()?->id
        ));

        if ($antes !== (int) $datos['dias']) {
            Auditoria::registrar('cambió', 'cts', $liquidacion?->id,
                "Anotó los días no laborados de {$empleado->nombre} {$empleado->apellido} en la CTS de {$periodo} {$anio}: de {$antes} a {$datos['dias']} día(s).");
        }

        return response()->json(['success' => true, 'data' => [
            'faltas' => (int) $datos['dias'],
            'monto'  => $calculo['monto'],
            'meses'  => $calculo['meses'],
            'dias'   => $calculo['dias'],
        ]]);
    }

    /**
     * Los días no laborados de varios trabajadores a la vez: la lista que
     * RR.HH. llena de una sola vez, como la columna de su hoja.
     *
     * Las depositadas se saltan y se dice de quiénes. Todo lo demás se guarda
     * junto o nada, y las ya calculadas se recalculan al momento.
     */
    public function registrarFaltasEnLote(Request $request)
    {
        $datos = $request->validate([
            'anio'                  => 'required|integer|min:2000|max:2100',
            'periodo'               => ['required', Rule::in(MotorCts::PERIODOS)],
            'faltas'                => 'required|array|min:1|max:500',
            'faltas.*.empleado_id'  => 'required|uuid|distinct|exists:empleados,id',
            'faltas.*.dias'         => 'required|integer|min:0|max:180',
            'faltas.*.observacion'  => 'nullable|string|max:255',
        ], self::MENSAJES_FALTAS);
        $anio = (int) $datos['anio'];
        $periodo = $datos['periodo'];

        $ids = array_column($datos['faltas'], 'empleado_id');
        $empleados = Empleado::whereIn('id', $ids)->get()->keyBy('id');
        $depositadas = CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->whereIn('empleado_id', $ids)
            ->where('estado', CtsLiquidacion::DEPOSITADA)->pluck('empleado_id')->flip();

        $cambiadas = 0;
        $omitidas = [];
        DB::transaction(function () use ($datos, $empleados, $depositadas, $anio, $periodo, $request, &$cambiadas, &$omitidas) {
            foreach ($datos['faltas'] as $f) {
                $e = $empleados[$f['empleado_id']];
                if (isset($depositadas[$e->id])) {
                    $omitidas[] = trim("{$e->apellido} {$e->nombre}");
                    continue;
                }
                [$antes] = $this->guardarFaltasDe($e, $anio, $periodo, (int) $f['dias'], $f['observacion'] ?? null, $request->user()?->id);
                if ($antes !== (int) $f['dias']) {
                    $cambiadas++;
                }
            }
        });

        if ($cambiadas > 0) {
            Auditoria::registrar('cambió', 'cts', null,
                "Anotó los días no laborados de {$cambiadas} trabajador(es) en la CTS de {$periodo} {$anio}.");
        }

        return response()->json(['success' => true, 'data' => [
            'cambiadas' => $cambiadas,
            // Ya depositadas: lo pagado no se toca.
            'omitidas'  => $omitidas,
            'total'     => round(CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->sum('monto'), 2),
        ]]);
    }

    private const MENSAJES_FALTAS = [
        'dias.max'         => 'Un semestre tiene 180 días: no puede tener más faltas que eso.',
        'faltas.*.dias.max' => 'Un semestre tiene 180 días: nadie puede tener más faltas que eso.',
        'faltas.*.dias.integer' => 'Los días no laborados son un número entero.',
        'faltas.*.empleado_id.distinct' => 'Hay un trabajador repetido en la lista.',
    ];

    /**
     * Guarda sus días (0 los borra) y, si su CTS ya estaba calculada y sin
     * depositar, la recalcula ahí mismo. Va dentro de la transacción de quien
     * llama. Devuelve [días que tenía antes, cálculo nuevo].
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function guardarFaltasDe(Empleado $empleado, int $anio, string $periodo, int $dias, ?string $observacion, ?string $quien): array
    {
        $clave = ['empleado_id' => $empleado->id, 'anio' => $anio, 'periodo' => $periodo];
        $antes = (int) CtsInasistencia::where($clave)->value('dias');

        if ($dias === 0) {
            CtsInasistencia::where($clave)->delete();
        } else {
            CtsInasistencia::updateOrCreate($clave, ['dias' => $dias, 'observacion' => $observacion, 'registrado_por' => $quien]);
        }

        $calculo = (new MotorCts())->calcular($empleado, $anio, $periodo, $dias);
        $guardada = CtsLiquidacion::where($clave)->lockForUpdate()->first();
        if ($guardada && ! $guardada->depositada()) {
            $nuevo = $calculo;
            unset($nuevo['aplica'], $nuevo['motivo']);
            $calculo['aplica'] ? $guardada->fill($nuevo)->save() : $guardada->delete();
        }

        return [$antes, $calculo];
    }

    /** Lo guardado, con la misma forma que devuelve el motor. */
    private function desglose(CtsLiquidacion $l): array
    {
        return [
            'aplica' => true, 'motivo' => null,
            'desde' => $l->desde?->toDateString(), 'hasta' => $l->hasta?->toDateString(),
            'sueldo' => $l->sueldo, 'asignacion_familiar' => $l->asignacion_familiar, 'bonificacion_cargo' => $l->bonificacion_cargo,
            'remuneracion_fuente' => $l->remuneracion_fuente,
            'gratificacion' => $l->gratificacion, 'gratificacion_fuente' => $l->gratificacion_fuente,
            'sexto_gratificacion' => $l->sexto_gratificacion, 'remuneracion_computable' => $l->remuneracion_computable,
            'meses' => $l->meses, 'dias' => $l->dias, 'faltas' => (int) $l->faltas, 'dias_licencia' => (int) $l->dias_licencia, 'monto' => $l->monto,
        ];
    }

    /**
     * Calcula y guarda la CTS de todo el personal del semestre.
     *
     * Las ya depositadas no se tocan: son lo que se pagó. Las calculadas se
     * vuelven a calcular (por si cambió algo), y si alguien dejó de tener
     * derecho se quita la suya.
     */
    public function calcular(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorCts::PERIODOS)],
        ]);
        $anio = (int) $datos['anio'];
        $periodo = $datos['periodo'];
        $motor = new MotorCts();
        $calculadas = 0;
        $depositadas = 0;
        $quitadas = 0;

        DB::transaction(function () use ($motor, $anio, $periodo, &$calculadas, &$depositadas, &$quitadas) {
            $guardadas = CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->get()->keyBy('empleado_id');
            $faltas = CtsInasistencia::delSemestre($anio, $periodo);

            foreach ($this->personalDelSemestre($anio, $periodo) as $e) {
                $guardada = $guardadas->get($e->id);
                if ($guardada?->depositada()) {
                    $depositadas++;
                    continue;
                }

                $calculo = $motor->calcular($e, $anio, $periodo, $faltas[$e->id] ?? 0);
                if (! $calculo['aplica']) {
                    if ($guardada) {
                        $guardada->delete();
                        $quitadas++;
                    }
                    continue;
                }

                unset($calculo['aplica'], $calculo['motivo']);
                CtsLiquidacion::updateOrCreate(
                    ['empleado_id' => $e->id, 'anio' => $anio, 'periodo' => $periodo],
                    [...$calculo, 'estado' => CtsLiquidacion::CALCULADA],
                );
                $calculadas++;
            }
        });

        $total = round(CtsLiquidacion::where('anio', $anio)->where('periodo', $periodo)->sum('monto'), 2);
        Auditoria::registrar('cambió', 'cts', null,
            "Calculó la CTS de {$periodo} {$anio}: {$calculadas} trabajador(es), S/ " . number_format($total, 2) . " en total.");

        return response()->json(['success' => true, 'data' => [
            'calculadas' => $calculadas,
            'depositadas' => $depositadas,
            'quitadas' => $quitadas,
            'total' => $total,
        ]]);
    }

    /**
     * Registra el depósito de una o varias liquidaciones.
     *
     * Cada una pasa a «depositada» —ya no se recalcula— y su hoja de
     * liquidación queda en el expediente del trabajador (tipo «cts»), donde él
     * la ve en Mis Documentos y da su conformidad como con la boleta.
     *
     * Si no se indica la cuenta, se toma la cuenta CTS de la ficha de cada
     * uno, nunca la del sueldo. A quien sigue trabajando y no la tiene no se
     * le registra: su CTS va a una cuenta que él eligió, y anotar otra sería
     * dejar en su hoja un depósito que no se hizo ahí. Al cesado sí: su CTS
     * no se deposita, se le paga directo con la liquidación (D.S. 001-97-TR,
     * art. 3).
     */
    public function depositar(Request $request)
    {
        $datos = $request->validate([
            'ids'                => 'required|array|min:1',
            'ids.*'              => 'uuid|exists:cts_liquidaciones,id',
            'fecha_deposito'     => 'required|date',
            'entidad_financiera' => 'nullable|string|max:100|required_with:numero_cuenta',
            'numero_cuenta'      => 'nullable|string|min:6|max:30|regex:/^[0-9]+(-[0-9]+)*$/',
        ], [
            'numero_cuenta.regex'              => 'La cuenta CTS lleva solo números, con guiones si el banco la escribe así.',
            'numero_cuenta.min'                => 'La cuenta CTS tiene al menos 6 cifras.',
            'entidad_financiera.required_with' => 'Indica la entidad donde está la cuenta CTS.',
        ]);
        // Una cuenta escrita a mano solo tiene sentido para una persona.
        if (! empty($datos['numero_cuenta']) && count($datos['ids']) > 1) {
            return response()->json(['success' => false, 'message' => 'El número de cuenta se escribe solo cuando se registra el depósito de una persona.'], 422);
        }

        $liquidaciones = CtsLiquidacion::with('empleado.cargo')->whereIn('id', $datos['ids'])->get();
        $yaDepositadas = $liquidaciones->filter->depositada();
        if ($yaDepositadas->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Hay CTS que ya estaban depositadas. Quítalas de la selección.'], 422);
        }

        $cuentaDe = fn (CtsLiquidacion $l) => [
            'entidad_financiera' => ($datos['entidad_financiera'] ?? null) ?: $l->empleado->cts_entidad_financiera,
            'numero_cuenta'      => ($datos['numero_cuenta'] ?? null) ?: $l->empleado->cts_numero_cuenta,
        ];
        $sinCuenta = $liquidaciones
            ->filter(fn (CtsLiquidacion $l) => $l->empleado->estado !== 'inactivo' && empty($cuentaDe($l)['numero_cuenta']))
            ->map(fn (CtsLiquidacion $l) => trim("{$l->empleado->apellido} {$l->empleado->nombre}"))
            ->sort()->values();
        if ($sinCuenta->isNotEmpty()) {
            $nombres = $sinCuenta->take(3)->implode(', ') . ($sinCuenta->count() > 3 ? ' y ' . ($sinCuenta->count() - 3) . ' más' : '');

            return response()->json(['success' => false, 'message' => "Falta la cuenta CTS de {$nombres}. Anótala en su ficha (Planilla y pagos) antes de registrar el depósito."], 422);
        }

        DB::transaction(function () use ($liquidaciones, $datos, $cuentaDe) {
            foreach ($liquidaciones as $l) {
                $l->fill([
                    'estado'         => CtsLiquidacion::DEPOSITADA,
                    'fecha_deposito' => $datos['fecha_deposito'],
                    ...$cuentaDe($l),
                ])->save();

                $this->guardarHoja($l);
            }
        });

        $l = $liquidaciones->first();
        Auditoria::registrar('cambió', 'cts', $liquidaciones->count() === 1 ? $l->id : null,
            "Registró el depósito de la CTS de {$l->periodo} {$l->anio} de {$liquidaciones->count()} trabajador(es), el {$datos['fecha_deposito']}.");

        return response()->json(['success' => true, 'data' => ['depositadas' => $liquidaciones->count()]]);
    }

    /** Deshace un depósito registrado por error: vuelve a «calculada» y su hoja sale del expediente. */
    public function anularDeposito(string $id)
    {
        $l = CtsLiquidacion::with('empleado')->findOrFail($id);
        if (! $l->depositada()) {
            return response()->json(['success' => false, 'message' => 'Esta CTS no tiene un depósito registrado.'], 422);
        }

        $documentoId = $l->documento_id;
        // Lo que el trabajador ya abrió o firmó no se borra, como con la boleta.
        if ($documentoId && Documento::where('id', $documentoId)->where('estado_firma', '!=', 'pendiente')->exists()) {
            return response()->json(['success' => false, 'message' => 'El trabajador ya abrió su hoja de CTS o dio su conformidad: no se puede anular el depósito.'], 422);
        }
        $l->fill(['estado' => CtsLiquidacion::CALCULADA, 'fecha_deposito' => null, 'documento_id' => null])->save();
        if ($documentoId) {
            Documento::where('id', $documentoId)->update(['estado_registro' => 'inactivo']);
        }

        Auditoria::registrar('cambió', 'cts', $l->id,
            "Anuló el depósito de la CTS de {$l->periodo} {$l->anio} de {$l->empleado->nombre} {$l->empleado->apellido}.");

        return response()->json(['success' => true, 'data' => ['message' => 'Depósito anulado.']]);
    }

    /** Lo que ganó mes por mes en el semestre (ver MotorCts::historial). */
    public function historial(Request $request, string $empleado)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorCts::PERIODOS)],
        ]);

        return response()->json(['success' => true, 'data' => (new MotorCts())->historial(
            Empleado::findOrFail($empleado), (int) $datos['anio'], $datos['periodo'],
        )]);
    }

    /**
     * GET|POST /cts/zip/link — el enlace para bajar en un .zip las hojas de
     * liquidación del depósito: de los marcados (ids) o de todos los que ya
     * tienen su CTS calculada o depositada. Quien todavía no se calculó no
     * tiene hoja.
     */
    public function enlaceZip(Request $request)
    {
        $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorCts::PERIODOS)],
            'ids'     => 'nullable|array|max:1000',
            'ids.*'   => 'uuid',
        ]);
        $cantidad = $this->liquidacionesDelZip($request, $this->marcadosDelPedido($request) ?: null)->count();
        if ($cantidad === 0) {
            return response()->json(['success' => false, 'message' => 'Todavía no hay hojas de CTS de ese depósito: primero calcula la CTS.'], 404);
        }

        return $this->enlaceAlZip($request, 'cts.zip', $request->only('anio', 'periodo'), $cantidad);
    }

    /** GET /cts/zip/download (firmado) — el .zip del enlace de arriba. */
    public function zipConEnlace(Request $request)
    {
        $this->exigirQuienPidioElZip($request, '/cts');
        $marcados = $this->marcadosDelPedido($request);
        if ($marcados === false) {
            abort(410, 'Este enlace venció: vuelve a pedir la descarga desde el sistema.');
        }

        $liquidaciones = $this->liquidacionesDelZip($request, $marcados);
        $hojas = [];
        foreach ($liquidaciones as $l) {
            $nombre = sprintf('CTS_%s_%d_%s_%s.pdf', $l->periodo, $l->anio, $l->empleado->dni,
                $this->nombreParaArchivo("{$l->empleado->apellido} {$l->empleado->nombre}"));
            $hojas[$nombre] = function () use ($l) {
                $guardada = $l->documento?->archivo;

                return $guardada && Storage::disk('local')->exists($guardada)
                    ? Storage::disk('local')->get($guardada)
                    : $this->pdf($l)->output();
            };
        }

        return $this->zipDeHojas($hojas, "CTS_{$request->query('periodo')}_{$request->query('anio')}.zip", 'Juntando las hojas de CTS',
            $this->marcadosSinHoja($marcados, $liquidaciones->pluck('empleado_id')->all(), 'su CTS todavía no se calculó, o no le corresponde.'));
    }

    private function liquidacionesDelZip(Request $request, ?array $marcados): Collection
    {
        return CtsLiquidacion::with('empleado.cargo', 'documento')
            ->where('anio', (int) $request->input('anio'))
            ->where('periodo', $request->input('periodo'))
            ->when($marcados !== null, fn ($q) => $q->whereIn('empleado_id', $marcados))
            ->get()
            ->sortBy(fn ($l) => "{$l->empleado->apellido} {$l->empleado->nombre}")
            ->values();
    }

    /** La hoja de liquidación: la guardada en el expediente o, si aún no se depositó, una al vuelo. */
    public function hoja(string $id)
    {
        $l = CtsLiquidacion::with('empleado.cargo', 'documento')->findOrFail($id);
        $nombre = "CTS_{$l->empleado->dni}_{$l->periodo}_{$l->anio}.pdf";

        if ($l->documento && Storage::disk('local')->exists($l->documento->archivo)) {
            return Storage::disk('local')->download($l->documento->archivo, $nombre);
        }

        return $this->pdf($l)->download($nombre);
    }

    private function pdf(CtsLiquidacion $l)
    {
        $semestre = MotorCts::semestre($l->anio, $l->periodo);

        return Pdf::loadView('cts', [
            'l'                => $l,
            'empleado'         => $l->empleado,
            'semestre'         => $semestre,
            'nombreAnio'       => ValorLegal::nombreDelAnio($l->anio),
            'firmaDigital'     => \App\Support\Firma\FirmaDigitalDeBoletas::activa(),
            'firmasRequeridas' => \App\Support\Firma\FirmaDigitalDeBoletas::requeridas(),
            'representante'    => $this->representante(),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Quien representa al colegio en la constancia: el del .env, o el del
     * certificado con que firma el colegio (el más reciente que esté puesto).
     *
     * @return array{nombre: string, dni: ?string}|null
     */
    private function representante(): ?array
    {
        if ($nombre = config('institucion.representante')) {
            return ['nombre' => $nombre, 'dni' => config('institucion.representante_dni')];
        }
        $certificado = \App\Models\CertificadoFirma::where('activo', true)->latest()->first();
        if (! $certificado) {
            return null;
        }

        // Llama.pe pega el RUC al nombre («… ISIDORO RUC:20…»): en la constancia sobra.
        return [
            'nombre' => trim((string) preg_replace('/\s*RUC\s*:?\s*\d{11}/i', '', $certificado->nombre)),
            'dni'    => $certificado->dni,
        ];
    }

    /**
     * Guarda la hoja en el disco privado y la deja en el expediente. Nunca en
     * el disco público: trae sueldo, DNI y cuenta bancaria.
     */
    private function guardarHoja(CtsLiquidacion $l): void
    {
        $mes = $l->periodo === 'mayo' ? 5 : 11;
        $ruta = "documentos/{$l->empleado_id}/cts/cts_{$l->anio}_{$l->periodo}.pdf";
        Storage::disk('local')->put($ruta, $this->pdf($l)->output());

        $documento = Documento::updateOrCreate(
            ['empleado_id' => $l->empleado_id, 'tipo' => 'cts', 'periodo_mes' => $mes, 'periodo_anio' => $l->anio],
            // Espera la firma digital del colegio: el trabajador la ve cuando esté firmada.
            ['archivo' => $ruta, 'estado_registro' => 'activo', 'estado_firma' => 'pendiente', ...FirmaDelColegio::camposDeEmision()],
        );

        $l->documento_id = $documento->id;
        $l->save();
    }
}
