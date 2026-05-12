<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::all();
        return response()->json(['success' => true, 'data' => $empleados]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni'          => 'required|string|max:8|unique:empleados',
            'nombre'       => 'required|string|max:100',
            'apellido'     => 'required|string|max:100',
            'cargo'        => 'required|string|max:100',
            'area'         => 'required|string|max:100',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'required|date',
        ]);

        $empleado = Empleado::create($request->all());

        return response()->json(['success' => true, 'data' => $empleado], 201);
    }

    public function show(string $id)
    {
        $empleado = Empleado::findOrFail($id);
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function update(Request $request, string $id)
    {
        $empleado = Empleado::findOrFail($id);

        $request->validate([
            'dni'          => 'sometimes|string|max:8|unique:empleados,dni,'.$id,
            'nombre'       => 'sometimes|string|max:100',
            'apellido'     => 'sometimes|string|max:100',
            'cargo'        => 'sometimes|string|max:100',
            'area'         => 'sometimes|string|max:100',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'sometimes|date',
        ]);

        $empleado->update($request->all());

        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function destroy(string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();

        return response()->json(['success' => true, 'data' => ['message' => 'Empleado eliminado correctamente.']]);
    }
}