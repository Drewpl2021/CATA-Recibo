<?php
namespace App\Http\Controllers;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Area::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $area = Area::create($request->all());
        return response()->json(['success' => true, 'data' => $area], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Area::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $area = Area::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $area->update($request->all());
        return response()->json(['success' => true, 'data' => $area]);
    }

    public function destroy(string $id)
    {
        Area::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Area eliminada.']]);
    }
}