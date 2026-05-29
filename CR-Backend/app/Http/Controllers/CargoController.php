<?php
namespace App\Http\Controllers;
use App\Models\Cargo;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Cargo::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $cargo = Cargo::create($request->all());
        return response()->json(['success' => true, 'data' => $cargo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Cargo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $cargo = Cargo::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $cargo->update($request->all());
        return response()->json(['success' => true, 'data' => $cargo]);
    }

    public function destroy(string $id)
    {
        Cargo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Cargo eliminado.']]);
    }
}