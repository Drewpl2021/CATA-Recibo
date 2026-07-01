<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Descuento;
use App\Models\Documento;
use App\Models\User;
use App\Traits\CalculaConceptosPlanilla;
use App\Mail\BoletaGenerada;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    private array $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    public function generar(Request $request, $empleado_id, $mes, $anio)
    {
        $empleado = Empleado::with('area', 'cargo')->findOrFail($empleado_id);
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

        $correlativo = Planilla::where('empleado_id', $empleado_id)
            ->whereYear('created_at', $anio)
            ->count();
        $numero_boleta = 'BOL-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        $archivo = "boleta_{$empleado->dni}_{$mes}_{$anio}.pdf";

        $pension            = $this->calcularDescuentoPension($empleado, $planilla->sueldo_base);
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);
        $gratificacion      = $this->calcularGratificacion($planilla->sueldo_base, $mes);
        $essalud            = $this->calcularEssalud($planilla->sueldo_base);
        $renta5ta           = $this->calcularRenta5taCategoria(
            $planilla->sueldo_base,
            $planilla->bonificaciones,
            $mes
        );

        $documento = Documento::where('empleado_id', $empleado_id)
            ->where('planilla_id', $planilla->id)
            ->where('tipo', 'boleta')
            ->first();

        if (!$documento) {
            $documento = Documento::create([
                'empleado_id'  => $empleado_id,
                'planilla_id'  => $planilla->id,
                'tipo'         => 'boleta',
                'archivo'      => $archivo,
                'estado_firma' => 'pendiente',
            ]);
        }

        // Enviar correo al empleado
        $user = User::where('empleado_id', $empleado_id)->first();
        if ($user && $user->email) {
            Mail::to($user->email)->send(new BoletaGenerada(
                $empleado->nombre . ' ' . $empleado->apellido,
                $this->meses[(int)$mes],
                (int)$anio,
                $numero_boleta
            ));
        }

        $data = [
            'empleado'           => $empleado,
            'planilla'           => $planilla,
            'descuentos'         => $descuentos,
            'mes_nombre'         => $this->meses[(int)$mes],
            'mes'                => $mes,
            'anio'               => $anio,
            'numero_boleta'      => $numero_boleta,
            'pension'            => $pension,
            'asignacionFamiliar' => $asignacionFamiliar,
            'gratificacion'      => $gratificacion,
            'essalud'            => $essalud,
            'renta5ta'           => $renta5ta,
            'documento'          => $documento,
        ];

        $pdf = Pdf::loadView('boleta', $data)->setPaper('a4', 'landscape');
        return $pdf->download($archivo);
    }

    public function generarMasivo(Request $request)
    {
        $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000',
        ]);

        $mes  = $request->mes;
        $anio = $request->anio;

        $empleados = Empleado::where('estado', 'activo')->get();
        $generadas = 0;
        $omitidas  = 0;

        foreach ($empleados as $empleado) {
            $planilla = Planilla::where('empleado_id', $empleado->id)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->first();

            if (!$planilla) {
                $omitidas++;
                continue;
            }

            $correlativo = Planilla::where('empleado_id', $empleado->id)
                ->whereYear('created_at', $anio)
                ->count();
            $numero_boleta = 'BOL-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

            $existe = Documento::where('empleado_id', $empleado->id)
                ->where('planilla_id', $planilla->id)
                ->where('tipo', 'boleta')
                ->first();

            if (!$existe) {
                Documento::create([
                    'empleado_id'  => $empleado->id,
                    'planilla_id'  => $planilla->id,
                    'tipo'         => 'boleta',
                    'archivo'      => "boleta_{$empleado->dni}_{$mes}_{$anio}.pdf",
                    'estado_firma' => 'pendiente',
                ]);

                // Enviar correo al empleado
                $user = User::where('empleado_id', $empleado->id)->first();
                if ($user && $user->email) {
                    Mail::to($user->email)->send(new BoletaGenerada(
                        $empleado->nombre . ' ' . $empleado->apellido,
                        $this->meses[(int)$mes],
                        (int)$anio,
                        $numero_boleta
                    ));
                }

                $generadas++;
            } else {
                $omitidas++;
            }
        }

        return response()->json([
            'success'   => true,
            'message'   => "Proceso completado.",
            'generadas' => $generadas,
            'omitidas'  => $omitidas,
        ]);
    }
}