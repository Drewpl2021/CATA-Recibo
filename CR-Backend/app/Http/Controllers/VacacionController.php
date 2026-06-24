<?php

namespace App\Http\Controllers;

use App\Models\Vacacion;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    // Días máximos por año — cambiar aquí si el ingeniero indica otro valor
    const DIAS_MAX_ANUALES = 30;

    public function index(Request $request)
    {
        $query = Vacacion::with('empleado');

        if ($request->has('empleado_id'))
            $query->where('empleado_id', $request->empleado_id);

        if ($request->has('estado'))
            $query->where('estado', $request->estado);

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'      => 'required|exists:empleados,id',
            'fecha_inicio'     => 'required|date',
            'fecha_fin'        => 'required|date|after_or_equal:fecha_inicio',
            'dias_solicitados' => 'required|integer|min:1',
            'motivo'           => 'nullable|string',
        ]);

        $anio = date('Y', strtotime($request->fecha_inicio));

        $diasUsados = Vacacion::where('empleado_id', $request->empleado_id)
            ->whereYear('fecha_inicio', $anio)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->sum('dias_solicitados');

        $diasDisponibles = self::DIAS_MAX_ANUALES - $diasUsados;

        if ($request->dias_solicitados > $diasDisponibles) {
            return response()->json([
                'success' => false,
                'message' => "No tienes días disponibles suficientes. Disponibles: {$diasDisponibles}, Solicitados: {$request->dias_solicitados}."
            ], 422);
        }

        $vacacion = Vacacion::create($request->all());

        return response()->json([
            'success' => true,
            'data'    => $vacacion,
            'dias_restantes' => $diasDisponibles - $request->dias_solicitados
        ], 201);

    }

    public function show(string $id)
    {
        $vacacion = Vacacion::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $vacacion]);
    }

    public function update(Request $request, string $id)
    {
        $vacacion = Vacacion::findOrFail($id);

        $request->validate([
            'estado'       => 'sometimes|in:pendiente,aprobado,rechazado',
            'aprobado_por' => 'nullable|string',
            'motivo'       => 'nullable|string',
        ]);

        $vacacion->update($request->all());

        return response()->json(['success' => true, 'data' => $vacacion]);
    }

    public function destroy(string $id)
    {
        $vacacion = Vacacion::findOrFail($id);
        $vacacion->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Vacación eliminada correctamente.']]);
    }
}