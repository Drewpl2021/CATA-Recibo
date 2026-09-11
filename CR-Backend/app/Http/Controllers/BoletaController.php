<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Models\Empleado;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\User;
use App\Traits\CalculaConceptosPlanilla;
use App\Mail\BoletaGenerada;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\ConceptosDePago;
use App\Support\Meses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BoletaController extends Controller
{
    use CalculaConceptosPlanilla;

    // Conceptos que se generan como PayrollDetalle (para que Planilla.total los incluya)
    // pero que en el PDF se muestran aparte, en su propia sección dedicada — para no
    // duplicarlos también en el listado genérico de "Descuentos".
    /** La pensión y la Renta de 5ta ya salen en su propia fila de la boleta. */
    private const CONCEPTOS_MOSTRADOS_APARTE = ConceptosDePago::MOSTRADOS_APARTE;

    /** Los nombres viven en App\Support\Meses: aquí solo se usan. */
    private array $meses = Meses::NOMBRES;

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
            ->filter(fn ($d) => $d->paymentConcept?->tipo === 'aportacion' && $d->paymentConcept?->nombre !== ConceptosDePago::ESSALUD)
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

        // Queda anotado en la boleta a quién y cuándo se le avisó. Es lo que
        // le permite a RR.HH. contestar con una fecha y un correo cuando
        // alguien dice "a mí nunca me avisaron".
        if ($documentoId) {
            Documento::find($documentoId)?->registrarAviso($user->email);
        }

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
            // Desde dentro de una planilla se manda su id y se emite SOLO para
            // su gente. Antes solo se aceptaba el mes, así que "Emitir boletas"
            // en la Planilla TIC emitía las de todo el colegio.
            'corrida_id' => 'nullable|uuid|exists:planilla_corridas,id',
            'mes'        => 'required_without:corrida_id|integer|min:1|max:12',
            'anio'       => 'required_without:corrida_id|integer|min:2000',
        ]);

        $corrida = $request->filled('corrida_id') ? PlanillaCorrida::findOrFail($request->corrida_id) : null;
        $mes     = $corrida ? (int) $corrida->mes : (int) $request->mes;
        $anio    = $corrida ? (int) $corrida->anio : (int) $request->anio;

        /*
         * Se recorren las PLANILLAS, con su empleado ya cargado, y no los
         * empleados uno por uno.
         *
         * Antes eran dos consultas por trabajador —buscar su planilla, buscar
         * su boleta—: con 2 000 personas, 4 000 consultas antes de generar el
         * primer PDF. Ahora son dos en total: las planillas con todo lo que
         * pinta la boleta, y cuáles de ellas ya tienen la suya.
         */
        $planillas = ($corrida ? $corrida->planillas() : Planilla::where('mes', $mes)->where('anio', $anio))
            ->whereHas('empleado', fn ($q) => $q->where('estado', 'activo'))
            ->with('empleado.area', 'empleado.cargo', 'empleado.identidadFirma')
            ->get();

        $yaEmitidas = Documento::where('tipo', 'boleta')
            ->whereIn('planilla_id', $planillas->pluck('id'))
            ->pluck('planilla_id')
            ->flip();

        $generadas = 0;
        $yaTenian  = 0;

        foreach ($planillas as $planilla) {
            // Idempotente: la boleta que ya existe no se vuelve a generar.
            if (isset($yaEmitidas[$planilla->id])) {
                $yaTenian++;
                continue;
            }

            $empleado = $planilla->empleado;

            // Genera y persiste el PDF real (antes solo se creaba el registro
            // Documento sin archivo — quedaba metadata sin nada que descargar).
            ['numero_boleta' => $numero_boleta, 'documento' => $documento]
                = $this->construirBoleta($empleado, $planilla, $mes, $anio);
            $this->avisarBoletaLista($empleado, $mes, $anio, $numero_boleta, $documento?->id);
            $generadas++;
        }

        // Quien está activo pero no tiene planilla ese mes. Solo cuenta al
        // emitir el mes entero: dentro de una planilla, los de fuera no son
        // "omitidos", simplemente no son de ella.
        $sinPlanilla = $corrida
            ? 0
            : max(0, Empleado::where('estado', 'activo')->count() - $planillas->count());

        return response()->json([
            'success'        => true,
            'message'        => 'Proceso completado.',
            'generadas'      => $generadas,
            'omitidas'       => $yaTenian + $sinPlanilla,
            // El desglose de las omitidas: no es lo mismo "ya la tenía" que
            // "no tiene planilla", y lo segundo es trabajo pendiente.
            'yaTenianBoleta' => $yaTenian,
            'sinPlanilla'    => $sinPlanilla,
            'planilla'       => $corrida?->nombre,
        ]);
    }
}