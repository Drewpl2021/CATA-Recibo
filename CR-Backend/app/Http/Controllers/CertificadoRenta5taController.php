<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Models\ValorLegal;
use App\Support\Firma\FirmaDelColegio;
use App\Support\Firma\FirmaDigitalDeBoletas;
use App\Support\Renta5ta\MotorRenta5ta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * El Certificado de Rentas y Retenciones por Rentas de Quinta Categoría
 * (art. 45 del Reglamento de la Ley del Impuesto a la Renta): el documento
 * anual que el colegio entrega a cada trabajador antes del 1 de marzo del
 * año siguiente, y al cese con su liquidación. Ver MotorRenta5ta::certificado.
 *
 *   GET  /income-tax/certificates?anio=          quién puede recibirlo, quién ya lo tiene y cómo va su firma
 *   GET  /income-tax/{empleado}/certificate      el certificado en PDF (el entregado, o uno al vuelo)
 *   POST /income-tax/certificates/deliver        dejarlo en el expediente de varios (espera la firma del colegio)
 *   POST /income-tax/certificates/sign-here      «Firmar aquí», como las boletas y las hojas
 *   POST /income-tax/certificates/zip/link       el .zip de los marcados o de todos los que lo tienen
 *
 * Como la boleta y las hojas de CTS y gratificación, va con la firma digital
 * del colegio: el trabajador lo ve en Mis Boletas recién cuando está firmado,
 * y da su conformidad con su contraseña.
 */
class CertificadoRenta5taController extends Controller
{
    use \App\Traits\DescargaEnZip;

    public const TIPO = 'certificado_5ta';

    private function anio(Request $request): int
    {
        return (int) $request->validate(['anio' => 'required|integer|min:2000|max:2100'])['anio'];
    }

    /** Quien pudo cobrar en el año: los activos, y los que tienen planilla o historial en él. */
    private function personalDelAnio(int $anio): Collection
    {
        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre')
            ->where(fn ($q) => $q->where('estado', 'activo')
                ->orWhereIn('id', Planilla::where('anio', $anio)->select('empleado_id'))
                ->orWhereIn('id', RentaQuintaPrevia::where('anio', $anio)->select('empleado_id')))
            ->orderBy('apellido')->orderBy('nombre')->get();
    }

    /** Los certificados de un año que están en el expediente. */
    private function delAnio(int $anio): \Illuminate\Database\Eloquent\Builder
    {
        return Documento::where('tipo', self::TIPO)->where('estado_registro', 'activo')->where('periodo_anio', $anio);
    }

    /**
     * GET /income-tax/certificates?anio= — una fila por trabajador del año:
     * si ya se le puede entregar (y por qué no), lo retenido, el saldo, y si
     * su certificado ya está en el expediente y firmado.
     */
    public function index(Request $request)
    {
        $anio = $this->anio($request);
        $motor = new MotorRenta5ta();
        $hojas = $this->delAnio($anio)->get(['id', 'empleado_id', 'firma_colegio', 'estado_firma'])->keyBy('empleado_id');

        $filas = $this->personalDelAnio($anio)->map(function (Empleado $e) use ($motor, $anio, $hojas) {
            $motivo = $motor->porQueNoHayCertificado($e, $anio);
            $c = $motivo ? null : $motor->certificado($e, $anio);
            $hoja = $hojas->get($e->id);

            return [
                'id'            => $e->id,
                'dni'           => $e->dni,
                'nombre'        => trim("{$e->apellido} {$e->nombre}"),
                'cargo'         => $e->cargo?->nombre,
                'estado'        => $e->estado,
                'puede'         => $motivo === null,
                'motivo'        => $motivo,
                'renta_bruta'   => $c['renta_bruta'] ?? null,
                'impuesto'      => $c['impuesto'] ?? null,
                'retenido'      => $c['retenido'] ?? null,
                'saldo'         => $c['saldo'] ?? null,
                'documento_id'  => $hoja?->id,
                'firma_colegio' => $hoja?->firma_colegio,
                'conformidad'   => $hoja?->estado_firma === 'firmado',
            ];
        })->values();

        return response()->json(['success' => true, 'data' => [
            'anio'    => $anio,
            'filas'   => $filas,
            'resumen' => [
                'trabajadores' => $filas->count(),
                'pueden'       => $filas->where('puede', true)->count(),
                'entregados'   => $filas->whereNotNull('documento_id')->count(),
            ],
            'firma'   => FirmaDelColegio::resumen($request, $this->delAnio($anio)),
        ]]);
    }

