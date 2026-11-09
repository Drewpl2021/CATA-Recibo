<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\ValorLegal;
use App\Support\FirmaDelColegio;
use App\Support\Gratificacion\MotorGratificacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * El módulo «Gratificaciones»: la de Fiestas Patrias (julio) y la de Navidad
 * (diciembre), con su bonificación extraordinaria.
 *
 *   GET  /bonuses?anio=&periodo=              cada trabajador: lo que corresponde y lo que tiene su planilla
 *   GET  /bonuses/{empleado}/history          el semestre mes a mes, de sus planillas
 *   GET  /bonuses/{empleado}/sheet            la hoja de gratificación en PDF
 *   GET  /bonuses/export                      la lista en Excel, con los filtros de la pantalla
 *   POST /bonuses/deliver                     dejar la hoja en el expediente de varios
 *   POST /bonuses/sign-here                   «Firmar aquí»: la firma digital del colegio en las hojas
 *
 * La hoja se firma digitalmente por el colegio, como la boleta
 * (App\Support\FirmaDelColegio): el trabajador la ve en Mis Documentos
 * recién cuando tiene las firmas que pide el colegio. Una vez firmada no se
 * vuelve a generar.
 *
 * La gratificación se paga en la planilla (y sale en la boleta): esto no
 * guarda montos propios, los compara. Ver MotorGratificacion.
 */
class GratificacionController extends Controller
{
    use \App\Traits\DescargaEnZip;

    /** El año y el periodo pedidos; sin ellos, la próxima gratificación. */
    private function periodoPedido(Request $request): array
    {
        $datos = $request->validate([
            'anio'    => 'nullable|integer|min:2000|max:2100',
            'periodo' => ['nullable', Rule::in(MotorGratificacion::PERIODOS)],
        ]);

        $hoy = now();
        $porDefecto = [(int) $hoy->year, $hoy->month <= 7 ? 'julio' : 'diciembre'];

        return [(int) ($datos['anio'] ?? $porDefecto[0]), $datos['periodo'] ?? $porDefecto[1]];
    }

    /** Quien pudo trabajar en el semestre: los activos y los que cesaron dentro o después de él. */
    private function personalDelSemestre(int $anio, string $periodo): Collection
    {
        $semestre = MotorGratificacion::semestre($anio, $periodo);

        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre', 'foto')
            ->where('fecha_ingreso', '<=', $semestre['hasta']->toDateString())
            ->where(fn ($q) => $q->where('estado', 'activo')->orWhere('fecha_cese', '>=', $semestre['desde']->toDateString()))
            ->orderBy('apellido')->orderBy('nombre')->get();
    }

    public function index(Request $request)
    {
        [$anio, $periodo] = $this->periodoPedido($request);
        $semestre = MotorGratificacion::semestre($anio, $periodo);
        $filas = $this->filas($anio, $periodo);
        $conDerecho = $filas->where('aplica', true);

        return response()->json(['success' => true, 'data' => [
            'anio'    => $anio,
            'anios'   => \App\Support\AniosConDatos::gratificaciones($anio),
            'periodo' => $periodo,
            'desde'   => $semestre['desde']->toDateString(),
            'hasta'   => $semestre['hasta']->toDateString(),
            'limite'  => $semestre['limite']->toDateString(),
            'filas'   => $filas,
            'resumen' => [
                'trabajadores'   => $filas->count(),
                'con_derecho'    => $conDerecho->count(),
                'pagadas'        => $filas->where('estado_grati', MotorGratificacion::PAGADA)->count(),
                'por_pagar'      => $filas->where('estado_grati', MotorGratificacion::POR_PAGAR)->count(),
                'diferencias'    => $filas->where('estado_grati', MotorGratificacion::DIFERENCIA)->count(),
                'truncas'        => $filas->where('trunca', true)->count(),
                'gratificacion'  => round($conDerecho->sum('gratificacion'), 2),
                'bonificacion'   => round($conDerecho->sum('bonificacion_extraordinaria'), 2),
                'total'          => round($conDerecho->sum('total'), 2),
                'pagado'         => round($filas->sum('pagado_total'), 2),
                'entregadas'     => $filas->whereNotNull('documento_id')->count(),
            ],
            // Cómo va la firma digital del colegio en las hojas del semestre.
            'firma'   => FirmaDelColegio::resumen($request, $this->hojasDelSemestre($anio, $periodo)),
        ]]);
    }

