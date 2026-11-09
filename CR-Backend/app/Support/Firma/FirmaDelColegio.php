<?php

namespace App\Support\Firma;

use App\Exceptions\AvisoParaElUsuario;
use App\Models\CertificadoFirma;
use App\Models\Documento;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * «Firmar aquí»: la firma digital del colegio sobre los documentos que el
 * sistema emite (la boleta, la hoja de CTS y la de gratificación), con el
 * certificado de quien lo pide y la clave que escribe ahora (no se guarda).
 *
 * Es UN camino para todos: el documento se emite con `firma_colegio` en
 * «pendiente», el trabajador no lo ve hasta que tiene las firmas que pide
 * el colegio (FirmaDigitalDeBoletas::requeridas), y desde la primera firma
 * el PDF queda sellado: nadie lo vuelve a generar.
 *
 * Va por tandas (25 por pedido, hasta 50): la pantalla pide una y con
 * «siguiente» la que sigue. Así ningún pedido se pasa del tiempo del
 * servidor y la barra avanza de verdad. No va a la cola a propósito: la cola
 * guarda su trabajo en la base, y la clave no se guarda en ninguna parte.
 */
final class FirmaDelColegio
{
    /** Cuántos intentos con la clave equivocada antes de esperar 15 minutos. */
    private const INTENTOS = 5;

    /**
     * El resumen del certificado de quien mira y cuántos de estos documentos
     * esperan SU firma; null si todavía no puso uno.
     */
    public static function miCertificado(Request $request, Builder $documentos): ?array
    {
        $certificado = CertificadoFirma::activoDe($request->user());
        if (! $certificado) {
            return null;
        }

        return $certificado->resumen() + ['por_firmar' => self::esperanMiFirma($documentos, $certificado)->count()];
    }

    /**
     * Cómo va la firma de un grupo de documentos (los del mes, los del
     * depósito…): cuántos esperan, cuántos a medias, cuántos ya entregados y
     * con conformidad, y el certificado de quien mira.
     */
    public static function resumen(Request $request, Builder $documentos): array
    {
        $estados = (clone $documentos)->get(['id', 'firma_colegio', 'estado_firma']);
        $digitales = $estados->whereNotNull('firma_colegio');

        return [
            'requeridas'     => FirmaDigitalDeBoletas::requeridas(),
            'emitidas'       => $estados->count(),
            'por_firmar'     => $digitales->where('firma_colegio', 'pendiente')->count(),
            'a_medias'       => $digitales->where('firma_colegio', 'parcial')->count(),
            'entregadas'     => $digitales->where('firma_colegio', 'completa')->count(),
            'conformidad'    => $digitales->where('firma_colegio', 'completa')->where('estado_firma', 'firmado')->count(),
            'mi_certificado' => self::miCertificado($request, $documentos),
        ];
    }

    /**
     * Los ids de los documentos que esperan la firma del colegio y no tienen
     * la de este certificado, en orden (el orden es el que siguen las
     * tandas). Quién firmó se mira aquí y no en SQL porque va en un JSON, y
     * la búsqueda en un JSON no es igual en MySQL que en SQLite.
     *
     * @return Collection<int, string>
     */
    public static function esperanMiFirma(Builder $documentos, CertificadoFirma $certificado): Collection
    {
        return (clone $documentos)
            ->whereIn('firma_colegio', Documento::FIRMA_COLEGIO_EN_CURSO)
            ->orderBy('documentos.id')
            ->get(['documentos.id', 'documentos.firmas_colegio'])
            ->reject(fn (Documento $d) => self::yaLaFirmo($d, $certificado->dni, $certificado->nombre))
            ->pluck('id')
            ->values();
    }

    public static function yaLaFirmo(Documento $documento, ?string $dni, string $nombre): bool
    {
        return collect($documento->firmas_colegio ?? [])
            ->contains(fn ($f) => $dni ? ($f['dni'] ?? null) === $dni : ($f['nombre'] ?? null) === $nombre);
    }

