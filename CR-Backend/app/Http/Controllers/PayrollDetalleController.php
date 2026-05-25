<?php
namespace App\Http\Controllers;
use App\Models\PayrollDetalle;
use Illuminate\Http\Request;

class PayrollDetalleController extends Controller
{
    public function index(Request $request)
    {
        $query = PayrollDetalle::with('paymentConcept');
        if ($request->has('planilla_id'))
            $query->where('planilla_id', $request->planilla_id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'planilla_id' => 'required|exists:planilla,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'monto_calculado' => 'required|numeric|min:0',
            'estado' => 'nullable|string|max:45',
        ]);
        $detalle = PayrollDetalle::create($request->all());
        return response()->json(['success' => true, 'data' => $detalle], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => PayrollDetalle::with('paymentConcept')->findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $detalle = PayrollDetalle::findOrFail($id);
        $request->validate([
            'monto_calculado' => 'sometimes|numeric|min:0',
            'estado' => 'nullable|string|max:45',
        ]);
        $detalle->update($request->all());
        return response()->json(['success' => true, 'data' => $detalle]);
    }

    public function destroy(string $id)
    {
        PayrollDetalle::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Detalle eliminado.']]);
    }
}