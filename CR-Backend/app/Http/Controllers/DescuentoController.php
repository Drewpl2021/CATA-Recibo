<?php

namespace App\Http\Controllers;

use App\Models\Descuento;
use Illuminate\Http\Request;

class DescuentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Descuento::with('empleado');

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
            'empleado_id' => 'required|exists:empleados,id',
            'tipo'        => 'required|string|max:100',
            'monto'       => 'required|numeric|min:0',
            'mes'         => 'required|integer|min:1|max:12',
            'anio'        => 'required|integer|min:2000',
        ]);

        $descuento = Descuento::create($request->all());

        return response()->json(['success' => true, 'data' => $descuento], 201);
    }

    public function show($id)
    {
        $descuento = Descuento::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $descuento]);
    }

    public function update(Request $request, $id)
    {
        $descuento = Descuento::findOrFail($id);

        $request->validate([
            'tipo'  => 'sometimes|string|max:100',
            'monto' => 'sometimes|numeric|min:0',
            'mes'   => 'sometimes|integer|min:1|max:12',
            'anio'  => 'sometimes|integer|min:2000',
        ]);

        $descuento->update($request->all());

        return response()->json(['success' => true, 'data' => $descuento]);
    }

    public function destroy($id)
        {
            $descuento = Descuento::findOrFail($id);
            $descuento->update(['estado_registro' => 'inactivo']);

            return response()->json(['success' => true, 'data' => ['message' => 'Descuento eliminado correctamente.']]);
        }
}