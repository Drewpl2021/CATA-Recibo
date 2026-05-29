<?php
namespace App\Http\Controllers;
use App\Models\Periodo;
use Illuminate\Http\Request;

class PeriodoController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Periodo::all()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:45',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);
        $periodo = Periodo::create($request->all());
        return response()->json(['success' => true, 'data' => $periodo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Periodo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $periodo = Periodo::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:45',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);
        $periodo->update($request->all());
        return response()->json(['success' => true, 'data' => $periodo]);
    }

    public function destroy(string $id)
    {
        Periodo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Periodo eliminado.']]);
    }
}