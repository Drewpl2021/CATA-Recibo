<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\ValorLegal;
use App\Support\DetalleDeAuditoria;
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

    /**
     * GET /legal-values/{anio}?mes= — los que usa ese año (los del último año
     * cargado, si falta). Con mes, con la RMV de ese mes y su asignación.
     */
    public function show(Request $request, int $anio)
    {
        $mes = $request->validate(['mes' => 'nullable|integer|min:1|max:12'])['mes'] ?? null;
        $ley = clone ValorLegal::delAnio($anio);
        if ($mes) {
            $ley->rmv = ValorLegal::rmvDelMes($anio, (int) $mes);
        }

        return response()->json(['success' => true, 'data' => $ley]);
    }

    /**
     * GET /legal-values/history?anio= — qué sueldo mínimo rigió cada parte de
     * ese año y quién cambió los montos de ley, y cuándo. Solo quien administra
     * los ajustes: lleva los nombres de quienes hicieron los cambios.
     */
    public function historial(Request $request)
    {
        $anio = (int) $request->validate(['anio' => 'required|integer|min:2000|max:2100'])['anio'];

        $tramos = [];
        foreach (range(1, 12) as $mes) {
            $rmv = ValorLegal::rmvDelMes($anio, $mes);
            $ultimo = array_key_last($tramos);
            if ($ultimo !== null && $tramos[$ultimo]['rmv'] === $rmv) {
                $tramos[$ultimo]['hasta_mes'] = $mes;
            } else {
                $tramos[] = ['desde_mes' => $mes, 'hasta_mes' => $mes, 'rmv' => $rmv];
            }
        }

        $cambios = Auditoria::whereIn('entidad', ['montos de ley', 'cambio del sueldo mínimo'])
            ->orderByDesc('created_at')->orderByDesc('id')->limit(100)->get()
            ->map(fn (Auditoria $r) => [
                'id'          => $r->id,
                'fecha'       => $r->created_at,
                'usuario'     => $r->usuario_nombre,
                'descripcion' => $r->descripcion,
                'campos'      => DetalleDeAuditoria::campos($r),
            ]);

        return response()->json(['success' => true, 'data' => ['anio' => $anio, 'tramos' => $tramos, 'cambios' => $cambios]]);
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

        // La RMV de cómo terminó ese año: pudo cambiar a mitad (RmvVigencia).
        $valores = $base ? ['rmv' => ValorLegal::rmvDelMes($base->anio, 12)] + $base->only(ValorLegal::CAMPOS) : [];
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
            // Opcional siempre: sin él, la boleta solo no lleva esa línea.
            'nombre_anio'         => 'sometimes|nullable|string|max:255',
            'uit'                 => "{$obligatorio}|numeric|min:1|max:100000",
            'rmv'                 => "{$obligatorio}|numeric|min:1|max:100000",
            // El % de la RMV; el monto en soles lo calcula el modelo.
            'asignacion_familiar_pct' => $porcentaje,
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
