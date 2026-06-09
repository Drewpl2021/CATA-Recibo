<?php
namespace App\Http\Controllers;
use App\Models\Empleado;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::with('area', 'cargo')->get();
        return response()->json(['success' => true, 'data' => $empleados]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni'          => 'required|string|max:8|unique:empleados',
            'nombre'       => 'required|string|max:100',
            'apellido'     => 'required|string|max:100',
            'cargo_id'     => 'required|uuid|exists:cargos,id',
            'area_id'      => 'required|uuid|exists:areas,id',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'required|date',
            'estado'       => 'nullable|string|max:20',
        ]);

        $empleado = Empleado::create($request->all());
        $empleado->load('area', 'cargo');
        return response()->json(['success' => true, 'data' => $empleado], 201);
    }

    public function show(string $id)
    {
        $empleado = Empleado::with('area', 'cargo')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function update(Request $request, string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $request->validate([
            'dni'          => 'sometimes|string|max:8|unique:empleados,dni,'.$id,
            'nombre'       => 'sometimes|string|max:100',
            'apellido'     => 'sometimes|string|max:100',
            'cargo_id'     => 'sometimes|uuid|exists:cargos,id',
            'area_id'      => 'sometimes|uuid|exists:areas,id',
            'telefono'     => 'nullable|string|max:15',
            'direccion'    => 'nullable|string|max:255',
            'fecha_ingreso'=> 'sometimes|date',
            'estado'       => 'nullable|string|max:20',
        ]);

        $empleado->update($request->all());
        $empleado->load('area', 'cargo');
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function destroy(string $id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Empleado eliminado correctamente.']]);
    }
}