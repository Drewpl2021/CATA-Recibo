<?php
namespace App\Http\Controllers;
use App\Models\Periodo;
use App\Traits\GeneraPlanillasEnLote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PeriodoController extends Controller
{
    use ListadoPaginado;
    use GeneraPlanillasEnLote;

    /**
     * GET /periodos
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios). Con ?page&size la corta el servidor y manda
     * además el total, para que el frontend no tenga que traerse todo
     * para saber cuántos hay. Ver App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            Periodo::query()->orderBy('fecha_inicio', 'desc'),
            ['nombre']
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:45',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);
        $periodo = Periodo::create($datos);
        return response()->json(['success' => true, 'data' => $periodo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Periodo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $periodo = Periodo::findOrFail($id);
        $datos = $request->validate([
            'nombre' => 'sometimes|string|max:45',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);
        $periodo->update($datos);
        return response()->json(['success' => true, 'data' => $periodo]);
    }

    public function destroy(string $id)
    {
        Periodo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Periodo eliminado.']]);
    }

    /**
     * Genera la Planilla del mes/año indicado para TODOS los empleados activos,
     * bajo este Periodo. Es idempotente: si un empleado ya tiene planilla de ese
     * mes, se omite (no falla el resto de la corrida). Cada planilla generada
     * dispara sus conceptos automáticos (pensión, EsSalud, fijos del catálogo,
     * Renta 5ta) tal como una planilla creada individualmente.
     */
    public function generarPlanilla(Request $request, string $id)
    {
        $periodo = Periodo::findOrFail($id);

        $request->validate(array_merge([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000',
        ], $this->reglasDelGrupo()));

        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;

        $fechaDelMes   = Carbon::create($anio, $mes, 1);
        $inicioPeriodo = Carbon::parse($periodo->fecha_inicio)->startOfMonth();
        $finPeriodo    = Carbon::parse($periodo->fecha_fin)->endOfMonth();

        if ($fechaDelMes->lt($inicioPeriodo) || $fechaDelMes->gt($finPeriodo)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "El mes {$mes}/{$anio} no cae dentro del rango de este periodo ({$periodo->fecha_inicio} a {$periodo->fecha_fin})."],
            ], 422);
        }

        $empleados = $this->empleadosDelGrupo($request);

        if ($empleados->isEmpty()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Ningún empleado activo coincide con el grupo indicado.'],
            ], 422);
        }

        $resultado = $this->generarLote($empleados, $mes, $anio, $periodo->id);

        return response()->json([
            'success' => true,
            'data'    => [
                'periodo' => $periodo->nombre,
                'mes'     => $mes,
                'anio'    => $anio,
                'resumen' => [
                    'generadas' => $resultado['generadas'],
                    'omitidas'  => $resultado['omitidas'],
                    'evaluados' => $resultado['evaluados'],
                ],
                'detalle' => $resultado['detalle'],
            ],
        ]);
    }
}
