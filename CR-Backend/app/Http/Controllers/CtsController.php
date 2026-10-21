<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\CtsLiquidacion;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\ValorLegal;
use App\Support\Cts\MotorCts;
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
 *   GET    /cts/{empleado}/history      lo que ganó mes por mes en el semestre, de sus planillas
 *
 * Ver App\Support\Cts\MotorCts para el cálculo.
 */
class CtsController extends Controller
{
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

        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre')
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
        ]]);
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

        return $this->personalDelSemestre($anio, $periodo)->map(function (Empleado $e) use ($motor, $anio, $periodo, $guardadas) {
            $previa = $motor->calcular($e, $anio, $periodo);
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
                'cargo_id'           => $e->cargo_id,
                'cargo'              => $e->cargo?->nombre,
                'area_id'            => $e->area_id,
                'area'               => $e->area?->nombre,
                'sede_id'            => $e->sede_id,
                'sede'               => $e->sede?->nombre,
                'estado'             => $e->estado,
                'fecha_ingreso'      => $e->fecha_ingreso,
                // La cuenta de su ficha, para proponerla al registrar el depósito.
                'entidad_ficha'      => $e->entidad_financiera,
                'cuenta_ficha'       => $e->numero_cuenta,
                ...$calculo,
                'aplica'             => $guardada ? true : $previa['aplica'],
                'estado_cts'         => $estado,
                'desactualizada'     => $estado === 'calculada' && abs($previa['monto'] - $guardada->monto) > 0.005,
                'fecha_deposito'     => $guardada?->fecha_deposito?->toDateString(),
                'entidad_financiera' => $guardada?->entidad_financiera,
                'numero_cuenta'      => $guardada?->numero_cuenta,
                'documento_id'       => $guardada?->documento_id,
            ];
        });
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
            'meses' => $l->meses, 'dias' => $l->dias, 'monto' => $l->monto,
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

            foreach ($this->personalDelSemestre($anio, $periodo) as $e) {
                $guardada = $guardadas->get($e->id);
                if ($guardada?->depositada()) {
                    $depositadas++;
                    continue;
                }

                $calculo = $motor->calcular($e, $anio, $periodo);
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
     * Si no se indica banco ni cuenta, se toma la de la ficha de cada uno.
     */
    public function depositar(Request $request)
    {
        $datos = $request->validate([
            'ids'                => 'required|array|min:1',
            'ids.*'              => 'uuid|exists:cts_liquidaciones,id',
            'fecha_deposito'     => 'required|date',
            'entidad_financiera' => 'nullable|string|max:100',
            'numero_cuenta'      => 'nullable|string|max:50',
        ]);
        // Una cuenta escrita a mano solo tiene sentido para una persona.
        if (! empty($datos['numero_cuenta']) && count($datos['ids']) > 1) {
            return response()->json(['success' => false, 'data' => [
                'message' => 'El número de cuenta se escribe solo cuando se registra el depósito de una persona.',
            ]], 422);
        }

        $liquidaciones = CtsLiquidacion::with('empleado.cargo')->whereIn('id', $datos['ids'])->get();
        $yaDepositadas = $liquidaciones->filter->depositada();
        if ($yaDepositadas->isNotEmpty()) {
            return response()->json(['success' => false, 'data' => [
                'message' => 'Hay CTS que ya estaban depositadas. Quítalas de la selección.',
            ]], 422);
        }

        DB::transaction(function () use ($liquidaciones, $datos) {
            foreach ($liquidaciones as $l) {
                $l->fill([
                    'estado'             => CtsLiquidacion::DEPOSITADA,
                    'fecha_deposito'     => $datos['fecha_deposito'],
                    'entidad_financiera' => $datos['entidad_financiera'] ?? $l->empleado->entidad_financiera,
                    'numero_cuenta'      => $datos['numero_cuenta'] ?? $l->empleado->numero_cuenta,
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
            return response()->json(['success' => false, 'data' => ['message' => 'Esta CTS no tiene un depósito registrado.']], 422);
        }

        $documentoId = $l->documento_id;
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
            'l'          => $l,
            'empleado'   => $l->empleado,
            'semestre'   => $semestre,
            'nombreAnio' => ValorLegal::nombreDelAnio($l->anio),
        ])->setPaper('a4', 'portrait');
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
            ['archivo' => $ruta, 'estado_registro' => 'activo', 'estado_firma' => 'pendiente'],
        );

        $l->documento_id = $documento->id;
        $l->save();
    }
}
