<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use Illuminate\Http\Request;

class PlanillaController extends Controller
{
    public function index(Request $request)
    {
        $query = Planilla::with('empleado');
        if ($request->has('empleado_id'))
            $query->where('empleado_id', $request->empleado_id);
        if ($request->has('mes'))
            $query->where('mes', $request->mes);
        if ($request->has('anio'))
            $query->where('anio', $request->anio);

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
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

        // Jalar sueldo_base directamente del empleado — no se puede editar
        $empleado       = Empleado::findOrFail($request->empleado_id);
        $sueldo_base    = (float) $empleado->sueldo_base;
        $bonificaciones = (float) ($request->bonificaciones ?? 0);
        $descuentos     = (float) ($request->descuentos ?? 0);
        $total          = $sueldo_base + $bonificaciones - $descuentos;

        $planilla = Planilla::create([
            'empleado_id'    => $request->empleado_id,
            'mes'            => $request->mes,
            'anio'           => $request->anio,
            'periodo_id'     => $request->periodo_id ?? null,
            'sueldo_base'    => $sueldo_base,
            'bonificaciones' => $bonificaciones,
            'descuentos'     => $descuentos,
            'total'          => $total,
        ]);

        return response()->json(['success' => true, 'data' => $planilla], 201);
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
        $sueldo_base    = (float) $planilla->sueldo_base;
        $bonificaciones = (float) ($request->bonificaciones ?? $planilla->bonificaciones);
        $descuentos     = (float) ($request->descuentos ?? $planilla->descuentos);
        $total          = $sueldo_base + $bonificaciones - $descuentos;

        $planilla->update([
            'bonificaciones' => $bonificaciones,
            'descuentos'     => $descuentos,
            'total'          => $total,
        ]);

        return response()->json(['success' => true, 'data' => $planilla]);
    }

    public function destroy(string $id)
    {
        $planilla = Planilla::findOrFail($id);
        $planilla->update(['estado_registro' => 'inactivo']);
        return response()->json(['success' => true, 'data' => ['message' => 'Planilla eliminada correctamente.']]);
    }
}