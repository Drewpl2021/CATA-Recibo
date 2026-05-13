<?php

namespace App\Http\Controllers;

use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Descuento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BoletaController extends Controller
{
    public function generar(Request $request, $empleado_id, $mes, $anio)
    {
        $empleado = Empleado::findOrFail($empleado_id);

        $planilla = Planilla::where('empleado_id', $empleado_id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->first();

        if (!$planilla) {
            return response()->json([
                'success' => false,
                'message' => "No existe planilla para el mes {$mes} del año {$anio}."
            ], 404);
        }

        $descuentos = Descuento::where('empleado_id', $empleado_id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->get();

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
            4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $data = [
            'empleado'  => $empleado,
            'planilla'  => $planilla,
            'descuentos'=> $descuentos,
            'mes_nombre'=> $meses[(int)$mes],
            'anio'      => $anio,
        ];

        $pdf = Pdf::loadView('boleta', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download("boleta_{$empleado->dni}_{$mes}_{$anio}.pdf");
    }
}