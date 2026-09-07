<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\User;
use App\Traits\CalculaConceptosPlanilla;
use App\Mail\BoletaGenerada;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    // Conceptos que se generan como PayrollDetalle (para que Planilla.total los incluya)
    // pero que en el PDF se muestran aparte, en su propia sección dedicada — para no
    // duplicarlos también en el listado genérico de "Descuentos".
    private const CONCEPTOS_MOSTRADOS_APARTE = [
        'ONP', 'SPP Fondo de Pensiones', 'SPP Prima de Seguro', 'SPP Comisión', 'I.R. 5ta Categoría',
    ];

    private array $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    public function generar(Request $request, $empleado_id, $mes, $anio)
    {
        $empleado = Empleado::with('area', 'cargo', 'identidadFirma')->findOrFail($empleado_id);
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

        ['pdf' => $pdf, 'archivo' => $archivo, 'numero_boleta' => $numero_boleta, 'documento' => $documento]
            = $this->construirBoleta($empleado, $planilla, (int) $mes, (int) $anio);

        $this->avisarBoletaLista($empleado, (int) $mes, (int) $anio, $numero_boleta, $documento?->id);

        return $pdf->download($archivo);
    }

    /**
     * Arma la boleta de un empleado/planilla: corre todos los cálculos del trait,
     * renderiza el PDF, y persiste una copia en el disco privado "local" (a menos
     * que el Documento ya esté firmado, en cuyo caso queda congelada). La usan
     * tanto generar() (una boleta) como generarMasivo() (todo un periodo).
     */
    public function construirBoleta(Empleado $empleado, Planilla $planilla, int $mes, int $anio, bool $forzarGuardado = false): array
    {
        $empleado_id = $empleado->id;

        $correlativo = Planilla::where('empleado_id', $empleado_id)
            ->whereYear('created_at', $anio)
            ->count();
        $numero_boleta = 'BOL-' . $anio . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        $archivo = "boleta_{$empleado->dni}_{$mes}_{$anio}.pdf";

        // Base afecta a AFP/ONP/ESSALUD = sueldo_base + asignación familiar
        // (confirmado contra boleta física — la gratificación NO entra aquí,
        // está exonerada por Ley 29351/30334)
        $asignacionFamiliar = $this->calcularAsignacionFamiliar($empleado);
        $baseAfecta         = (float) $planilla->sueldo_base + $asignacionFamiliar;

        $pension       = $this->calcularDescuentoPension($empleado, $baseAfecta);
        $gratificacion = $this->calcularGratificacion($empleado, $planilla->sueldo_base, $mes, $anio);
        $essalud       = $this->calcularEssalud($baseAfecta);
        // Recalcula y deja registrada la retención del mes (Art. 40 Reglamento LIR),
        // por si se agregaron bonos u otros ingresos después de crear la planilla.
        $renta5ta      = $this->generarYPersistirRenta5ta($planilla, $empleado);

        // Conceptos de esta planilla (PaymentConcept vía PayrollDetalle), separados por tipo.
        // Se leen DESPUÉS de generarYPersistirRenta5ta() para incluir su resultado más reciente.
        $conceptosPlanilla  = $planilla->payrollDetalles()->with('paymentConcept')->get();
        $conceptosIngreso   = $conceptosPlanilla->filter(fn ($d) => $d->paymentConcept?->tipo === 'bonificacion')->values();
        // Excluye los conceptos que ya se muestran aparte (pensión, I.R. 5ta) — aquí solo
        // van los demás descuentos (diezmo, escolaridad, etc.).
        $conceptosDescuento = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'descuento' && !in_array($d->paymentConcept?->nombre, self::CONCEPTOS_MOSTRADOS_APARTE, true))
            ->values();
        // ESSALUD ya se muestra aparte (calculado por el trait); aquí solo otras aportaciones (ej. SCTR).
        $conceptosAportacion = $conceptosPlanilla
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'aportacion' && $d->paymentConcept?->nombre !== 'ESSALUD')
            ->values();
        $conceptosAdelanto = $conceptosPlanilla->filter(fn ($d) => $d->paymentConcept?->tipo === 'adelanto')->values();

        $cabecera = $this->datosCabeceraBoleta($empleado, $mes, $anio);

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
            'mes_nombre'         => $this->meses[$mes],
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
        // evidencia de lo que el empleado realmente vio y firmó — la ÚNICA excepción
        // es $forzarGuardado=true, que usa MisDocumentosController::firmar() justo
        // después de marcar el Documento como firmado, para capturar en el archivo
        // congelado el sello de firma+huella y el texto de verificación (si no,
        // quedaría archivada para siempre la versión de antes de firmar).
        if ($documento->estado_firma !== 'firmado' || $forzarGuardado) {
            Storage::disk('local')->put($rutaArchivo, $pdf->output());
        }

        return ['pdf' => $pdf, 'documento' => $documento, 'archivo' => $archivo, 'numero_boleta' => $numero_boleta];
    }

    /**
     * Le avisa al trabajador que su boleta ya está: le deja el aviso guardado
     * en la campana y le encola el correo.
     *
     * El aviso en la base es lo que ve al entrar (con su fecha, y le queda de
     * historial); el correo es para que se entere sin entrar. Si no tiene
     * cuenta, no hay a quién avisar y se sale sin hacer nada.
     *
     * queue() y no send(): con send() los 127 correos de una emisión masiva
     * salían uno detrás de otro dentro de la misma petición. Ver BoletaGenerada.
     */
    private function avisarBoletaLista(Empleado $empleado, int $mes, int $anio, string $numero_boleta, ?string $documentoId = null): void
    {
        $user = User::where('empleado_id', $empleado->id)->first();

        if (! $user) {
            return;
        }

        $periodo = $this->meses[$mes] . ' ' . $anio;

        Notificacion::create([
            'user_id'      => $user->id,
            'tipo'         => 'boleta_disponible',
            'titulo'       => "Tu boleta de {$periodo} ya está lista",
            'mensaje'      => "Boleta {$numero_boleta}. Ábrela y fírmala para dejar constancia de que la recibiste.",
            'documento_id' => $documentoId,
        ]);

        if ($user->email) {
            Mail::to($user->email)->queue(new BoletaGenerada(
                $empleado->nombre . ' ' . $empleado->apellido,
                $this->meses[$mes],
                $anio,
                $numero_boleta
            ));
        }
    }

    public function generarMasivo(Request $request)
    {
        $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000',
        ]);

        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;

        $empleados = Empleado::with('area', 'cargo', 'identidadFirma')->where('estado', 'activo')->get();
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

            $existe = Documento::where('empleado_id', $empleado->id)
                ->where('planilla_id', $planilla->id)
                ->where('tipo', 'boleta')
                ->first();

            if (!$existe) {
                // Genera y persiste el PDF real (antes solo se creaba el registro
                // Documento sin archivo — quedaba metadata sin nada que descargar).
                ['numero_boleta' => $numero_boleta, 'documento' => $documento]
                    = $this->construirBoleta($empleado, $planilla, $mes, $anio);
                $this->avisarBoletaLista($empleado, $mes, $anio, $numero_boleta, $documento?->id);
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