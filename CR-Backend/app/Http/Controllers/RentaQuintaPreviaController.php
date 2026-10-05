<?php

namespace App\Http\Controllers;

use App\Models\RentaQuintaPrevia;
use App\Services\CargaRentaQuintaPrevia;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Enero y febrero que no pasaron por el sistema, para la Renta de 5ta.
 * Solo el Administrador, desde Ajustes del sistema.
 */
class RentaQuintaPreviaController extends Controller
{
    /** GET renta-5ta/previous?anio= — cuántos hay cargados ese año. */
    public function index(Request $request)
    {
        $anio = (int) $request->validate(['anio' => 'required|integer|min:2000'])['anio'];
        $filas = RentaQuintaPrevia::where('anio', $anio);

        return response()->json(['success' => true, 'data' => [
            'anio'         => $anio,
            'trabajadores' => (clone $filas)->distinct('empleado_id')->count('empleado_id'),
            'remuneracion' => round((float) (clone $filas)->sum('remuneracion'), 2),
            'retencion'    => round((float) (clone $filas)->sum('retencion'), 2),
            'origen'       => (clone $filas)->latest('updated_at')->value('origen'),
        ]]);
    }

    /** POST renta-5ta/previous — sube el Excel "Calculo 5ta.xlsx". */
    public function store(Request $request, CargaRentaQuintaPrevia $carga)
    {
        $datos = $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'anio'    => 'required|integer|min:2000|max:2100',
        ], [
            'archivo.mimes' => 'Sube el Excel (.xlsx o .xls).',
        ]);

        try {
            $resultado = $carga->cargar(
                $request->file('archivo')->getRealPath(),
                (int) $datos['anio'],
                $request->file('archivo')->getClientOriginalName()
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $resultado]);
    }
}
