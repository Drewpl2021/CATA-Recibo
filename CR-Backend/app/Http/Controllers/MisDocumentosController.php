<?php
namespace App\Http\Controllers;
use App\Models\Documento;
use App\Models\Planilla;
use App\Support\Documentos\ExpedienteDigital;
use App\Traits\ListadoPaginado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class MisDocumentosController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /mis-documentos?tipo=&anio=&sin_firmar=&page=&size=&search=
     *
     * Los documentos del trabajador de la sesión. El empleado sale del token,
     * nunca de la petición.
     *
     * Con el tiempo esto crece sin parar —una boleta por mes, más contratos y
     * constancias—, así que va paginado como el resto: sin ?page devolvía la
     * carrera entera del trabajador de una sola vez.
     *
     * Los tres filtros son los que piden las tres pantallas que cuelgan de
     * aquí, y cada uno ahorra traer documentos que no se van a pintar:
     *
     *   Mis Boletas   -> ?tipo=boleta&anio=2026  (la pantalla ya elige el año)
     *                    y ?tipo=cts,gratificacion&anio=2026 (sus hojas, que
     *                    también llegan ahí)
     *   Mis Documentos-> sin filtro, todo lo suyo
     *   La campanita  -> ?sin_firmar=1           (solo lo que le falta firmar)
     */
    public function index(Request $request)
    {
        $empleado_id = $request->user()->empleado_id;

        // Una cuenta sin ficha —las de Administración y RR.HH., que son para
        // operar el sistema— simplemente no tiene documentos, que no es lo
        // mismo que no tener permiso. Antes acá salía un 403 y la campana de
        // la barra de arriba fallaba en CADA pantalla del administrador.
        // Ahora devuelve la lista vacía, que es la verdad.

        // Ordenado por el periodo que representa, no por cuándo se registró.
        //
        // Un documento se ordena por lo que dice ser: la boleta de agosto va
        // después de la de julio aunque las dos se hayan emitido el mismo día
        // —que es justo lo que pasa cuando se emiten todas de golpe, y ahí
        // created_at no desempata nada—. Lo que no tiene planilla (contratos,
        // constancias) cae al final por su fecha, que es lo único que tiene.
        // empleado_id lleva su tabla delante porque con el join lo tienen las
        // dos. Las demás columnas de aquí abajo (tipo, estado_firma) son solo
        // de documentos, y van sin calificar a propósito: el punto en
        // ListadoPaginado significa "relación", no "tabla".
        // Se trae la sede y la cuenta para poder pintar la "Entidad" y los
        // datos de contacto de la fila sin una consulta por boleta.
        $query = Documento::whereRaw($empleado_id ? '1 = 1' : '1 = 0')
            ->where('documentos.empleado_id', $empleado_id)
            // La que espera la firma digital del colegio todavía no es suya.
            ->visiblesParaElTrabajador()
            ->with(['planilla', 'empleado:id,nombre,apellido,telefono,sede_id', 'empleado.sede:id,nombre', 'empleado.usuario:id,empleado_id,email'])
            // Quién firmó por el colegio (nombre y DNI completo) es para RR.HH.;
            // al trabajador le basta saber que ya está firmada (firma_colegio).
            ->afterQuery(fn ($documentos) => $documentos->each->makeHidden(['firmas_colegio']))
            ->leftJoin('planilla', 'documentos.planilla_id', '=', 'planilla.id')
            ->select('documentos.*')
            // Un archivo anterior no tiene planilla: su periodo es el suyo.
            ->orderByRaw('COALESCE(planilla.anio, documentos.periodo_anio) DESC')
            ->orderByRaw('COALESCE(planilla.mes, documentos.periodo_mes) DESC')
            ->orderByDesc('documentos.created_at');

        // Uno o varios tipos separados por coma (las hojas de CTS y de
        // gratificación van juntas en Mis Boletas).
        if ($request->filled('tipo')) {
            $query->whereIn('tipo', array_slice(array_filter(array_map('trim', explode(',', (string) $request->input('tipo')))), 0, 10));
        }

        // El año es el de la planilla, no el de created_at: una boleta de
        // diciembre emitida en enero pertenece al año que se trabajó.
        if ($request->filled('anio')) {
            $query->whereRaw('COALESCE(planilla.anio, documentos.periodo_anio) = ?', [(int) $request->input('anio')]);
        }

        // "Sin firmar" es lo que le falta firmar: la hoja de vida no se firma,
        // así que no tiene nada que hacer en la campana.
        if ($request->boolean('sin_firmar')) {
            $query->whereNotIn('estado_firma', Documento::FIRMA_RESUELTA)
                ->whereNotIn('tipo', ExpedienteDigital::SIN_FIRMA);
        }

        return $this->responderListado(
            $request,
            $query,
            ['tipo'],
            // Cuántos le faltan por firmar: es el número del recuadro de
            // arriba, y tiene que contarse sobre TODOS, no sobre la página.
            // Solo sobre lo que se firma: su CV no es un pendiente.
            fn (Builder $suyos) => $this->conteoPorEstado((clone $suyos)->whereNotIn('tipo', ExpedienteDigital::SIN_FIRMA), 'estado_firma', [
                'pendientes' => 'pendiente',
                'firmados'   => 'firmado',
            ])
        );
    }

    public function visto(Request $request, $id)
    {
        $empleado_id = $request->user()->empleado_id;
        $documento = Documento::where('id', $id)
            ->where('empleado_id', $empleado_id)
            ->where('estado_registro', 'activo')
            ->firstOrFail();

        if (! ExpedienteDigital::seFirma($documento->tipo)) {
            return response()->json([
                'success' => false,
                'message' => ExpedienteDigital::porQueNoSeFirma($documento->tipo),
            ], 422);
        }

        if ($documento->estado_firma !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede marcar como visto si está pendiente.'
            ], 422);
        }

        $documento->update([
            'estado_firma' => 'visto',
            'fecha_visto'  => now(),
        ]);

        return response()->json(['success' => true, 'data' => $documento]);
    }

    public function firmar(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user        = $request->user();
        $empleado_id = $user->empleado_id;
        $intentosKey = 'intentos_firma_' . $user->id;
        $intentos    = Cache::get($intentosKey, 0);

        if (!Hash::check($request->password, $user->password)) {
            $intentos++;
            Cache::put($intentosKey, $intentos, now()->addMinutes(15));

            if ($intentos >= 3) {
                // Cierra la sesión tras el tercer intento fallido
                $user->tokens()->delete();
                Cache::forget($intentosKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Se superó el límite de 3 intentos. Su sesión ha sido cerrada.'
                ], 403);
            }

            $intentosRestantes = 3 - $intentos;
            return response()->json([
                'success' => false,
                'message' => "Contraseña incorrecta. Le quedan {$intentosRestantes} intento(s).",
            ], 401);
        }

        // Contraseña correcta: reinicia el contador
        Cache::forget($intentosKey);

        // Todo junto y con candado: dos clics a la vez no firman dos veces, y si
        // el PDF no se puede armar, el documento no queda firmado.
        return DB::transaction(function () use ($request, $user, $id, $empleado_id) {
            $documento = Documento::where('id', $id)
                ->where('empleado_id', $empleado_id)
                ->where('estado_registro', 'activo')
                ->lockForUpdate()
                ->firstOrFail();

            // Se revisa aquí y no solo escondiendo el botón: una pantalla no es
            // una cerradura.
            if (! ExpedienteDigital::seFirma($documento->tipo)) {
                return response()->json([
                    'success' => false,
                    'message' => ExpedienteDigital::porQueNoSeFirma($documento->tipo),
                ], 422);
            }

            if ($documento->esperaFirmaDelColegio()) {
                return response()->json([
                    'success' => false,
                    'message' => \App\Support\Documentos\AccesoADocumento::enFirmaDelColegio($documento),
                ], 422);
            }

            if ($documento->estado_firma === 'firmado') {
                return response()->json([
                    'success' => false,
                    'message' => 'El documento ya está firmado.'
                ], 422);
            }

            if ($documento->estado_firma === 'en_papel') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta boleta es de registro: ya la firmaste en papel en su momento.'
                ], 422);
            }

            $empleado = $user->empleado;

            $documento->update([
                'estado_firma' => 'firmado',
                'fecha_firma'  => now(),
                'firmado_por'  => $empleado->nombre . ' ' . $empleado->apellido,
                'codigo_firma' => strtoupper(Str::random(8)) . '-' . time(),
                'conformidad_ip'          => mb_substr((string) $request->ip(), 0, 45),
                'conformidad_dispositivo' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);

            // Si es una boleta, se vuelve a armar el PDF ahora que ya quedó firmada —
            // así el archivo congelado en disco sí incluye el sello de firma+huella y
            // el texto de verificación (si no se regenerara aquí, quedaría archivada
            // para siempre la versión de ANTES de firmar).
            //
            // Con la firma digital del colegio NO: el PDF ya está sellado y
            // tocarlo invalidaría esas firmas. Su conformidad queda en el
            // documento (fecha, código) y sale en la Constancia de entrega.
            if ($documento->tipo === 'boleta' && $documento->planilla_id && $documento->firma_colegio === null) {
                $planilla = Planilla::find($documento->planilla_id);
                if ($planilla) {
                    app(BoletaController::class)->construirBoleta(
                        $empleado->load('area', 'cargo'),
                        $planilla,
                        (int) $planilla->mes,
                        (int) $planilla->anio,
                        forzarGuardado: true
                    );
                }
            }

            // La huella va al final: es la del archivo que queda guardado. Si
            // alguien lo cambiara después, ya no coincidiría.
            $archivo = $documento->archivo && Storage::disk('local')->exists($documento->archivo)
                ? Storage::disk('local')->get($documento->archivo) : null;
            $documento->update(['conformidad_sha256' => $archivo !== null ? hash('sha256', $archivo) : null]);

            return response()->json([
                'success' => true,
                'message' => 'Documento firmado correctamente.',
                'data'    => $documento
            ]);
        });
    }

    /**
     * POST /mis-documentos/hoja-de-vida — el trabajador sube su propio CV.
     *
     * El empleado sale del token, nunca del cuerpo: sin eso cualquiera podía
     * colgarle un archivo al expediente de otro. Reemplaza al CV que tuviera
     * (el anterior queda dado de baja, como cuando lo sube RR.HH.).
     */
    public function subirHojaDeVida(Request $request)
    {
        // Sin `tipo` es la hoja de vida, como antes: la ruta vieja
        // (my-documents/resume) sigue sirviendo igual.
        $tipo = $request->input('tipo', ExpedienteDigital::HOJA_DE_VIDA);

        $request->validate([
            'archivo' => ExpedienteDigital::REGLA_ARCHIVO,
            // Solo los suyos: una boleta o un contrato los emite el colegio,
            // y dejar que se los suba él mismo sería dejarle escribir su
            // propia boleta.
            'tipo'    => ['nullable', Rule::in(ExpedienteDigital::PERSONALES)],
        ], [
            'archivo.required' => 'Elige el archivo que quieres subir.',
            'archivo.mimes'    => 'El archivo tiene que ser PDF, Word o una imagen (JPG o PNG).',
            'archivo.max'      => 'El archivo pasa de 5 MB.',
            'tipo.in'          => 'Ese tipo de documento no lo subes tú: lo emite el colegio.',
        ]);

        $empleadoId = $request->user()->empleado_id;
        if (! $empleadoId) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta no está vinculada a una ficha de trabajador.',
            ], 403);
        }

        $documento = ExpedienteDigital::guardar($empleadoId, $tipo, $request->file('archivo'));

        return response()->json(['success' => true, 'data' => $documento], 201);
    }

    /**
     * POST /my-documents/bundle — varias de sus boletas y hojas juntas: en un
     * .zip (cada una tal cual, con su firma digital) o en un solo PDF para
     * leer o imprimir (ahí la firma no viaja: vale sobre cada archivo).
     *
     * Solo entra lo suyo y lo que ya puede bajar (con su conformidad), con
     * los mismos controles que al bajar una por una; cada una queda anotada
     * como descargada. Lo que no entra se dice: en el .zip, en
     * NO_INCLUIDAS.txt; en los dos, en la cabecera X-No-Incluidas.
     */
    public function juntar(Request $request)
    {
        $datos = $request->validate([
            'ids'     => 'required|array|min:1|max:60',
            'ids.*'   => 'uuid',
            'formato' => 'required|in:zip,pdf',
        ], ['ids.max' => 'Elige hasta 60 a la vez.']);
        $usuario = $request->user();
        if (! $usuario->empleado_id) {
            return response()->json(['success' => false, 'message' => 'Tu cuenta no tiene un trabajador vinculado.'], 403);
        }

        $documentos = Documento::with('planilla')
            ->where('empleado_id', $usuario->empleado_id)
            ->whereIn('id', $datos['ids'])
            ->whereIn('tipo', self::SE_JUNTAN)
            ->where('estado_registro', 'activo')
            ->visiblesParaElTrabajador()
            ->get()
            ->sortBy(fn (Documento $d) => sprintf('%04d-%02d-%s', $d->planilla?->anio ?? $d->periodo_anio, $d->planilla?->mes ?? $d->periodo_mes, $d->tipo))
            ->values();

        $archivos = [];
        $fuera = [];
        foreach ($documentos as $d) {
            $nombre = $this->nombreParaJuntar($d);
            if ($motivo = \App\Support\Documentos\AccesoADocumento::porQueNoPuedeDescargar($d, $usuario)) {
                $fuera[] = "{$nombre}: {$motivo}";
                continue;
            }
            $bytes = $this->bytesParaElTrabajador($request, $d);
            if ($bytes === null) {
                $fuera[] = "{$nombre}: su archivo no está disponible; pídelo a Recursos Humanos.";
                continue;
            }
            $archivos[$nombre] = $bytes;
        }
        // Lo que pidió y no es suyo, o no existe, no se nombra: solo se cuenta.
        $faltan = count(array_unique($datos['ids'])) - $documentos->count();
        if ($faltan > 0) {
            $fuera[] = "{$faltan} documento(s) que no se encontraron entre los tuyos.";
        }

        if (! $archivos) {
            return response()->json(['success' => false, 'message' => $fuera
                ? 'Ninguno se puede bajar todavía. ' . $fuera[0]
                : 'No hay documentos para bajar.'], 422);
        }

        $base = 'Mis documentos ' . now()->format('Y-m-d');
        if ($datos['formato'] === 'pdf') {
            ['pdf' => $pdf, 'fallaron' => $fallaron] = \App\Support\Pdf\JuntarPdf::juntar($archivos, $base);
            foreach ($fallaron as $f) {
                $fuera[] = "{$f}: no se pudo leer para juntarlo; bájalo en el .zip.";
            }
            if ($pdf === '') {
                return response()->json(['success' => false, 'message' => 'No se pudieron juntar en un PDF: bájalos en .zip.'], 422);
            }

            return response($pdf, 200, [
                'Content-Type'                  => 'application/pdf',
                'Content-Disposition'           => 'attachment; filename="' . $base . '.pdf"',
                'X-No-Incluidas'                => (string) count($fuera),
                'Access-Control-Expose-Headers' => 'X-No-Incluidas',
            ]);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'mis');
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        foreach ($archivos as $nombre => $bytes) {
            $zip->addFromString($nombre, $bytes);
        }
        if ($fuera) {
            $zip->addFromString('NO_INCLUIDAS.txt', "No van en este archivo:\r\n\r\n- " . implode("\r\n- ", $fuera) . "\r\n");
        }
        $zip->close();

        return response()->download($ruta, $base . '.zip', [
            'Content-Type'                  => 'application/zip',
            'X-No-Incluidas'                => (string) count($fuera),
            'Access-Control-Expose-Headers' => 'X-No-Incluidas',
        ])->deleteFileAfterSend();
    }

    /** Lo que se puede juntar: lo que tiene periodo y se baja con su conformidad. */
    private const SE_JUNTAN = ['boleta', 'cts', 'gratificacion', 'certificado_5ta'];

    /** «2026-10 Boleta BOL-2026-0002.pdf», «2026-11 Hoja de CTS.pdf». */
    private function nombreParaJuntar(Documento $d): string
    {
        $anio = $d->planilla?->anio ?? $d->periodo_anio;
        $mes = $d->planilla?->mes ?? $d->periodo_mes;
        $que = match ($d->tipo) {
            'boleta' => 'Boleta ' . ($d->planilla?->numeroDeBoleta() ?? \App\Support\Meses::nombre((int) $mes)),
            'cts'             => 'Hoja de CTS',
            'certificado_5ta' => 'Certificado de retenciones de 5ta',
            default           => 'Hoja de gratificación',
        };

        return sprintf('%04d-%02d %s.pdf', $anio, $mes, $que);
    }

    /**
     * Los bytes que el trabajador se lleva: su boleta con UNA copia (la
     * misma que baja en Mis Boletas, que además la anota como descargada) y
     * la hoja tal cual está guardada. null si no hay archivo.
     */
    private function bytesParaElTrabajador(Request $request, Documento $d): ?string
    {
        if ($d->tipo === 'boleta' && $d->planilla) {
            $pedido = $request->duplicate(['ver' => 0], []);
            $pedido->setUserResolver(fn () => $request->user());
            $respuesta = app(MiBoletaController::class)->descargar($pedido, (int) $d->planilla->mes, (int) $d->planilla->anio);
            if ($respuesta->getStatusCode() !== 200) {
                return null;
            }
            if ($respuesta instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
                return (string) file_get_contents($respuesta->getFile()->getPathname());
            }
            if ($respuesta instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                ob_start();
                $respuesta->sendContent();

                return (string) ob_get_clean();
            }

            return (string) $respuesta->getContent();
        }

        if (! $d->archivo || ! Storage::disk('local')->exists($d->archivo)) {
            return null;
        }
        $d->registrarDescarga();

        return Storage::disk('local')->get($d->archivo);
    }
}
