<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ContratoController extends Controller
{
    public function index(Request $request)
    {
        $query = Contrato::with('empleado', 'documento');

        if ($request->has('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        if (!$request->has('incluir_inactivos')) {
        $query->where('estado_registro', 'activo');
    }

        return response()->json(['success' => true, 'data' => $query->orderBy('fecha_inicio', 'desc')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'   => 'required|uuid|exists:empleados,id',
            'tipo_contrato' => 'required|in:indeterminado,plazo_fijo,suplencia,practicas',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'documento_id'  => 'nullable|uuid|exists:documentos,id',
            'observaciones' => 'nullable|string',
        ]);

        $contratoVigente = Contrato::where('empleado_id', $request->empleado_id)
            ->where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->first();

        if ($contratoVigente) {
            $fechaFinAnterior = Carbon::parse($request->fecha_inicio)->subDay()->toDateString();

            $contratoVigente->update([
                'estado'     => 'finalizado',
                'fecha_fin'  => $contratoVigente->fecha_fin ?? $fechaFinAnterior,
                'motivo_fin' => $contratoVigente->motivo_fin ?? 'otro',
            ]);
        }

        $contrato = Contrato::create([
            'empleado_id'   => $request->empleado_id,
            'tipo_contrato' => $request->tipo_contrato,
            'fecha_inicio'  => $request->fecha_inicio,
            'fecha_fin'     => $request->fecha_fin,
            'documento_id'  => $request->documento_id,
            'observaciones' => $request->observaciones,
            'estado'        => 'vigente',
        ]);

        $contrato->load('empleado', 'documento');

        return response()->json(['success' => true, 'data' => $contrato], 201);
    }

    public function show(string $id)
    {
        $contrato = Contrato::with('empleado', 'documento')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $contrato]);
    }

    public function update(Request $request, string $id)
    {
        $contrato = Contrato::findOrFail($id);

        $request->validate([
            'tipo_contrato' => 'sometimes|in:indeterminado,plazo_fijo,suplencia,practicas',
            'fecha_inicio'  => 'sometimes|date',
            'fecha_fin'     => 'nullable|date|after_or_equal:fecha_inicio',
            'estado'        => 'sometimes|in:vigente,finalizado,renovado',
            'motivo_fin'    => 'nullable|in:renuncia,despido,fin_contrato_plazo,fin_año_escolar,no_renovacion,jubilacion,otro',
            'documento_id'  => 'nullable|uuid|exists:documentos,id',
            'observaciones' => 'nullable|string',
        ]);

        $contrato->update($request->all());
        $contrato->load('empleado', 'documento');

        return response()->json(['success' => true, 'data' => $contrato]);
    }

    public function destroy(string $id)
{
    $contrato = Contrato::findOrFail($id);
    $contrato->update(['estado_registro' => 'inactivo']);

    return response()->json(['success' => true, 'data' => ['message' => 'Contrato desactivado correctamente.']]);
}
}