    /**
     * Firma UNA TANDA de los documentos que esperan la firma de quien pide,
     * y responde lo mismo para todos: cuántas firmó, cuántas quedaron
     * completas (ya le llegaron al trabajador), cuántas esperan otra firma,
     * los errores y desde dónde sigue la próxima tanda.
     *
     * @param  Builder                         $documentos  el grupo (los del mes, los del depósito…)
     * @param  string                          $queSon      «boletas de octubre 2026», para el mensaje si no hay ninguna
     * @param  Closure(Documento): string      $motivo      lo que va en la firma («Boleta de pago BOL-…»)
     * @param  Closure(Documento): void        $alCompletar se le avisa al trabajador
     * @param  Closure(Documento): ?string     $numero      el número que se muestra junto al nombre en un error
     * @param  Closure(int, string): void      $auditar     una línea en la auditoría: cuántas firmó y con qué certificado
     */
    public static function firmarTanda(
        Request $request,
        Builder $documentos,
        string $queSon,
        Closure $motivo,
        Closure $alCompletar,
        Closure $numero,
        Closure $auditar,
    ): JsonResponse {
        $datos = $request->validate([
            'clave'  => 'required|string|max:200',
            'desde'  => 'nullable|string|max:40',
            'limite' => 'nullable|integer|min:1|max:50',
        ], ['clave.required' => 'Escribe la clave de tu certificado.']);
        $desde = $datos['desde'] ?? null;
        $limite = (int) ($datos['limite'] ?? 25);

        $certificado = CertificadoFirma::activoDe($request->user());
        if (! $certificado) {
            return self::rechazo('Todavía no pusiste tu certificado digital: hazlo en Ajustes del sistema → Boletas.');
        }
        if ($certificado->vencido()) {
            return self::rechazo('Tu certificado venció el ' . $certificado->valido_hasta->format('d/m/Y') . ': renuévalo y ponlo de nuevo en Ajustes.');
        }

        // Contra adivinar la clave: 5 intentos fallidos y 15 minutos de espera.
        // Las tandas buenas no cuentan; solo la clave equivocada.
        $intentos = 'clave-certificado:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($intentos, self::INTENTOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($intentos) / 60);
            return self::rechazo("Pusiste mal la clave varias veces. Espera {$minutos} minuto(s) y vuelve a intentarlo.", 429);
        }
        try {
            $llaves = CertificadoDigital::abrir($certificado->archivo(), $datos['clave']);
        } catch (AvisoParaElUsuario $e) {
            if (str_contains($e->getMessage(), 'clave')) {
                RateLimiter::hit($intentos, 15 * 60);
            }
            return self::rechazo($e->getMessage());
        }
        RateLimiter::clear($intentos);

        $pendientes = self::esperanMiFirma($documentos, $certificado);
        if ($pendientes->isEmpty() && $desde === null) {
            return self::rechazo("No hay {$queSon} esperando tu firma.");
        }
        // «desde» es el id de la última de la tanda anterior: se sigue después
        // de ella, así una que falló no se reintenta en la misma pasada.
        $porHacer = $desde === null ? $pendientes : $pendientes->filter(fn ($id) => strcmp($id, $desde) > 0)->values();
        $lote = $porHacer->take($limite)->values();

        $requeridas = FirmaDigitalDeBoletas::requeridas();
        $quien = mb_substr((string) $request->user()->name, 0, 100);
        $cuenta = ['firmadas' => 0, 'entregadas' => 0, 'a_medias' => 0];
        $errores = [];

