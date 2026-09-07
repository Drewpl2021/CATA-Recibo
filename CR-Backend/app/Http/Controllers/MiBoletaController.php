<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Documento;
use App\Traits\CalculaConceptosPlanilla;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MiBoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    private const CONCEPTOS_MOSTRADOS_APARTE = [
        'ONP', 'SPP Fondo de Pensiones', 'SPP Prima de Seguro', 'SPP Comisión', 'I.R. 5ta Categoría',
    ];

    public function descargar(Request $request, $mes, $anio)
    {
        $empleado_id = $request->user()->empleado_id;
        if (!$empleado_id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Tu usuario no tiene empleado vinculado.']
            ], 403);
        }

        $empleado = Empleado::with('area', 'cargo', 'identidadFirma')->findOrFail($empleado_id);
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

        // Base afecta a AFP/ONP/ESSALUD = sueldo_base + asignación familiar
        // (misma regla que BoletaController — confirmado contra boleta física)
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);
        $baseAfecta         = (float) $planilla->sueldo_base + $asignacionFamiliar;

        $pension            = $this->calcularDescuentoPension($empleado, $baseAfecta);
        $gratificacion      = $this->calcularGratificacion($empleado, $planilla->sueldo_base, $mes, $anio);
        $essalud            = $this->calcularEssalud($baseAfecta);
        $renta5ta           = $this->generarYPersistirRenta5ta($planilla, $empleado);

        // Conceptos de esta planilla (PaymentConcept vía PayrollDetalle), separados por tipo.
        // Se leen DESPUÉS de generarYPersistirRenta5ta() para incluir su resultado más reciente.
        $conceptosPlanilla  = $planilla->payrollDetalles()->with('paymentConcept')->get();
        $conceptosIngreso   = $conceptosPlanilla->filter(fn ($d) => $d->paymentConcept?->tipo === 'bonificacion')->values();
        $conceptosDescuento = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'descuento' && !in_array($d->paymentConcept?->nombre, self::CONCEPTOS_MOSTRADOS_APARTE, true))
            ->values();
        $conceptosAportacion = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'aportacion' && $d->paymentConcept?->nombre !== 'ESSALUD')
            ->values();
        $conceptosAdelanto = $conceptosPlanilla->filter(fn ($d) => $d->paymentConcept?->tipo === 'adelanto')->values();

        $cabecera = $this->datosCabeceraBoleta($empleado, (int) $mes, (int) $anio);

        // Ruta dentro del disco privado "local" (storage/app/private) — nunca en
        // el disco "public", porque una boleta trae sueldo, DNI y cuenta bancaria.
        $rutaArchivo = "documentos/{$empleado_id}/boletas/{$archivo}";

        $documento = Documento::with('empleador.identidadFirma')
            ->where('empleado_id', $empleado_id)
            ->where('planilla_id', $planilla->id)
            ->where('tipo', 'boleta')
            ->first();

        if (!$documento) {
            $documento = Documento::create([
                'empleado_id'  => $empleado_id,
                'planilla_id'  => $planilla->id,
                'tipo'         => 'boleta',
                'archivo'      => $rutaArchivo,
                'estado_firma' => 'pendiente',
            ]);
        }

        $data = [
            'empleado'           => $empleado,
            'planilla'           => $planilla,
            'conceptosIngreso'    => $conceptosIngreso,
            'conceptosDescuento'  => $conceptosDescuento,
            'conceptosAportacion' => $conceptosAportacion,
            'conceptosAdelanto'   => $conceptosAdelanto,
            'mes_nombre'         => $meses[(int)$mes],
            'mes'                => $mes,
            'anio'               => $anio,
            'numero_boleta'      => $numero_boleta,
            'pension'            => $pension,
            'asignacionFamiliar' => $asignacionFamiliar,
            'gratificacion'      => $gratificacion,
            'essalud'            => $essalud,
            'renta5ta'           => $renta5ta,
            'documento'          => $documento,
            'cabecera'           => $cabecera,
        ];

        $pdf = Pdf::loadView('boleta', $data)->setPaper('a4', 'landscape');

        // Mientras no esté firmada, cada regeneración sobrescribe la copia en disco
        // para reflejar el último cálculo. Una vez firmada queda congelada como
        // evidencia de lo que el empleado realmente vio y firmó.
        if ($documento->estado_firma !== 'firmado') {
            Storage::disk('local')->put($rutaArchivo, $pdf->output());
        }

        return $pdf->download($archivo);
    }
}