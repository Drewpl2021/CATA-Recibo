<?php
namespace App\Http\Controllers;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PayrollDetalleController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /payroll-detalles?planilla_id=&page=&size=&search=
     */
    public function index(Request $request)
    {
        $query = PayrollDetalle::with('paymentConcept');

        if ($request->filled('planilla_id')) {
            $query->where('planilla_id', $request->planilla_id);
        }

        return $this->responderListado(
            $request,
            $query,
            ['descripcion', 'paymentConcept.nombre']
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'planilla_id' => 'required|exists:planilla,id',
            'payment_concept_id' => 'required|exists:payment_concepts,id',
            'monto_calculado' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:45',
        ]);
        $detalle = PayrollDetalle::create($request->all());
        $detalle->planilla->recalcularTotal();

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
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:45',
        ]);
        $detalle->update($request->all());
        $detalle->planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $detalle]);
    }

    public function destroy(string $id)
    {
        $detalle  = PayrollDetalle::findOrFail($id);
        $planilla = $detalle->planilla;

        $detalle->delete();
        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => ['message' => 'Detalle eliminado.']]);
    }
}