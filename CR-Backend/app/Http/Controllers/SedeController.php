<?php
namespace App\Http\Controllers;
use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Sede::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:15',
            'estado'    => 'nullable|string|in:activo,inactivo',
        ]);
        $sede = Sede::create($request->all());
        return response()->json(['success' => true, 'data' => $sede], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Sede::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $sede = Sede::findOrFail($id);
        $request->validate([
            'nombre'    => 'sometimes|string|max:100',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:15',
            'estado'    => 'nullable|string|in:activo,inactivo',
        ]);
        $sede->update($request->all());
        return response()->json(['success' => true, 'data' => $sede]);
    }

    public function destroy(string $id)
    {
        Sede::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Sede eliminada.']]);
    }
}