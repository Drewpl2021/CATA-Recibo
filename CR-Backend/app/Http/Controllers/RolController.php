<?php
namespace App\Http\Controllers;
use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Rol::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:45',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $rol = Rol::create($request->all());
        return response()->json(['success' => true, 'data' => $rol], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Rol::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $rol = Rol::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:45',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $rol->update($request->all());
        return response()->json(['success' => true, 'data' => $rol]);
    }

    public function destroy(string $id)
    {
        Rol::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Rol eliminado.']]);
    }
}