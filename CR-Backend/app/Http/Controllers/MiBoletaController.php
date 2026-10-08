<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Documento;
use App\Traits\CalculaConceptosPlanilla;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\ConceptosDePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\AccesoADocumento;
use App\Support\Meses;
use App\Support\AniosAnteriores;
use App\Support\FirmaDigitalDeBoletas;

class MiBoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    /** La pensión y la Renta de 5ta ya salen en su propia fila de la boleta. */
    private const CONCEPTOS_MOSTRADOS_APARTE = ConceptosDePago::MOSTRADOS_APARTE;

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

        $meses = Meses::NOMBRES;

        $archivo = "boleta_{$empleado->dni}_{$mes}_{$anio}.pdf";

        // Con firma digital del colegio la boleta no se arma aquí: es el PDF
        // que firmó el colegio, y solo cuando ya está completo.
        $suDocumento = Documento::where('planilla_id', $planilla->id)->where('tipo', 'boleta')->first();
        if ($suDocumento?->firma_colegio !== null
            || (! $suDocumento && FirmaDigitalDeBoletas::activa() && ! AniosAnteriores::esAnterior((int) $anio))) {
            return $this->laFirmadaPorElColegio($request, $suDocumento, $archivo, $meses[(int) $mes] . " {$anio}");
        }

        $correlativo = Planilla::where('empleado_id', $empleado_id)
            ->whereYear('created_at', $anio)
            ->count();
        $numero_boleta = 'BOL-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        // Base afecta a AFP/ONP/ESSALUD/Diezmo = sueldo_base + asignación
        // familiar + Bonificación por Cargo + Vacaciones Truncas, si la
        // planilla ya las tiene (misma regla que BoletaController —
        // confirmado contra el PLAME real)
        $asignacionFamiliar = $this->asignacionFamiliarDeLaPlanilla($planilla);
        $baseAfecta         = (float) $planilla->sueldo_base + $asignacionFamiliar + $this->otrosIngresosAfectosDeLaPlanilla($planilla);

        $pension            = $this->calcularDescuentoPension($empleado, $baseAfecta, (int) $anio, (int) $mes);
        // La gratificación es una línea de la planilla (misma regla que
        // BoletaController): sale sola entre los conceptos de ingreso.
        $essalud            = $this->calcularEssalud($baseAfecta, (int) $anio, $this->proporcionDelMes($empleado, (int) $mes, (int) $anio));
        $renta5ta           = $this->generarYPersistirRenta5ta($planilla, $empleado);

        // Conceptos de esta planilla (PaymentConcept vía PayrollDetalle), separados por tipo.
        // Se leen DESPUÉS de generarYPersistirRenta5ta() para incluir su resultado más reciente.
        $conceptosPlanilla  = $planilla->payrollDetalles()->with('paymentConcept')->get();
        // Sin la Asignación Familiar: la boleta la imprime en su propia fila
        // (misma regla que BoletaController, o se sumaría dos veces).
        $conceptosIngreso   = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'bonificacion'
                && $d->paymentConcept?->nombre !== ConceptosDePago::ASIGNACION_FAMILIAR)
            ->values();
        $conceptosDescuento = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'descuento' && !in_array($d->paymentConcept?->nombre, self::CONCEPTOS_MOSTRADOS_APARTE, true))
            ->values();
        $conceptosAportacion = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'aportacion' && $d->paymentConcept?->nombre !== ConceptosDePago::ESSALUD)
            ->values();
        $conceptosAdelanto = $conceptosPlanilla->filter(fn ($d) => $d->paymentConcept?->tipo === 'adelanto')->values();

        $cabecera = $this->datosCabeceraBoleta($empleado, (int) $mes, (int) $anio);

        // Ruta dentro del disco privado "local" (storage/app/private) — nunca en
        // el disco "public", porque una boleta trae sueldo, DNI y cuenta bancaria.
        $rutaArchivo = "documentos/{$empleado_id}/boletas/{$archivo}";

        $documento = Documento::query()
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
                // Igual que al emitirla desde RR.HH.: la de un año anterior ya
                // se firmó en papel.
                'estado_firma' => \App\Support\AniosAnteriores::esAnterior((int) $anio) ? 'en_papel' : 'pendiente',
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
            'essalud'            => $essalud,
            'renta5ta'           => $renta5ta,
            'documento'          => $documento,
            'cabecera'           => $cabecera,
            'nombre_anio'        => \App\Models\ValorLegal::nombreDelAnio((int) $anio),
        ];

        // Al trabajador se le da UNA sola copia: la del colegio no le sirve de
        // nada y solo le hacía imprimir el doble.
        $suya = Pdf::loadView('boleta', $data + ['copias' => 1])->setPaper('a4', 'portrait');

        // Mientras no esté firmada, cada regeneración sobrescribe la copia en disco
        // para reflejar el último cálculo. Una vez firmada queda congelada como
        // evidencia de lo que el empleado realmente vio y firmó.
        //
        // Lo que se archiva son las DOS copias: ese es el ejemplar que se firma
        // y que baja RR.HH., y no puede depender de quién lo haya pedido antes.
        if ($documento->estado_firma !== 'firmado') {
            $archivada = Pdf::loadView('boleta', $data + ['copias' => 2])->setPaper('a4', 'portrait');
            Storage::disk('local')->put($rutaArchivo, $archivada->output());
        }

        // Ni abrirla ni bajarla antes de firmarla: primero firma con su
        // contraseña y después la ve (y queda anotado que la revisó).
        if ($request->boolean('ver')) {
            if ($motivo = AccesoADocumento::porQueNoPuedeVer($documento, $request->user())) {
                return response()->json(['success' => false, 'message' => $motivo], 403);
            }
            AccesoADocumento::marcarVisto($documento, $request->user());

            return $suya->stream($archivo);
        }

        if ($motivo = AccesoADocumento::porQueNoPuedeDescargar($documento, $request->user())) {
            return response()->json(['success' => false, 'message' => $motivo], 403);
        }

        // El trabajador se la bajó: queda anotado. Solo acá, que es SU
        // descarga; que RR.HH. abra el PDF para revisarlo no significa que el
        // trabajador la haya recibido.
        $documento->registrarDescarga();

        return $suya->download($archivo);
    }

    /** El PDF firmado digitalmente por el colegio, tal cual, o por qué todavía no. */
    private function laFirmadaPorElColegio(Request $request, ?Documento $documento, string $archivo, string $periodo)
    {
        if (! $documento) {
            return response()->json(['success' => false, 'message' => "Tu boleta de {$periodo} todavía no se emite."], 403);
        }
        if ($documento->esperaFirmaDelColegio()) {
            return response()->json([
                'success' => false,
                'message' => "Tu boleta de {$periodo} todavía está en firma del colegio. Te avisaremos cuando esté lista.",
            ], 403);
        }
        if (! Storage::disk('local')->exists($documento->archivo)) {
            return response()->json(['success' => false, 'message' => 'El archivo de tu boleta no está disponible. Avísale a RR.HH.'], 404);
        }

        if ($request->boolean('ver')) {
            if ($motivo = AccesoADocumento::porQueNoPuedeVer($documento, $request->user())) {
                return response()->json(['success' => false, 'message' => $motivo], 403);
            }
            AccesoADocumento::marcarVisto($documento, $request->user());

            return response(Storage::disk('local')->get($documento->archivo), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$archivo}\"",
            ]);
        }

        if ($motivo = AccesoADocumento::porQueNoPuedeDescargar($documento, $request->user())) {
            return response()->json(['success' => false, 'message' => $motivo], 403);
        }
        $documento->registrarDescarga();

        return Storage::disk('local')->download($documento->archivo, $archivo);
    }
}