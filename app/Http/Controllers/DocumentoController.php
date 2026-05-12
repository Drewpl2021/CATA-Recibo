<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Documento::with('empleado');

        if ($request->has('empleado_id'))
            $query->where('empleado_id', $request->empleado_id);

        if ($request->has('tipo'))
            $query->where('tipo', $request->tipo);

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'tipo'        => 'required|string|max:50',
            'archivo'     => 'required|string|max:255',
            'firmado_por' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['codigo_firma'] = Str::upper(Str::random(10)) . '-' . now()->format('Ymd');
        $data['fecha_firma']  = now();

        $documento = Documento::create($data);

        return response()->json(['success' => true, 'data' => $documento], 201);
    }

    public function show(string $id)
    {
        $documento = Documento::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function update(Request $request, string $id)
    {
        $documento = Documento::findOrFail($id);

        $request->validate([
            'tipo'        => 'sometimes|string|max:50',
            'archivo'     => 'sometimes|string|max:255',
            'firmado_por' => 'nullable|string',
        ]);

        $documento->update($request->all());

        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function destroy(string $id)
    {
        $documento = Documento::findOrFail($id);
        $documento->delete();

        return response()->json(['success' => true, 'data' => ['message' => 'Documento eliminado correctamente.']]);
    }
}