<?php
namespace App\Http\Controllers;
use App\Models\PaymentConcept;
use Illuminate\Http\Request;

class PaymentConceptController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentConcept::query();
        if ($request->has('tipo'))
            $query->where('tipo', $request->tipo);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:45',
            'tipo' => 'required|in:bonificacion,descuento',
            'calculo' => 'nullable|in:fijo,porcentaje',
            'valor' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $concept = PaymentConcept::create($request->all());
        return response()->json(['success' => true, 'data' => $concept], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => PaymentConcept::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $concept = PaymentConcept::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:45',
            'tipo' => 'sometimes|in:bonificacion,descuento',
            'calculo' => 'nullable|in:fijo,porcentaje',
            'valor' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $concept->update($request->all());
        return response()->json(['success' => true, 'data' => $concept]);
    }

    public function destroy(string $id)
    {
        PaymentConcept::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Concepto eliminado.']]);
    }
}