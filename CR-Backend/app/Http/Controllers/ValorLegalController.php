<?php

namespace App\Http\Controllers;

use App\Models\ValorLegal;
use Illuminate\Http\Request;

/**
 * Los montos de ley por año. RR.HH. los lee (la pantalla de boletas los usa
 * para su vista previa); solo el Administrador los crea o los cambia.
 *
 * No se borran: una planilla ya armada de ese año se calculó con ellos.
 */
class ValorLegalController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => ValorLegal::orderByDesc('anio')->get(),
        ]);
    }

    /** GET /legal-values/{anio} — los que usa ese año (los del último año cargado, si falta). */
    public function show(int $anio)
    {
        return response()->json(['success' => true, 'data' => ValorLegal::delAnio($anio)]);
    }

    /**
     * Un año nuevo. Lo que no se mande se copia del año anterior más
     * cercano: casi siempre cambian la UIT y poco más.
     */
    public function store(Request $request)
    {
        $datos = $request->validate(['anio' => 'required|integer|min:2000|max:2100|unique:valores_legales,anio'] + $this->reglas('sometimes'));

        $base = ValorLegal::where('anio', '<', $datos['anio'])->orderByDesc('anio')->first()
            ?? ValorLegal::orderBy('anio')->first();

        $valores = $base ? $base->only(ValorLegal::CAMPOS) : [];
        $nuevo = ValorLegal::create(array_merge($valores, $datos));

        return response()->json(['success' => true, 'data' => $nuevo->fresh()], 201);
    }

    public function update(Request $request, int $anio)
    {
        $valor = ValorLegal::findOrFail($anio);
        $valor->update($request->validate($this->reglas('required')));

        return response()->json(['success' => true, 'data' => $valor->fresh()]);
    }

    /** Montos en soles con tope razonable; porcentajes entre 0 y 100. */
    private function reglas(string $obligatorio): array
    {
        $porcentaje = "{$obligatorio}|numeric|min:0|max:100";

        return [
            'uit'                 => "{$obligatorio}|numeric|min:1|max:100000",
            'asignacion_familiar' => "{$obligatorio}|numeric|min:0|max:10000",
            'onp'                 => $porcentaje,
            'essalud'             => $porcentaje,
            'aporte_afp'          => $porcentaje,
            'prima_seguro_afp'    => $porcentaje,
            'comision_habitat'    => $porcentaje,
            'comision_integra'    => $porcentaje,
            'comision_prima'      => $porcentaje,
            'comision_profuturo'  => $porcentaje,
        ];
    }
}