        foreach (Documento::with('planilla', 'empleado')->whereIn('id', $lote)->orderBy('id')->get() as $documento) {
            try {
                $estado = self::firmarUno($documento, $llaves, $certificado, $requeridas, $quien, $motivo);
                if ($estado !== null) {
                    $cuenta['firmadas']++;
                    $cuenta[$estado === 'completa' ? 'entregadas' : 'a_medias']++;
                    if ($estado === 'completa') {
                        $alCompletar($documento->refresh());
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $errores[] = [
                    'trabajador' => trim("{$documento->empleado?->apellido} {$documento->empleado?->nombre}"),
                    'numero'     => $numero($documento),
                    'mensaje'    => $e instanceof AvisoParaElUsuario ? $e->getMessage() : 'No se pudo firmar: vuelve a intentarlo.',
                ];
            }
        }

        if ($cuenta['firmadas'] > 0) {
            $auditar($cuenta['firmadas'], $certificado->nombre
                . ($cuenta['entregadas'] ? ": {$cuenta['entregadas']} ya se entregaron" : '')
                . ($cuenta['a_medias'] ? ", {$cuenta['a_medias']} esperan la otra firma" : '')
                . ($errores ? ', ' . count($errores) . ' no se pudieron firmar' : ''));
        }

        return response()->json(['success' => true, 'data' => $cuenta + [
            'errores'    => $errores,
            // Para la barra: cuántas faltaban al empezar esta tanda y cuántas vio.
            'total'      => $porHacer->count(),
            'procesadas' => $lote->count(),
            // La tanda que sigue empieza después de esta; null si ya no hay.
            'siguiente'  => $porHacer->count() > $lote->count() ? $lote->last() : null,
        ]]);
    }

    /**
     * Firma un documento. Devuelve 'completa' o 'parcial', o null si mientras
     * tanto otra pestaña ya lo firmó con este certificado.
     *
     * Queda igual que si se hubiera firmado en ReFirma y subido: la firma va
     * al final del PDF emitido y se comprueba con el mismo lector que revisa
     * las subidas. Si no cuadra, no se guarda.
     */
    private static function firmarUno(Documento $documento, array $llaves, CertificadoFirma $certificado, int $requeridas, string $quien, Closure $motivo): ?string
    {
        return DB::transaction(function () use ($documento, $llaves, $certificado, $requeridas, $quien, $motivo) {
            // Otra pestaña pudo firmarlo mientras tanto: se vuelve a mirar, con candado.
            $actual = Documento::with('planilla', 'empleado')->lockForUpdate()->findOrFail($documento->id);
            if (! $actual->esperaFirmaDelColegio() || self::yaLaFirmo($actual, $certificado->dni, $certificado->nombre)) {
                return null;
            }

            $pdf = Storage::disk('local')->get($actual->archivo);
            if ($pdf === null) {
                throw new AvisoParaElUsuario('No se encontró su PDF: vuelve a emitirla.');
            }
            $firmado = FirmadorPdf::firmar($pdf, $llaves['certificado'], $llaves['llave'], $llaves['cadena'], [
                'nombre' => $llaves['nombre'],
                'motivo' => $motivo($actual),
                'lugar'  => 'Juliaca, Perú',
            ]);

            $firmas = FirmaDigitalPdf::firmas($firmado);
            $ultima = end($firmas);
            if (! $firmas || collect($firmas)->contains(fn ($f) => ! $f['valida']) || ! $ultima['cubre_hasta_el_final']) {
                throw new AvisoParaElUsuario('La firma no salió bien: no se guardó.');
            }
            $distintas = collect($firmas)->unique(fn ($f) => $f['dni'] ?? $f['nombre'])->values();
            $estado = $distintas->count() >= $requeridas ? 'completa' : 'parcial';

            self::guardarFirmada(
                $actual,
                $firmado,
                $distintas->map(fn ($f) => ['nombre' => $f['nombre'], 'dni' => $f['dni'], 'fecha' => $f['fecha'], 'emisor' => $f['emisor']])->all(),
                $estado,
                $quien,
            );

            return $estado;
        });
    }

    /**
     * Guarda el PDF firmado y su estado: lo mismo venga de una subida
     * (ReFirma) o de «Firmar aquí». Va dentro de la transacción de quien
     * llama. El firmado queda junto al emitido, con «_firmada» en el nombre.
     *
     * Mientras falte una firma, el emitido se guarda: contra él se compara
     * la próxima subida. Cuando ya tiene todas, sobra: el firmado lo lleva
     * dentro intacto (firmar solo agrega al final), así que se borra para no
     * guardar cada documento dos veces.
     */
    public static function guardarFirmada(Documento $documento, string $contenido, array $firmas, string $estado, string $quien): void
    {
        $emitido = $documento->archivo_sin_firma ?? $documento->archivo;
        $ruta = preg_replace('/\.pdf$/i', '', $emitido) . '_firmada.pdf';

        Storage::disk('local')->put($ruta, $contenido);

        $completa = $estado === 'completa';
        $documento->forceFill([
            'archivo_sin_firma'         => $completa ? null : $emitido,
            'archivo'                   => $ruta,
            'firmas_colegio'            => $firmas,
            'firma_colegio'             => $estado,
            'firma_colegio_subida_por'  => $quien,
            'firma_colegio_completa_en' => $completa ? now() : null,
        ])->save();

        // Recién cuando la base quedó guardada: si algo falla a mitad, el
        // emitido sigue ahí.
        if ($completa && $emitido !== $ruta) {
            DB::afterCommit(fn () => Storage::disk('local')->delete($emitido));
        }
    }

    /**
     * Borra las copias sin firmar de los documentos que ya tienen todas sus
     * firmas (las que quedaron de antes de que esto se hiciera solo). Devuelve
     * cuántas borró y cuánto espacio liberó.
     *
     * @return array{borradas: int, bytes: int}
     */
    public static function quitarCopiasSinFirma(): array
    {
        $borradas = 0;
        $bytes = 0;
        Documento::where('firma_colegio', 'completa')->whereNotNull('archivo_sin_firma')
            ->select(['id', 'archivo', 'archivo_sin_firma'])
            ->chunkById(200, function ($documentos) use (&$borradas, &$bytes) {
                foreach ($documentos as $d) {
                    $disco = Storage::disk('local');
                    // Solo si el firmado existe: es el que lleva al emitido adentro.
                    if ($d->archivo_sin_firma !== $d->archivo && $disco->exists($d->archivo)) {
                        if ($disco->exists($d->archivo_sin_firma)) {
                            $bytes += $disco->size($d->archivo_sin_firma);
                            $disco->delete($d->archivo_sin_firma);
                            $borradas++;
                        }
                        $d->forceFill(['archivo_sin_firma' => null])->save();
                    }
                }
            });

        return ['borradas' => $borradas, 'bytes' => $bytes];
    }

    /**
     * Los campos de un documento recién emitido que espera la firma del
     * colegio. Si se vuelve a emitir (se anuló y se rehízo), empieza de cero.
     */
    public static function camposDeEmision(): array
    {
        return [
            'firma_colegio'             => FirmaDigitalDeBoletas::activa() ? 'pendiente' : null,
            'firmas_colegio'            => null,
            'archivo_sin_firma'         => null,
            'firma_colegio_subida_por'  => null,
            'firma_colegio_completa_en' => null,
        ];
    }

    /**
     * Le avisa al trabajador que su hoja ya está firmada por el colegio: en
     * la campanita y por correo, como con la boleta. Deja anotado a quién y
     * cuándo se le avisó, que es lo que permite contestar «a mí nunca me
     * avisaron» con una fecha y un correo.
     *
     * @param  string  $queEs     «hoja de CTS», «hoja de gratificación», «certificado de retenciones de 5ta»
     * @param  string  $periodo   «noviembre 2026», «julio 2026», «2026»
     * @param  bool    $femenino  «lista / Revísala» o «listo / Revísalo»
     */
    public static function avisarAlTrabajador(Documento $documento, string $queEs, string $periodo, bool $femenino = true): void
    {
        $usuario = \App\Models\User::where('empleado_id', $documento->empleado_id)->first();
        if (! $usuario) {
            return;
        }

        \App\Models\Notificacion::create([
            'user_id'      => $usuario->id,
            'tipo'         => 'documento_disponible',
            'titulo'       => "Tu {$queEs} de {$periodo} ya está " . ($femenino ? 'lista' : 'listo'),
            'mensaje'      => $femenino
                ? 'Ya está firmada por el colegio. Revísala en Mis Boletas y da tu conformidad con tu contraseña.'
                : 'Ya está firmado por el colegio. Revísalo en Mis Boletas y da tu conformidad con tu contraseña.',
            'documento_id' => $documento->id,
        ]);
        $documento->registrarAviso($usuario->email);

        if ($usuario->email) {
            $empleado = $documento->empleado;
            \Illuminate\Support\Facades\Mail::to($usuario->email)->queue(new \App\Mail\HojaLista(
                trim("{$empleado?->nombre} {$empleado?->apellido}"), $queEs, $periodo, $femenino
            ));
        }
    }

    private static function rechazo(string $mensaje, int $codigo = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $mensaje], $codigo);
    }
}