    /** GET /income-tax/{empleado}/certificate?anio= — el entregado tal cual (firmado), o uno al vuelo. */
    public function hoja(Request $request, string $empleado)
    {
        $anio = $this->anio($request);
        $e = Empleado::findOrFail($empleado);
        $nombre = "Certificado_5ta_{$anio}_{$e->dni}.pdf";

        $guardado = $this->delAnio($anio)->where('empleado_id', $e->id)->value('archivo');
        if ($guardado && Storage::disk('local')->exists($guardado)) {
            return Storage::disk('local')->download($guardado, $nombre);
        }
        if ($motivo = (new MotorRenta5ta())->porQueNoHayCertificado($e, $anio)) {
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        return $this->pdf($e, $anio)->download($nombre);
    }

    /**
     * POST /income-tax/certificates/deliver — deja el certificado en el
     * expediente de cada uno, esperando la firma digital del colegio. Sin
     * ids, a todos los que ya lo pueden recibir. Al que ya lo tiene firmado
     * no se le rehace (está sellado); al que solo lo tiene por firmar, sí,
     * con lo último de sus planillas.
     */
    public function entregar(Request $request)
    {
        $datos = $request->validate([
            'anio'  => 'required|integer|min:2000|max:2100',
            'ids'   => 'nullable|array|max:1000',
            'ids.*' => 'uuid',
        ]);
        $anio = (int) $datos['anio'];
        $motor = new MotorRenta5ta();
        $sellados = $this->delAnio($anio)->whereIn('firma_colegio', ['parcial', 'completa'])->pluck('empleado_id')->flip();

        $personal = empty($datos['ids'])
            ? $this->personalDelAnio($anio)
            : Empleado::whereIn('id', $datos['ids'])->orderBy('apellido')->get();

        $entregados = 0;
        $firmados = 0;
        $saltados = [];
        foreach ($personal as $e) {
            if ($sellados->has($e->id)) {
                $firmados++;
                continue;
            }
            if ($motivo = $motor->porQueNoHayCertificado($e, $anio)) {
                if (! empty($datos['ids'])) {
                    $saltados[] = trim("{$e->apellido} {$e->nombre}") . ": {$motivo}";
                }
                continue;
            }

            $ruta = "documentos/{$e->id}/certificados/certificado_5ta_{$anio}.pdf";
            Storage::disk('local')->put($ruta, $this->pdf($e, $anio, $motor)->output());
            Documento::updateOrCreate(
                ['empleado_id' => $e->id, 'tipo' => self::TIPO, 'periodo_anio' => $anio],
                ['archivo' => $ruta, 'periodo_mes' => 12, 'estado_registro' => 'activo', 'estado_firma' => 'pendiente', ...FirmaDelColegio::camposDeEmision()],
            );
            $entregados++;
        }

        if ($entregados) {
            Auditoria::registrar('aplicó', 'renta de 5ta', null,
                "Dejó el certificado de rentas y retenciones de 5ta del {$anio} en el expediente de {$entregados} trabajador(es).",
                ['entregados' => $entregados, 'saltados' => count($saltados)]);
        }

        return response()->json(['success' => true, 'data' => [
            'entregados' => $entregados,
            'saltados'   => $saltados,
            'message'    => $entregados
                ? "Se dejó el certificado del {$anio} en el expediente de {$entregados} trabajador(es): ahora falta firmarlos."
                    . ($firmados ? " {$firmados} ya estaban firmados por el colegio y no se tocaron." : '')
                : 'No se entregó ningún certificado.' . ($firmados ? " {$firmados} ya estaban firmados." : ''),
        ]]);
    }

    /** POST /income-tax/certificates/sign-here — «Firmar aquí» de los certificados del año. */
    public function firmarAqui(Request $request)
    {
        $anio = $this->anio($request);

        return FirmaDelColegio::firmarTanda(
            $request,
            $this->delAnio($anio),
            "certificados de 5ta del {$anio}",
            fn (Documento $d) => "Certificado de rentas y retenciones de 5ta categoría {$anio}",
            fn (Documento $d) => FirmaDelColegio::avisarAlTrabajador($d, 'certificado de retenciones de 5ta', (string) $anio, femenino: false),
            fn (Documento $d) => null,
            fn (int $firmadas, string $detalle) => Auditoria::registrar('firmó', 'renta de 5ta', null,
                "Firmó desde el sistema {$firmadas} certificado(s) de 5ta del {$anio} con su certificado ({$detalle}).",
                ['firmadas' => $firmadas]),
        );
    }

    /** POST /income-tax/certificates/zip/link — el enlace para bajar los certificados del año en un .zip. */
    public function enlaceZip(Request $request)
    {
        $request->validate([
            'anio'  => 'required|integer|min:2000|max:2100',
            'ids'   => 'nullable|array|max:1000',
            'ids.*' => 'uuid',
        ]);
        $cantidad = $this->delZip((int) $request->input('anio'), $this->marcadosDelPedido($request) ?: null)->count();
        if ($cantidad === 0) {
            return response()->json(['success' => false, 'message' => 'Todavía no hay certificados en el expediente: primero entrégalos.'], 404);
        }

        return $this->enlaceAlZip($request, 'certificados5ta.zip', $request->only('anio'), $cantidad);
    }

    /** GET /income-tax/certificates/zip/download (firmado) — el .zip del enlace de arriba. */
    public function zipConEnlace(Request $request)
    {
        $this->exigirQuienPidioElZip($request, '/renta-5ta');
        $marcados = $this->marcadosDelPedido($request);
        if ($marcados === false) {
            abort(410, 'Este enlace venció: vuelve a pedir la descarga desde el sistema.');
        }
        $anio = (int) $request->query('anio');

        $documentos = $this->delZip($anio, $marcados);
        $hojas = [];
        foreach ($documentos as $d) {
            $nombre = sprintf('Certificado_5ta_%d_%s_%s.pdf', $anio, $d->empleado->dni,
                $this->nombreParaArchivo("{$d->empleado->apellido} {$d->empleado->nombre}"));
            $hojas[$nombre] = fn () => Storage::disk('local')->get($d->archivo);
        }

        return $this->zipDeHojas($hojas, "Certificados_5ta_{$anio}.zip", 'Juntando los certificados de 5ta',
            $this->marcadosSinHoja($marcados, $documentos->pluck('empleado_id')->all(), 'su certificado todavía no se entregó.'));
    }

    private function delZip(int $anio, ?array $marcados): Collection
    {
        return $this->delAnio($anio)->with('empleado:id,dni,nombre,apellido')
            ->when($marcados !== null, fn ($q) => $q->whereIn('empleado_id', $marcados))
            ->get()
            ->sortBy(fn ($d) => "{$d->empleado->apellido} {$d->empleado->nombre}")
            ->values();
    }

    private function pdf(Empleado $e, int $anio, ?MotorRenta5ta $motor = null)
    {
        return Pdf::loadView('certificado-5ta', [
            'e'                => $e,
            'c'                => ($motor ?? new MotorRenta5ta())->certificado($e, $anio),
            'nombreAnio'       => ValorLegal::nombreDelAnio($anio),
            'firmaDigital'     => FirmaDigitalDeBoletas::activa(),
            'firmasRequeridas' => FirmaDigitalDeBoletas::requeridas(),
        ])->setPaper('a4', 'portrait');
    }
}
