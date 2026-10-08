<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Support\FirmaDigitalDeBoletas;
use App\Support\FirmaDigitalPdf;
use App\Support\LibroExcel;
use App\Support\Meses;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Las boletas firmadas digitalmente por el colegio, de vuelta al sistema.
 * Ver App\Support\FirmaDigitalDeBoletas para el camino completo.
 *
 *   GET  /payslips/signed/summary   cómo va la firma del mes
 *   POST /payslips/signed/check     revisa UN archivo y dice qué pasaría (no guarda)
 *   POST /payslips/signed           lo vuelve a revisar y lo guarda
 *   POST /payslips/{documento}/void anula una boleta para volver a emitirla
 *   GET  /payslips/delivery-record  la constancia de entrega del mes, en Excel
 *
 * Va de a un archivo por pedido a propósito: con 50 o 300 boletas no se
 * choca con el límite de subida, y la pantalla puede mostrar el avance.
 */
class BoletasFirmadasController extends Controller
{
    /**
     * GET /payslips/signed/summary — cómo va el mes: cuántas boletas esperan
     * la firma del colegio, cuántas ya se entregaron y cuántas tienen la
     * conformidad del trabajador. Es el panel de pasos de Emisión de boletas.
     */
    public function resumen(Request $request)
    {
        $datos = $request->validate(['mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer|min:2000']);

        $boletas = Documento::where('tipo', 'boleta')
            ->whereHas('planilla', fn ($q) => $q->where('mes', (int) $datos['mes'])->where('anio', (int) $datos['anio']))
            ->get(['id', 'firma_colegio', 'estado_firma']);
        $digitales = $boletas->whereNotNull('firma_colegio');

        return response()->json(['success' => true, 'data' => [
            'activa'      => FirmaDigitalDeBoletas::activa(),
            'requeridas'  => FirmaDigitalDeBoletas::requeridas(),
            'emitidas'    => $boletas->count(),
            'por_firmar'  => $digitales->where('firma_colegio', 'pendiente')->count(),
            'a_medias'    => $digitales->where('firma_colegio', 'parcial')->count(),
            'entregadas'  => $digitales->where('firma_colegio', 'completa')->count(),
            'conformidad' => $digitales->where('firma_colegio', 'completa')->where('estado_firma', 'firmado')->count(),
            'sin_firma_digital' => $boletas->whereNull('firma_colegio')->count(),
        ]]);
    }

    public function revisar(Request $request)
    {
        ['resultado' => $resultado] = $this->analizar($request);

        return response()->json(['success' => true, 'data' => $resultado]);
    }

    public function guardar(Request $request)
    {
        ['resultado' => $resultado, 'documento' => $documento, 'contenido' => $contenido, 'firmas' => $firmas]
            = $this->analizar($request);

        if ($resultado['estado'] !== 'ok') {
            return response()->json(['success' => false, 'message' => $resultado['mensaje'], 'data' => $resultado], 422);
        }

        $planilla = $documento->planilla;
        $empleado = $documento->empleado;
        $ruta = "documentos/{$empleado->id}/boletas/boleta_{$empleado->dni}_{$planilla->mes}_{$planilla->anio}_firmada.pdf";

        DB::transaction(function () use ($documento, $ruta, $contenido, $firmas, $resultado, $request) {
            Storage::disk('local')->put($ruta, $contenido);

            $documento->forceFill([
                // El que emitió el sistema se guarda: contra él se compara la
                // próxima subida (la segunda firma, por ejemplo).
                'archivo_sin_firma'        => $documento->archivo_sin_firma ?? $documento->archivo,
                'archivo'                  => $ruta,
                'firmas_colegio'           => $firmas,
                'firma_colegio'            => $resultado['resultado'],
                'firma_colegio_subida_por' => mb_substr((string) $request->user()?->name, 0, 100),
                'firma_colegio_completa_en' => $resultado['resultado'] === 'completa' ? now() : null,
            ])->save();
        });

        // Completa: recién ahora es del trabajador. Se le avisa como siempre.
        if ($resultado['resultado'] === 'completa') {
            app(BoletaController::class)->avisarBoletaLista(
                $empleado, (int) $planilla->mes, (int) $planilla->anio, $planilla->numeroDeBoleta(), $documento->id
            );
        }

        Auditoria::registrar(
            'firmó',
            'boleta',
            (string) $documento->id,
            "Subió la boleta {$resultado['numero']} de {$resultado['trabajador']} firmada digitalmente por "
                . implode(' y ', array_column($firmas, 'nombre'))
                . ($resultado['resultado'] === 'completa' ? ': ya se le entregó.' : " ({$resultado['firmas_validas']} de {$resultado['firmas_requeridas']} firmas)."),
            ['firmantes' => array_column($firmas, 'nombre'), 'estado' => $resultado['resultado']]
        );

        return response()->json(['success' => true, 'data' => $resultado]);
    }

