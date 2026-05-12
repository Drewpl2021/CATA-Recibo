<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
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

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'   => 'required|exists:empleados,id',
            'mes'           => 'required|integer|min:1|max:12',
            'anio'          => 'required|integer|min:2000',
            'sueldo_base'   => 'required|numeric|min:0',
            'bonificaciones'=> 'nullable|numeric|min:0',
            'descuentos'    => 'nullable|numeric|min:0',
        ]);

        $data = $request->all();
        $data['total'] = ($data['sueldo_base'] + ($data['bonificaciones'] ?? 0)) - ($data['descuentos'] ?? 0);

        $planilla = Planilla::create($data);

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
            'sueldo_base'   => 'sometimes|numeric|min:0',
            'bonificaciones'=> 'nullable|numeric|min:0',
            'descuentos'    => 'nullable|numeric|min:0',
        ]);

        $data = $request->all();
        $data['total'] = (($data['sueldo_base'] ?? $planilla->sueldo_base) + ($data['bonificaciones'] ?? $planilla->bonificaciones)) - ($data['descuentos'] ?? $planilla->descuentos);

        $planilla->update($data);

        return response()->json(['success' => true, 'data' => $planilla]);
    }

    public function destroy(string $id)
    {
        $planilla = Planilla::findOrFail($id);
        $planilla->delete();

        return response()->json(['success' => true, 'data' => ['message' => 'Planilla eliminada correctamente.']]);
    }
}