    /** Las hojas del semestre que están en el expediente. La trunca va con el mes del cese: el semestre entero. */
    private function hojasDelSemestre(int $anio, string $periodo): \Illuminate\Database\Eloquent\Builder
    {
        $semestre = MotorGratificacion::semestre($anio, $periodo);

        return Documento::where('tipo', 'gratificacion')->where('estado_registro', 'activo')->where('periodo_anio', $anio)
            ->whereBetween('periodo_mes', [$semestre['desde']->month, $semestre['mes_pago']]);
    }

    /**
     * POST /bonuses/sign-here — «Firmar aquí»: firma una tanda de las hojas
     * del semestre que esperan la firma de quien lo pide, con su
     * certificado. La que queda completa le llega al trabajador.
     */
    public function firmarAqui(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorGratificacion::PERIODOS)],
        ]);
        $grati = "{$datos['periodo']} {$datos['anio']}";

        return FirmaDelColegio::firmarTanda(
            $request,
            $this->hojasDelSemestre((int) $datos['anio'], $datos['periodo']),
            "hojas de gratificación de {$grati}",
            fn (Documento $d) => "Gratificación de {$grati}",
            fn (Documento $d) => FirmaDelColegio::avisarAlTrabajador($d, 'hoja de gratificación', $grati),
            fn (Documento $d) => null,
            fn (int $firmadas, string $detalle) => Auditoria::registrar('firmó', 'gratificación', null,
                "Firmó desde el sistema {$firmadas} hoja(s) de gratificación de {$grati} con su certificado ({$detalle}).",
                ['firmadas' => $firmadas]),
        );
    }

    /** Una fila por trabajador del semestre: lo que le corresponde, lo que tiene su planilla y su hoja. */
    private function filas(int $anio, string $periodo): Collection
    {
        $motor = new MotorGratificacion();
        $hojas = $this->hojasDelSemestre($anio, $periodo)->get(['id', 'empleado_id', 'firma_colegio'])->keyBy('empleado_id');

        return $this->personalDelSemestre($anio, $periodo)->map(fn (Empleado $e) => (fn (array $c) => [
            'id'        => $e->id,
            'dni'       => $e->dni,
            'nombre'    => trim("{$e->apellido} {$e->nombre}"),
            'foto'      => $e->foto,
            'apellido'  => $e->apellido,
            'nombres'   => $e->nombre,
            'cargo_id'  => $e->cargo_id,
            'cargo'     => $e->cargo?->nombre,
            'area_id'   => $e->area_id,
            'area'      => $e->area?->nombre,
            'sede_id'   => $e->sede_id,
            'sede'      => $e->sede?->nombre,
            'fecha_ingreso' => $e->fecha_ingreso,
            'fecha_cese'    => $e->estado === 'inactivo' ? $e->fecha_cese : null,
            ...$c,
            // El del trabajador (activo/inactivo) y el de su gratificación, cada uno con su nombre.
            'estado'       => $e->estado,
            'estado_grati' => $c['estado'],
            'documento_id' => $hojas->get($e->id)?->id,
            // pendiente | parcial | completa; null si no hay hoja o no va por firma digital.
            'firma_colegio' => $hojas->get($e->id)?->firma_colegio,
        ])($motor->calcular($e, $anio, $periodo)))->values();
    }

    /**
     * GET /bonuses/export — la lista del semestre en Excel, con los mismos
     * filtros que la pantalla (situación, sede, área, cargo y búsqueda): lo
     * que corresponde, lo que tiene su planilla, la diferencia y su hoja.
     */
    public function exportar(Request $request)
    {
        [$anio, $periodo] = $this->periodoPedido($request);
        $f = $request->validate(self::REGLAS_FILTRO + [
            'situacion' => 'nullable|in:pagada,por_pagar,diferencia,no_corresponde,trunca',
        ]);
        $todas = $this->filas($anio, $periodo);
        $filas = \App\Support\FiltroDeLista::aplicar($todas, $f, fn (array $x, string $s) => $s === 'trunca' ? (bool) $x['trunca'] : $x['estado_grati'] === $s);

        $estados = ['pagada' => 'Pagada', 'por_pagar' => 'Por pagar', 'diferencia' => 'Con diferencia', 'no_corresponde' => 'No corresponde'];
        $firma = ['pendiente' => 'Por firmar', 'parcial' => 'Una firma de dos', 'completa' => 'Firmada y entregada'];
        $titulo = 'Gratificación de ' . $periodo . ' ' . $anio;
        $fecha = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';

        $hoja = [
            [$titulo],
            [\App\Support\FiltroDeLista::describir($f, $todas, ['trunca' => 'Truncas (cesaron)'] + $estados) . ' · ' . $filas->count() . ' de ' . $todas->count() . ' trabajador(es)'],
            [''],
            ['N°', 'DNI', 'Apellidos y nombres', 'Cargo', 'Área', 'Sede', 'Estado', 'Ingreso', 'Cese', 'Meses',
                'Sueldo', 'Asignación familiar', 'Bonificación por cargo', 'Remuneración computable',
                'Gratificación', 'Bonificación 9%', 'Le corresponde', 'En su planilla', 'Diferencia', 'Situación', 'Hoja'],
        ];
        foreach ($filas->values() as $n => $x) {
            $hoja[] = [$n + 1, (string) $x['dni'], $x['nombre'], $x['cargo'], $x['area'], $x['sede'],
                $x['estado'] === 'activo' ? 'Activo' : 'Cesado', $fecha($x['fecha_ingreso']), $fecha($x['fecha_cese']),
                $x['aplica'] ? $x['meses'] : null,
                $x['aplica'] ? $x['sueldo'] : null, $x['aplica'] ? $x['asignacion_familiar'] : null, $x['aplica'] ? $x['bonificacion_cargo'] : null,
                $x['aplica'] ? $x['remuneracion_computable'] : null,
                $x['aplica'] ? $x['gratificacion'] : null, $x['aplica'] ? $x['bonificacion_extraordinaria'] : null, $x['aplica'] ? $x['total'] : null,
                $x['pagado_total'], $x['diferencia'],
                ($estados[$x['estado_grati']] ?? $x['estado_grati']) . ($x['trunca'] ? ' (trunca)' : ''),
                $x['firma_colegio'] ? $firma[$x['firma_colegio']] : ($x['documento_id'] ? 'Entregada' : '')];
        }
        $montos = [10, 11, 12, 13, 14, 15, 16, 17, 18];
        $total = array_fill(0, 21, '');
        $total[2] = 'TOTAL';
        foreach ([14 => 'gratificacion', 15 => 'bonificacion_extraordinaria', 16 => 'total'] as $c => $campo) {
            $total[$c] = round($filas->where('aplica', true)->sum($campo), 2);
        }
        $total[17] = round($filas->sum('pagado_total'), 2);
        $total[18] = round($filas->sum('diferencia'), 2);
        $hoja[] = $total;

        $estiloTotal = array_fill(0, 21, \App\Support\LibroExcel::TOTAL_TEXTO);
        foreach ($montos as $c) {
            $estiloTotal[$c] = \App\Support\LibroExcel::TOTAL_MONTO;
        }
        $libro = new \App\Support\LibroExcel();
        $libro->hoja('Gratificación', $hoja, [
            'anchos' => [6, 12, 36, 26, 22, 18, 10, 12, 12, 8, 12, 12, 12, 14, 13, 13, 14, 14, 12, 18, 20],
            'estiloColumnas' => [1 => \App\Support\LibroExcel::TEXTO] + array_fill_keys($montos, \App\Support\LibroExcel::MONTO),
            'estiloFilas' => [0 => \App\Support\LibroExcel::ENCABEZADO, 1 => \App\Support\LibroExcel::PARRAFO,
                3 => array_fill(0, 21, \App\Support\LibroExcel::TITULO), count($hoja) - 1 => $estiloTotal],
        ]);

        return $libro->descargar($titulo . (array_filter($f) ? ' (filtrado)' : '') . '.xlsx');
    }

    /** Los filtros de la lista que también entiende el Excel. */
    private const REGLAS_FILTRO = [
        'sede_id'  => 'nullable|string|max:64',
        'area_id'  => 'nullable|string|max:64',
        'cargo_id' => 'nullable|string|max:64',
        'search'   => 'nullable|string|max:100',
    ];

    /** El semestre mes a mes (ver MotorGratificacion::historial). */
    public function historial(Request $request, string $empleado)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorGratificacion::PERIODOS)],
        ]);

        return response()->json(['success' => true, 'data' => (new MotorGratificacion())->historial(
            Empleado::findOrFail($empleado), (int) $datos['anio'], $datos['periodo'],
        )]);
    }

    /**
     * GET|POST /bonuses/zip/link — el enlace para bajar en un .zip las hojas
     * de gratificación del periodo: de los marcados (ids) o de todos a los
     * que les corresponde.
     */
    public function enlaceZip(Request $request)
    {
        $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorGratificacion::PERIODOS)],
            'ids'     => 'nullable|array|max:1000',
            'ids.*'   => 'uuid',
        ]);
        $cantidad = count($this->conGratificacion($request, $this->marcadosDelPedido($request) ?: null));
        if ($cantidad === 0) {
            return response()->json(['success' => false, 'message' => 'Nadie de la selección tiene gratificación en ese periodo.'], 404);
        }

        return $this->enlaceAlZip($request, 'gratificaciones.zip', $request->only('anio', 'periodo'), $cantidad);
    }

    /** GET /bonuses/zip/download (firmado) — el .zip del enlace de arriba. */
    public function zipConEnlace(Request $request)
    {
        $this->exigirQuienPidioElZip($request, '/gratificaciones');
        $marcados = $this->marcadosDelPedido($request);
        if ($marcados === false) {
            abort(410, 'Este enlace venció: vuelve a pedir la descarga desde el sistema.');
        }

        $anio = (int) $request->query('anio');
        $periodo = (string) $request->query('periodo');
        $semestre = MotorGratificacion::semestre($anio, $periodo);
        // La entregada al expediente es la que vio el trabajador: esa va tal cual.
        $entregadas = Documento::where('tipo', 'gratificacion')->where('estado_registro', 'activo')->where('periodo_anio', $anio)
            ->whereBetween('periodo_mes', [$semestre['desde']->month, $semestre['mes_pago']])
            ->pluck('archivo', 'empleado_id');

        $conGratificacion = $this->conGratificacion($request, $marcados);
        $hojas = [];
        foreach ($conGratificacion as [$e, $calculo]) {
            $nombre = sprintf('Gratificacion_%s_%d_%s_%s.pdf', $periodo, $anio, $e->dni, $this->nombreParaArchivo("{$e->apellido} {$e->nombre}"));
            $hojas[$nombre] = function () use ($e, $anio, $periodo, $calculo, $entregadas) {
                $guardada = $entregadas->get($e->id);

                return $guardada && Storage::disk('local')->exists($guardada)
                    ? Storage::disk('local')->get($guardada)
                    : $this->pdf($e, $anio, $periodo, $calculo)->output();
            };
        }

        return $this->zipDeHojas($hojas, "Gratificaciones_{$periodo}_{$anio}.zip", 'Juntando las hojas de gratificación',
            $this->marcadosSinHoja($marcados, array_map(fn ($par) => $par[0]->id, $conGratificacion), 'no le corresponde gratificación en este periodo.'));
    }

    /** [[Empleado, cálculo], …] de quienes tienen gratificación en el periodo, por apellido. */
    private function conGratificacion(Request $request, ?array $marcados): array
    {
        $anio = (int) $request->input('anio');
        $periodo = (string) $request->input('periodo');
        $motor = new MotorGratificacion();

        return $this->personalDelSemestre($anio, $periodo)
            ->when($marcados !== null, fn ($c) => $c->whereIn('id', $marcados))
            ->sortBy(fn (Empleado $e) => "{$e->apellido} {$e->nombre}")
            ->map(fn (Empleado $e) => [$e, $motor->calcular($e, $anio, $periodo)])
            ->filter(fn (array $par) => $par[1]['aplica'])
            ->values()
            ->all();
    }

    /** La hoja: la que ya está en el expediente o, si no, una al vuelo. */
    public function hoja(Request $request, string $empleado)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorGratificacion::PERIODOS)],
        ]);
        $e = Empleado::with('cargo')->findOrFail($empleado);
        $anio = (int) $datos['anio'];
        $calculo = (new MotorGratificacion())->calcular($e, $anio, $datos['periodo']);
        if (! $calculo['aplica']) {
            return response()->json(['success' => false, 'data' => ['message' => $calculo['motivo'] ?? 'No le corresponde gratificación.']], 422);
        }

        $nombre = "Gratificacion_{$e->dni}_{$datos['periodo']}_{$anio}.pdf";
        // La del expediente, tal como se entregó (y firmó); si aún no, una al vuelo.
        $guardada = $this->hojasDelSemestre($anio, $datos['periodo'])->where('empleado_id', $e->id)->value('archivo');
        if ($guardada && Storage::disk('local')->exists($guardada)) {
            return Storage::disk('local')->download($guardada, $nombre);
        }

        return $this->pdf($e, $anio, $datos['periodo'], $calculo)->download($nombre);
    }

    /**
     * Deja la hoja en el expediente de cada uno (tipo «gratificacion»), donde
     * el trabajador la ve en Mis Documentos y da su conformidad. Solo de
     * quien ya la tiene pagada en su planilla: una hoja de algo que no se
     * pagó no constata nada.
     */
    public function entregar(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'periodo' => ['required', Rule::in(MotorGratificacion::PERIODOS)],
            'ids'     => 'required|array|min:1|max:1000',
            'ids.*'   => 'uuid|exists:empleados,id',
        ]);
        $anio = (int) $datos['anio'];
        $periodo = $datos['periodo'];
        $motor = new MotorGratificacion();

        $entregadas = 0;
        $saltadas = [];
        $firmadas = 0;
        // La que ya tiene la firma del colegio está sellada: no se vuelve a generar.
        $selladas = $this->hojasDelSemestre($anio, $periodo)->whereIn('firma_colegio', ['parcial', 'completa'])->pluck('empleado_id')->flip();
        foreach (Empleado::with('cargo')->whereIn('id', $datos['ids'])->get() as $e) {
            if ($selladas->has($e->id)) {
                $firmadas++;
                continue;
            }
            $calculo = $motor->calcular($e, $anio, $periodo);
            if ($calculo['estado'] !== MotorGratificacion::PAGADA) {
                $saltadas[] = trim("{$e->nombre} {$e->apellido}");
                continue;
            }

            $ruta = "documentos/{$e->id}/gratificacion/gratificacion_{$anio}_{$periodo}.pdf";
            Storage::disk('local')->put($ruta, $this->pdf($e, $anio, $periodo, $calculo)->output());
            Documento::updateOrCreate(
                ['empleado_id' => $e->id, 'tipo' => 'gratificacion', 'periodo_mes' => $calculo['mes_planilla'], 'periodo_anio' => $anio],
                // Espera la firma digital del colegio: el trabajador la ve cuando esté firmada.
                ['archivo' => $ruta, 'estado_registro' => 'activo', 'estado_firma' => 'pendiente', ...FirmaDelColegio::camposDeEmision()],
            );
            $entregadas++;
        }

        Auditoria::registrar('aplicó', 'gratificación', null,
            "Dejó la hoja de la gratificación de {$periodo} {$anio} en el expediente de {$entregadas} trabajador(es).",
            ['entregadas' => $entregadas, 'saltadas' => count($saltadas)]);

        return response()->json(['success' => true, 'data' => [
            'entregadas' => $entregadas,
            'saltadas'   => $saltadas,
            'message'    => "Se dejó la hoja en el expediente de {$entregadas} trabajador(es): ahora falta firmarlas."
                . ($firmadas ? " {$firmadas} ya estaban firmadas por el colegio y no se tocaron." : '')
                . ($saltadas ? ' ' . count($saltadas) . ' se saltaron: su gratificación todavía no está pagada en su planilla, o no coincide con la ley.' : ''),
        ]]);
    }

    private function pdf(Empleado $e, int $anio, string $periodo, array $calculo)
    {
        return Pdf::loadView('gratificacion', [
            'e'          => $e,
            'c'          => $calculo,
            'anio'       => $anio,
            'periodo'    => $periodo,
            'semestre'   => MotorGratificacion::semestre($anio, $periodo),
            'nombreAnio'       => ValorLegal::nombreDelAnio($anio),
            'firmaDigital'     => \App\Support\FirmaDigitalDeBoletas::activa(),
            'firmasRequeridas' => \App\Support\FirmaDigitalDeBoletas::requeridas(),
        ])->setPaper('a4', 'portrait');
    }
}