    /**
     * Anular una boleta para volver a emitirla: el camino cuando se encuentra
     * un error después de firmarla. Solo mientras el trabajador no la haya
     * abierto ni dado su conformidad: lo que él ya vio no se borra.
     */
    public function anular(Request $request, string $id)
    {
        $documento = Documento::with('planilla', 'empleado')->where('tipo', 'boleta')->findOrFail($id);

        if ($documento->firma_colegio === null) {
            return response()->json(['success' => false, 'message' => 'Esta boleta no va por firma digital: se corrige desde Emisión, como siempre.'], 422);
        }
        if ($documento->estado_firma !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'El trabajador ya abrió esta boleta o dio su conformidad: no se puede anular. Si hay un error, emite un ajuste en la planilla del mes siguiente.',
            ], 422);
        }

        $nombre = trim("{$documento->empleado?->apellido} {$documento->empleado?->nombre}");
        $numero = $documento->planilla?->numeroDeBoleta();

        DB::transaction(function () use ($documento) {
            foreach (array_filter([$documento->archivo, $documento->archivo_sin_firma]) as $archivo) {
                Storage::disk('local')->delete($archivo);
            }
            Notificacion::where('documento_id', $documento->id)->delete();
            $documento->delete();
        });

        Auditoria::registrar('borró', 'boleta', $id, "Anuló la boleta {$numero} de {$nombre} para volver a emitirla.");

        return response()->json(['success' => true, 'message' => "Boleta {$numero} anulada: ya puedes corregir su planilla y volver a emitirla."]);
    }

    /**
     * La constancia de entrega del mes: lo que reemplaza al archivador de
     * copias firmadas por el trabajador. Una fila por boleta: quién firmó por
     * el colegio, cuándo se le entregó, cuándo la abrió y cuándo dio su
     * conformidad (con su código).
     */
    public function constancia(Request $request)
    {
        $datos = $request->validate(['mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer|min:2000']);
        $mes = (int) $datos['mes'];
        $anio = (int) $datos['anio'];

        $documentos = Documento::with('empleado:id,dni,nombre,apellido', 'planilla:id,empleado_id,mes,anio')
            ->where('tipo', 'boleta')
            ->whereHas('planilla', fn ($q) => $q->where('mes', $mes)->where('anio', $anio))
            ->get()
            ->sortBy(fn ($d) => mb_strtoupper(trim("{$d->empleado?->apellido} {$d->empleado?->nombre}")))
            ->values();

        $fecha = fn ($f) => $f ? \Carbon\Carbon::parse($f)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '';
        $filas = [
            ['Constancia de entrega de boletas · ' . Meses::nombre($mes) . " {$anio}"],
            ['Colegio Adventista Túpac Amaru · ' . $documentos->count() . ' boleta(s) · generada el ' . now()->format('d/m/Y H:i')],
            [''],
            ['N°', 'DNI', 'Trabajador', 'N° de boleta', 'Firma digital del colegio', 'Firmada por el colegio el',
                'Entregada el', 'Abierta por el trabajador', 'Conformidad del trabajador', 'Código de conformidad',
                'Desde (dirección IP)', 'Código del PDF al que dio conformidad (SHA-256)', 'Descargas', 'Estado'],
        ];

        foreach ($documentos as $n => $d) {
            $firmas = collect($d->firmas_colegio ?? []);
            $filas[] = [
                $n + 1,
                (string) $d->empleado?->dni,
                trim("{$d->empleado?->apellido} {$d->empleado?->nombre}"),
                $d->planilla?->numeroDeBoleta(),
                $d->firma_colegio === null ? 'No aplica' : ($firmas->pluck('nombre')->implode(' / ') ?: 'Pendiente'),
                $firmas->isEmpty() ? '' : $fecha($firmas->pluck('fecha')->filter()->max()),
                // Entregada: con firma digital, cuando quedó completa; si no, cuando se le avisó.
                $d->firma_colegio !== null ? $fecha($d->firma_colegio_completa_en) : $fecha($d->fecha_aviso ?? $d->created_at),
                $fecha($d->fecha_visto),
                $d->estado_firma === 'firmado' ? $fecha($d->fecha_firma) : ($d->estado_firma === 'en_papel' ? 'En papel' : ''),
                $d->estado_firma === 'firmado' ? (string) $d->codigo_firma : '',
                $d->estado_firma === 'firmado' ? (string) $d->conformidad_ip : '',
                $d->estado_firma === 'firmado' ? (string) $d->conformidad_sha256 : '',
                (int) $d->descargas,
                $this->estadoParaConstancia($d),
            ];
        }

        $libro = new LibroExcel();
        $libro->hoja('Constancia', $filas, [
            'anchos' => [6, 12, 34, 16, 34, 18, 18, 18, 20, 26, 18, 68, 10, 24],
            'estiloColumnas' => [1 => LibroExcel::TEXTO, 11 => LibroExcel::TEXTO],
            'estiloFilas' => [0 => LibroExcel::ENCABEZADO, 1 => LibroExcel::PARRAFO, 3 => array_fill(0, 14, LibroExcel::TITULO)],
        ]);

        return $libro->descargar('Constancia de entrega - ' . Meses::nombre($mes) . " {$anio}.xlsx");
    }

    private function estadoParaConstancia(Documento $d): string
    {
        return match (true) {
            $d->firma_colegio === 'pendiente' => 'Por firmar (colegio)',
            $d->firma_colegio === 'parcial'   => 'Firmada en parte (colegio)',
            $d->estado_firma === 'firmado'    => 'Recibida, con conformidad',
            $d->estado_firma === 'en_papel'   => 'Firmada en papel',
            $d->estado_firma === 'visto'      => 'Entregada y abierta',
            default                           => 'Entregada, sin abrir',
        };
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Revisa un archivo de punta a punta. Nunca guarda: lo usan la revisión
     * y el guardado, así que los dos dicen lo mismo.
     *
     * @return array{resultado: array, documento: ?Documento, contenido: ?string, firmas: array}
     */
    private function analizar(Request $request): array
    {
        $datos = $request->validate([
            'archivo' => 'required|file|max:12288',
            'mes'     => 'required|integer|min:1|max:12',
            'anio'    => 'required|integer|min:2000',
        ], [
            'archivo.required' => 'Elige el PDF firmado.',
            'archivo.max'      => 'El archivo pasa de 12 MB.',
        ]);

        /** @var UploadedFile $archivo */
        $archivo = $datos['archivo'];
        $mes = (int) $datos['mes'];
        $anio = (int) $datos['anio'];
        $periodo = Meses::nombre($mes) . " {$anio}";
        $requeridas = FirmaDigitalDeBoletas::requeridas();

        $resultado = [
            'archivo' => $archivo->getClientOriginalName(), 'estado' => 'ok', 'mensaje' => null,
            'trabajador' => null, 'dni' => null, 'numero' => null, 'documento_id' => null,
            'firmas' => [], 'firmas_validas' => 0, 'firmas_requeridas' => $requeridas, 'resultado' => null,
        ];
        $falla = fn (string $estado, string $mensaje) => [
            'resultado' => ['estado' => $estado, 'mensaje' => $mensaje] + $resultado,
            'documento' => null, 'contenido' => null, 'firmas' => [],
        ];

        $contenido = (string) file_get_contents($archivo->getRealPath());
        if (! str_starts_with($contenido, '%PDF-')) {
            return $falla('no_es_pdf', 'No es un PDF.');
        }

        // De quién es: por el nombre con que lo bajó el sistema
        // («BOL-2026-0002_29577480_ZAPANA_QUISPE_JUAN.pdf»), aunque el
        // firmador le agregue algo al final.
        $nombre = $resultado['archivo'];
        $numeroEnNombre = preg_match('/BOL-(\d{4})-(\d{4})/i', $nombre, $n) ? strtoupper($n[0]) : null;
        if (! preg_match('/(?<!\d)(\d{8})(?!\d)/', $nombre, $d)) {
            return $falla('no_reconocido', 'No se sabe de quién es: el nombre del archivo tiene que llevar el DNI, como lo bajó el sistema.');
        }
        $dni = $d[1];

        $empleado = Empleado::where('dni', $dni)->first(['id', 'dni', 'nombre', 'apellido']);
        if (! $empleado) {
            return $falla('no_reconocido', "No hay ningún trabajador con DNI {$dni}.");
        }
        $resultado['dni'] = $dni;
        $resultado['trabajador'] = trim("{$empleado->apellido} {$empleado->nombre}");

        $planilla = Planilla::where('empleado_id', $empleado->id)->where('mes', $mes)->where('anio', $anio)->first();
        $documento = $planilla
            ? Documento::with('planilla', 'empleado')->where('planilla_id', $planilla->id)->where('tipo', 'boleta')->first()
            : null;
        if (! $documento) {
            return $falla('sin_boleta', "No tiene boleta emitida de {$periodo}.");
        }

        $resultado['numero'] = $planilla->numeroDeBoleta();
        $resultado['documento_id'] = $documento->id;
        $falla = fn (string $estado, string $mensaje) => [
            'resultado' => ['estado' => $estado, 'mensaje' => $mensaje] + $resultado,
            'documento' => null, 'contenido' => null, 'firmas' => [],
        ];

        if ($numeroEnNombre && $numeroEnNombre !== $resultado['numero']) {
            return $falla('otro_mes', "Este archivo es la boleta {$numeroEnNombre}, y la de {$periodo} es la {$resultado['numero']}: ¿es de otro mes?");
        }
        if ($documento->firma_colegio === null) {
            return $falla('no_aplica', 'Esta boleta se emitió sin firma digital del colegio: ya se le entregó al trabajador como siempre.');
        }

        // La misma boleta que emitió el sistema, sin cambios: firmar solo
        // AGREGA al final del archivo, así que el original tiene que estar
        // intacto al principio.
        $original = Storage::disk('local')->get($documento->archivo_sin_firma ?? $documento->archivo);
        if ($original === null || ! str_starts_with($contenido, $original)) {
            return $falla('cambiada', 'No es la boleta que emitió el sistema, o cambió después de descargarla '
                . '(por ejemplo, si después se le corrigió un concepto). Vuelve a descargarla para firmar y fírmala de nuevo.');
        }

        $firmas = FirmaDigitalPdf::firmas($contenido);
        $resultado['firmas'] = array_map(fn ($f) => array_intersect_key($f, array_flip(['nombre', 'dni', 'fecha', 'emisor', 'valida'])), $firmas);

        if (! $firmas) {
            return $falla('sin_firma', 'El PDF no tiene ninguna firma digital: fírmalo en ReFirma y vuelve a subirlo.');
        }
        foreach ($firmas as $f) {
            if (! $f['valida']) {
                return $falla('firma_invalida', $f['motivo'] ?? 'Una de las firmas no es válida.');
            }
        }
        if (! end($firmas)['cubre_hasta_el_final']) {
            return $falla('modificada', 'Al archivo se le agregó algo después de la última firma: vuelve a firmarlo y súbelo sin tocarlo.');
        }

        // Cada persona cuenta una vez: firmar dos veces no hace dos firmas.
        $distintas = collect($firmas)->unique(fn ($f) => $f['dni'] ?? $f['nombre'])->values();
        $resultado['firmas_validas'] = $distintas->count();

        $yaTenia = count($documento->firmas_colegio ?? []);
        if ($distintas->count() <= $yaTenia) {
            return $falla('sin_cambios', "Ya estaba así en el sistema ({$yaTenia} firma(s)): no hay nada nuevo que guardar.");
        }

        $resultado['resultado'] = $distintas->count() >= $requeridas ? 'completa' : 'parcial';
        $resultado['mensaje'] = $resultado['resultado'] === 'completa'
            ? 'Lista: al guardarla se le entrega al trabajador.'
            : "Tiene {$distintas->count()} de {$requeridas} firmas: falta la otra antes de entregarla.";

        return [
            'resultado' => $resultado,
            'documento' => $documento,
            'contenido' => $contenido,
            'firmas'    => $distintas->map(fn ($f) => ['nombre' => $f['nombre'], 'dni' => $f['dni'], 'fecha' => $f['fecha'], 'emisor' => $f['emisor']])->all(),
        ];
    }
}
