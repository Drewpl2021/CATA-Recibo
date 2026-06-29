<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Descuento;
use App\Models\Documento;
use App\Traits\CalculaConceptosPlanilla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MiBoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    public function descargar(Request $request, $mes, $anio)
    {
        $empleado_id = $request->user()->empleado_id;
        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.']
            ], 403);
        }

        $empleado = Empleado::with('area', 'cargo')->findOrFail($empleado_id);
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

        $correlativo = Planilla::where('empleado_id', $empleado_id)
            ->whereYear('created_at', $anio)
            ->count();
        $numero_boleta = 'BOL-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        // Cálculos previsionales
        $pension            = $this->calcularDescuentoPension($empleado, $planilla->sueldo_base);
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);
        $gratificacion      = $this->calcularGratificacion($planilla->sueldo_base, $mes);
        $essalud            = $this->calcularEssalud($planilla->sueldo_base);

        // Guardar documento si no existe
        $existe = Documento::where('empleado_id', $empleado_id)
            ->where('planilla_id', $planilla->id)
            ->where('tipo', 'boleta')
            ->first();

        if (!$existe) {
            $existe = Documento::create([
                'empleado_id'  => $empleado_id,
                'planilla_id'  => $planilla->id,
                'tipo'         => 'boleta',
                'archivo'      => $archivo,
                'estado_firma' => 'pendiente',
            ]);
        }

        $data = [
            'empleado'           => $empleado,
            'planilla'           => $planilla,
            'descuentos'         => $descuentos,
            'mes_nombre'         => $meses[(int)$mes],
            'mes'                => $mes,
            'anio'               => $anio,
            'numero_boleta'      => $numero_boleta,
            'pension'            => $pension,
            'asignacionFamiliar' => $asignacionFamiliar,
            'gratificacion'      => $gratificacion,
            'essalud'            => $essalud,
            'documento'          => $existe,
        ];

        $pdf = Pdf::loadView('boleta', $data)->setPaper('a4', 'landscape');
        return $pdf->download($archivo);
    }
}