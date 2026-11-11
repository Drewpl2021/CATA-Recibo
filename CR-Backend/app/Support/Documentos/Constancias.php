<?php

namespace App\Support\Documentos;

use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\ValorLegal;
use App\Support\Cts\MotorCts;
use App\Support\Firma\FirmaDelColegio;
use App\Support\Firma\FirmaDigitalDeBoletas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Las constancias que el trabajador pide desde Mis Documentos, para un
 * préstamo, un crédito o un trámite:
 *
 *   constancia_trabajo  que trabaja (o trabajó) en el colegio, desde cuándo y
 *                       en qué cargo. A quien ya cesó le sale el certificado
 *                       de trabajo (D.S. N° 001-96-TR, art. 2), con el tiempo
 *                       de servicios y la fecha de cese.
 *   constancia_haberes  lo que cobró en sus últimos 3, 6 o 12 meses pagados,
 *                       de sus planillas: ingresos, descuentos y neto.
 *
 * Se arman al momento con lo que ya tiene el sistema y quedan esperando la
 * firma digital del colegio, como la boleta: RR.HH. las firma con «Firmar
 * aquí» desde Documentos y al trabajador le llega el aviso. Él no da su
 * conformidad (ver ExpedienteDigital::SIN_FIRMA).
 */
final class Constancias
{
    public const MESES_HABERES = [3, 6, 12];

    /** Lo que se puede pedir, con su nombre. */
    public const TIPOS = [
        ExpedienteDigital::CONSTANCIA_TRABAJO => 'constancia de trabajo',
        ExpedienteDigital::CONSTANCIA_HABERES => 'constancia de haberes',
    ];

    /** Por qué no se le puede armar; null si sí. */
    public static function porQueNo(Empleado $empleado, string $tipo, int $meses = 3): ?string
    {
        if (! $empleado->fecha_ingreso) {
            return 'Tu ficha no tiene fecha de ingreso: pídele a Recursos Humanos que la complete.';
        }
        if ($tipo === ExpedienteDigital::CONSTANCIA_HABERES && ! self::planillasPagadas($empleado, $meses)->count()) {
            return 'Todavía no tienes meses pagados en el sistema para armar la constancia de haberes.';
        }

        return null;
    }

    /**
     * La que ya pidió y todavía espera la firma, si hay: así pedirla dos
     * veces no arma dos.
     */
    public static function pendiente(Empleado $empleado, string $tipo): ?Documento
    {
        return Documento::where('empleado_id', $empleado->id)->where('tipo', $tipo)
            ->where('estado_registro', 'activo')
            ->whereIn('firma_colegio', Documento::FIRMA_COLEGIO_EN_CURSO)
            ->latest()->first();
    }

    /** Arma el PDF, lo guarda en su expediente y lo deja esperando la firma del colegio. */
    public static function generar(Empleado $empleado, string $tipo, int $meses = 3, ?string $dirigidoA = null): Documento
    {
        $hoy = now();
        $ruta = sprintf('documentos/%s/constancias/%s_%s.pdf', $empleado->id, $tipo, $hoy->format('Ymd_His'));
        Storage::disk('local')->put($ruta, self::pdf($empleado, $tipo, $meses, $dirigidoA, $hoy)->output());

        return Documento::create([
            'empleado_id'     => $empleado->id,
            'tipo'            => $tipo,
            'archivo'         => $ruta,
            'periodo_mes'     => (int) $hoy->month,
            'periodo_anio'    => (int) $hoy->year,
            'estado_registro' => 'activo',
            'estado_firma'    => 'pendiente',
            ...FirmaDelColegio::camposDeEmision(),
        ]);
    }

    public static function pdf(Empleado $empleado, string $tipo, int $meses, ?string $dirigidoA, ?Carbon $fecha = null)
    {
        $empleado->loadMissing('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre');
        $fecha ??= now();
        $comun = [
            'e'                => $empleado,
            'dirigidoA'        => trim((string) $dirigidoA) ?: null,
            'fechaEmision'     => $fecha,
            'nombreAnio'       => ValorLegal::nombreDelAnio((int) $fecha->year),
            'tipoContrato'     => $empleado->tipoContratoVigente()?->nombre,
            'firmaDigital'     => FirmaDigitalDeBoletas::activa(),
            'firmasRequeridas' => FirmaDigitalDeBoletas::requeridas(),
        ];

        $vista = $tipo === ExpedienteDigital::CONSTANCIA_HABERES ? 'constancia-haberes' : 'constancia-trabajo';
        $datos = $tipo === ExpedienteDigital::CONSTANCIA_HABERES ? ['meses' => self::haberes($empleado, $meses)] : [];

        return Pdf::loadView($vista, $comun + $datos)->setPaper('a4', 'portrait');
    }

    /**
     * Sus últimos meses pagados (los anteriores al mes en curso: el de ahora
     * todavía puede cambiar), del más antiguo al más reciente, con lo que
     * cobró, lo que se le descontó y el neto.
     *
     * @return list<array{mes: int, anio: int, ingresos: float, descuentos: float, neto: float}>
     */
    public static function haberes(Empleado $empleado, int $meses): array
    {
        $planillas = self::planillasPagadas($empleado, $meses)->sortBy(fn ($p) => $p->anio * 100 + $p->mes)->values();
        if ($planillas->isEmpty()) {
            return [];
        }
        $primera = $planillas->first();
        $ultima = $planillas->last();
        $porMes = (new MotorCts())->mesAMes(
            $empleado,
            Carbon::create($primera->anio, $primera->mes, 1),
            Carbon::create($ultima->anio, $ultima->mes, 1)->endOfMonth()->startOfDay(),
        );
        $pagadas = $planillas->map(fn ($p) => $p->anio * 100 + $p->mes)->flip();

        return array_values(array_map(fn ($m) => [
            'mes'        => $m['mes'],
            'anio'       => $m['anio'],
            'ingresos'   => $m['ingresos'],
            'descuentos' => $m['descuentos'],
            'neto'       => $m['neto'],
        ], array_filter($porMes, fn ($m) => $m['situacion'] === 'planilla' && $pagadas->has($m['anio'] * 100 + $m['mes']))));
    }

    /** Sus últimas planillas de meses ya pasados. */
    private static function planillasPagadas(Empleado $empleado, int $meses)
    {
        $esteMes = (int) now()->year * 100 + (int) now()->month;

        return Planilla::where('empleado_id', $empleado->id)->where('estado_registro', 'activo')
            ->whereRaw('(anio * 100 + mes) < ?', [$esteMes])
            ->orderByDesc('anio')->orderByDesc('mes')
            ->limit($meses)->get(['id', 'anio', 'mes']);
    }
}
