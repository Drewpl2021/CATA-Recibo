<?php

namespace App\Http\Controllers;

use App\Models\RmvVigencia;
use Illuminate\Http\Request;

/**
 * Los cambios del sueldo mínimo durante el año. RR.HH. los lee; solo el
 * Administrador los escribe. Cada planilla usa el de su propio mes.
 */
class RmvVigenciaController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => RmvVigencia::orderByDesc('vigente_desde')->get(['id', 'vigente_desde', 'monto']),
        ]);
    }

    /** Un mes ya registrado se corrige; si no, se agrega. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'vigente_desde' => 'required|date_format:Y-m',
            'monto'         => 'required|numeric|min:1|max:100000',
        ]);

        $cambio = RmvVigencia::updateOrCreate(
            ['vigente_desde' => "{$datos['vigente_desde']}-01"],
            ['monto' => $datos['monto']],
        );

        return response()->json(['success' => true, 'data' => $cambio->only(['id', 'vigente_desde', 'monto'])], 201);
    }

    public function destroy(int $id)
    {
        RmvVigencia::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
