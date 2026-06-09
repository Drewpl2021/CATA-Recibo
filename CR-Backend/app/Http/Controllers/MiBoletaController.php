<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Descuento;
use App\Models\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MiBoletaController extends Controller
{
    public function descargar(Request $request, $mes, $anio)
    {
        $empleado_id = $request->user()->empleado_id;
        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.']
            ], 403);
        }

        $empleado = Empleado::findOrFail($empleado_id);
        $planilla = Planilla::where('empleado_id', $empleado_id)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->first();

        if (!$planilla) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "No existe planilla para el mes {$mes} del año {$anio}."]
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

        $archivo = "boleta_{$empleado->dni}_{$mes}_{$anio}.pdf";

        // Guardar documento si no existe
        $existe = Documento::where('empleado_id', $empleado_id)
            ->where('planilla_id', $planilla->id)
            ->where('tipo', 'boleta')
            ->first();

        if (!$existe) {
            Documento::create([
                'empleado_id'  => $empleado_id,
                'planilla_id'  => $planilla->id,
                'tipo'         => 'boleta',
                'archivo'      => $archivo,
                'estado_firma' => 'pendiente',
            ]);
        }

        $data = [
            'empleado'   => $empleado,
            'planilla'   => $planilla,
            'descuentos' => $descuentos,
            'mes_nombre' => $meses[(int)$mes],
            'anio'       => $anio,
        ];

        $pdf = Pdf::loadView('boleta', $data)->setPaper('a4', 'portrait');
        return $pdf->download($archivo);
    }
}