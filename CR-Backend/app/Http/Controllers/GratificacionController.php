<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\ValorLegal;
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
 *   POST /bonuses/deliver                     dejar la hoja en el expediente de varios
 *
 * La gratificación se paga en la planilla (y sale en la boleta): esto no
 * guarda montos propios, los compara. Ver MotorGratificacion.
 */
class GratificacionController extends Controller
{
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

        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre')
            ->where('fecha_ingreso', '<=', $semestre['hasta']->toDateString())
            ->where(fn ($q) => $q->where('estado', 'activo')->orWhere('fecha_cese', '>=', $semestre['desde']->toDateString()))
            ->orderBy('apellido')->orderBy('nombre')->get();
    }

    public function index(Request $request)
    {
        [$anio, $periodo] = $this->periodoPedido($request);
        $semestre = MotorGratificacion::semestre($anio, $periodo);
        $motor = new MotorGratificacion();
        // La trunca se entrega con el mes del cese: se buscan las del semestre entero.
        $hojas = Documento::where('tipo', 'gratificacion')->where('estado_registro', 'activo')->where('periodo_anio', $anio)
            ->whereBetween('periodo_mes', [$semestre['desde']->month, $semestre['mes_pago']])
            ->pluck('id', 'empleado_id');

        $filas = $this->personalDelSemestre($anio, $periodo)->map(fn (Empleado $e) => (fn (array $c) => [
            'id'        => $e->id,
            'dni'       => $e->dni,
            'nombre'    => trim("{$e->apellido} {$e->nombre}"),
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
            'documento_id' => $hojas->get($e->id),
        ])($motor->calcular($e, $anio, $periodo)))->values();

        $conDerecho = $filas->where('aplica', true);

        return response()->json(['success' => true, 'data' => [
            'anio'    => $anio,
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
        ]]);
    }

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

        return $this->pdf($e, $anio, $datos['periodo'], $calculo)
            ->download("Gratificacion_{$e->dni}_{$datos['periodo']}_{$anio}.pdf");
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
        foreach (Empleado::with('cargo')->whereIn('id', $datos['ids'])->get() as $e) {
            $calculo = $motor->calcular($e, $anio, $periodo);
            if ($calculo['estado'] !== MotorGratificacion::PAGADA) {
                $saltadas[] = trim("{$e->nombre} {$e->apellido}");
                continue;
            }

            $ruta = "documentos/{$e->id}/gratificacion/gratificacion_{$anio}_{$periodo}.pdf";
            Storage::disk('local')->put($ruta, $this->pdf($e, $anio, $periodo, $calculo)->output());
            Documento::updateOrCreate(
                ['empleado_id' => $e->id, 'tipo' => 'gratificacion', 'periodo_mes' => $calculo['mes_planilla'], 'periodo_anio' => $anio],
                ['archivo' => $ruta, 'estado_registro' => 'activo', 'estado_firma' => 'pendiente'],
            );
            $entregadas++;
        }

        Auditoria::registrar('aplicó', 'gratificación', null,
            "Dejó la hoja de la gratificación de {$periodo} {$anio} en el expediente de {$entregadas} trabajador(es).",
            ['entregadas' => $entregadas, 'saltadas' => count($saltadas)]);

        return response()->json(['success' => true, 'data' => [
            'entregadas' => $entregadas,
            'saltadas'   => $saltadas,
            'message'    => "Se dejó la hoja en el expediente de {$entregadas} trabajador(es)."
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
            'nombreAnio' => ValorLegal::nombreDelAnio($anio),
        ])->setPaper('a4', 'portrait');
    }
}
