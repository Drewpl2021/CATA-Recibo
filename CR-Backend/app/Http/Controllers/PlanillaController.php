<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Traits\CalculaConceptosPlanilla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PlanillaController extends Controller
{
    use ListadoPaginado;
    use CalculaConceptosPlanilla;
    /**
     * GET /planilla?empleado_id=&mes=&anio=&periodo_id=&page=&size=&search=
     *
     * Es la tabla que más crece del sistema: un registro por trabajador y
     * por mes. Los filtros van sobre el índice planilla_empleado_periodo_idx
     * / planilla_periodo_idx, y con ?page el corte lo hace el servidor.
     */
    public function index(Request $request)
    {
        $query = Planilla::with('empleado');

        if ($request->filled('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }
        if ($request->filled('mes')) {
            $query->where('mes', $request->mes);
        }
        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }
        if ($request->filled('periodo_id')) {
            $query->where('periodo_id', $request->periodo_id);
        }

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query->orderBy('anio', 'desc')->orderBy('mes', 'desc'),
            ['empleado.nombre', 'empleado.apellido', 'empleado.dni'],
            // La masa salarial es de TODAS las planillas que pasan el filtro,
            // no de las diez que se están viendo. reorder() quita el ORDER BY,
            // que en una consulta de suma no pinta nada y molesta a MySQL.
            fn (Builder $filtrada) => [
                'masaSalarial' => (float) $filtrada->reorder()->sum('total'),
            ]
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'    => 'required|exists:empleados,id',
            'mes'            => 'required|integer|min:1|max:12',
            'anio'           => 'required|integer|min:2000',
            'bonificaciones' => 'nullable|numeric|min:0',
            'descuentos'     => 'nullable|numeric|min:0',
            'periodo_id'     => 'nullable|uuid|exists:periodos,id',
        ]);

        $existe = Planilla::where('empleado_id', $request->empleado_id)
            ->where('mes', $request->mes)
            ->where('anio', $request->anio)
            ->first();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una planilla para este empleado en el periodo indicado.'
            ], 422);
        }

        // Jalar sueldo_base directamente del empleado — no se puede editar.
        // Si entró a mitad de mes se prorratea por los días que le tocan: darle
        // el mes entero a quien empezó el día 28 es pagarle de más.
        $empleado    = Empleado::findOrFail($request->empleado_id);
        $sueldo_base = $this->sueldoDelMes($empleado, (int) $request->mes, (int) $request->anio);

        if ($sueldo_base === null) {
            return response()->json([
                'success' => false,
                'message' => 'Este empleado todavía no había ingresado en ' . $request->mes . '/' . $request->anio .
                             ' (ingresó el ' . \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') . ').',
            ], 422);
        }

        $bonificaciones = (float) ($request->bonificaciones ?? 0);
        $descuentos     = (float) ($request->descuentos ?? 0);

        $planilla = Planilla::create([
            'empleado_id'    => $request->empleado_id,
            'mes'            => $request->mes,
            'anio'           => $request->anio,
            'periodo_id'     => $request->periodo_id ?? null,
            'sueldo_base'    => $sueldo_base,
            'bonificaciones' => $bonificaciones,
            'descuentos'     => $descuentos,
            'total'          => 0, // se recalcula abajo (aún no tiene PayrollDetalle, pero centraliza la fórmula)
        ]);

        $this->generarConceptosAutomaticos($planilla, $empleado);
        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $planilla->load('payrollDetalles.paymentConcept')], 201);
    }

    public function show(string $id)
    {
        $planilla = Planilla::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $planilla]);
    }

    public function update(Request $request, string $id)
    {
        $planilla = Planilla::findOrFail($id);
        $request->validate([
            'bonificaciones' => 'nullable|numeric|min:0',
            'descuentos'     => 'nullable|numeric|min:0',
        ]);

        // sueldo_base NO se puede editar — siempre viene del empleado
        $bonificaciones = (float) ($request->bonificaciones ?? $planilla->bonificaciones);
        $descuentos     = (float) ($request->descuentos ?? $planilla->descuentos);

        $planilla->update([
            'bonificaciones' => $bonificaciones,
            'descuentos'     => $descuentos,
        ]);

        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $planilla]);
    }

    public function destroy(string $id)
    {
        $planilla = Planilla::findOrFail($id);
        $planilla->update(['estado_registro' => 'inactivo']);
        return response()->json(['success' => true, 'data' => ['message' => 'Planilla eliminada correctamente.']]);
    }
}