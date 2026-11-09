<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        El Certificado de Rentas y Retenciones por Rentas de Quinta Categoría
        (art. 45 del Reglamento de la Ley del Impuesto a la Renta, D.S. N°
        122-94-EF): lo que el colegio le pagó y le retuvo en el año, cómo sale
        el impuesto y el saldo. Con el mismo orden y los mismos colores que las
        hojas de CTS y de gratificación, y la firma digital del colegio
        (partials/firma-digital).

        Lo dibuja dompdf: nada de flex ni de variables CSS.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 12mm 16mm 10mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.6px; color: #1F2B33; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: middle; }

        .cab__logo img { width: 52px; height: 52px; }
        .cab__inst { font-size: 7.5px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 8px; line-height: 1.4; color: #4A5961; }
        .cab__lugar strong { color: #1F2B33; font-size: 9px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }

        .titulo { margin-top: 8px; text-align: center; }
        .titulo h1 { font-size: 12.5px; letter-spacing: .3px; }
        .titulo p { font-size: 7.3px; color: #4A5961; margin-top: 2px; }
        .titulo .periodo { display: inline-block; margin-top: 5px; padding: 3px 12px; background: #EC7A3A; color: #FFFFFF; font-size: 9.5px; font-weight: bold; }

        .constancia { margin-top: 8px; font-size: 8.6px; line-height: 1.5; text-align: justify; }

        .barra { background: #D3E3E8; font-weight: bold; font-size: 7.8px; text-transform: uppercase; padding: 4px 6px; margin-top: 8px; }
        .datos td { padding: 3px 6px; border-bottom: 1px solid #DCE7EB; vertical-align: top; }
        .rotulo { font-size: 6.6px; font-weight: bold; text-transform: uppercase; color: #4A5961; }
        .valor { font-size: 8.6px; color: #1F2B33; margin-top: 1px; }
        .valor--grande { font-size: 10px; font-weight: bold; }

        .cuadro { margin-top: 4px; border: 1px solid #9FB6BF; }
        .cuadro th { background: #EDF3F5; font-size: 6.8px; text-transform: uppercase; text-align: left; padding: 4px 8px; border-bottom: 1px solid #9FB6BF; }
        .cuadro td { padding: 3.5px 8px; border-bottom: 1px solid #DCE7EB; font-size: 8.4px; }
        .cuadro .num { text-align: right; white-space: nowrap; }
        .cuadro .detalle { color: #6B7A82; font-size: 7.4px; }
        .cuadro tr.total td { background: #D3E3E8; font-weight: bold; border-bottom: none; }
        .cuadro tr.resta td { color: #4A5961; }

        .dos-columnas td.col { vertical-align: top; }

        .neto { margin-top: 8px; border: 1.5px solid #1F2B33; }
        .neto td { padding: 6px 10px; }
        .neto .rotulo-neto { font-size: 9.5px; font-weight: bold; }
        .neto .monto { text-align: right; font-size: 12.5px; font-weight: bold; }
        .saldo { padding: 5px 10px; background: #EDF3F5; font-size: 8px; border-top: 1px solid #9FB6BF; line-height: 1.45; }

        .nota { font-size: 6.8px; color: #6B7A82; margin-top: 6px; line-height: 1.45; }
        .lugar-fecha { margin-top: 8px; text-align: right; font-size: 8.6px; }
    </style>
</head>
<body>
@php
    $colegioNombre    = 'Asociación Educativa Colegio Adventista Túpac Amaru';
    $colegioRuc       = config('institucion.ruc');
    $colegioDireccion = 'Jr. Moquegua N° 852, Juliaca';

    $meses   = \App\Support\Meses::NOMBRES;
    $fecha   = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '---';
    $enLetra = function ($f) use ($meses) {
        $x = \Carbon\Carbon::parse($f);
        return $x->day . ' de ' . mb_strtolower($meses[$x->month]) . ' de ' . $x->year;
    };
    $monto   = fn (float $v) => abs($v) < 0.005 ? '-' : number_format($v, 2);
    $uit     = number_format($c['uit'], 2);
    $cesado  = $e->estado === 'inactivo' && $e->fecha_cese;
    $saldo   = $c['saldo'];
@endphp

    @if (!empty($nombreAnio))
        <p class="cab__nombre-anio">"{{ $nombreAnio }}"</p>
    @endif

    <table class="cab">
        <tr>
            <td style="width: 60%;">
                <table>
                    <tr>
                        <td class="cab__logo" style="width: 16%;"><img src="{{ public_path('pdf/logo.png') }}"></td>
                        <td class="cab__inst" style="width: 84%;">ASOCIACIÓN EDUCATIVA<br>COLEGIO ADVENTISTA<br>TÚPAC AMARU</td>
                    </tr>
                </table>
            </td>
            <td class="cab__lugar" style="width: 40%;">
                <strong>JULIACA</strong><br>{{ $colegioDireccion }}<br>RUC {{ $colegioRuc }}
            </td>
        </tr>
    </table>

    <div class="titulo">
        <h1>CERTIFICADO DE RENTAS Y RETENCIONES POR RENTAS DE QUINTA CATEGORÍA</h1>
        <p>Art. 45° del Reglamento de la Ley del Impuesto a la Renta, D.S. N° 122-94-EF</p>
        <span class="periodo">EJERCICIO GRAVABLE {{ $c['anio'] }}{{ $c['al_cese'] ? ' · AL CESE' : '' }}</span>
    </div>

    <p class="constancia">
        La <strong>{{ $colegioNombre }}</strong>, con RUC N° {{ $colegioRuc }}, domiciliada en {{ $colegioDireccion }},
        certifica que a <strong>{{ $e->apellido }}, {{ $e->nombre }}</strong>, identificado(a) con DNI N° {{ $e->dni }},
        se le pagaron rentas de quinta categoría del {{ $fecha($c['desde']) }} al {{ $fecha($c['hasta']) }}
        y se le retuvo el Impuesto a la Renta según el siguiente detalle.
    </p>

    <div class="barra">Datos del trabajador</div>
    <table class="datos">
        <tr>
            <td style="width: 46%;"><div class="rotulo">Apellidos y nombres</div><div class="valor valor--grande">{{ $e->apellido }}, {{ $e->nombre }}</div></td>
            <td style="width: 18%;"><div class="rotulo">DNI</div><div class="valor valor--grande">{{ $e->dni }}</div></td>
            <td style="width: 18%;"><div class="rotulo">Fecha de ingreso</div><div class="valor">{{ $fecha($e->fecha_ingreso) }}</div></td>
            <td style="width: 18%;"><div class="rotulo">Fecha de cese</div><div class="valor">{{ $cesado ? $fecha($e->fecha_cese) : '---' }}</div></td>
        </tr>
    </table>

    <table class="dos-columnas" style="margin-top: 0;">
        <tr>
            {{-- ── I. Rentas y II. Impuesto ── --}}
            <td class="col" style="width: 55%; padding-right: 6px;">
                <div class="barra">I. Rentas brutas de quinta categoría</div>
                <table class="cuadro">
                    <tr><th style="width: 68%;">Concepto</th><th class="num" style="width: 32%;">Importe S/</th></tr>
                    <tr><td>Remuneraciones <span class="detalle">(básica, asignación familiar, cargo)</span></td><td class="num">{{ $monto($c['remuneraciones']) }}</td></tr>
                    <tr><td>Gratificaciones <span class="detalle">(con su bonificación de la Ley 30334)</span></td><td class="num">{{ $monto($c['gratificaciones']) }}</td></tr>
                    <tr><td>Otros ingresos afectos <span class="detalle">(bonificaciones, vacaciones…)</span></td><td class="num">{{ $monto($c['otros']) }}</td></tr>
                    <tr class="total"><td>Total renta bruta</td><td class="num">{{ number_format($c['renta_bruta'], 2) }}</td></tr>
                </table>

                <div class="barra">II. Determinación del impuesto</div>
                <table class="cuadro">
                    <tr><th style="width: 68%;">Concepto</th><th class="num" style="width: 32%;">Importe S/</th></tr>
                    <tr><td>Renta bruta</td><td class="num">{{ number_format($c['renta_bruta'], 2) }}</td></tr>
                    <tr class="resta"><td>(-) Deducción de 7 UIT <span class="detalle">(7 × {{ $uit }})</span></td><td class="num">{{ number_format($c['deduccion'], 2) }}</td></tr>
                    <tr class="total"><td>Renta neta imponible</td><td class="num">{{ number_format($c['renta_neta'], 2) }}</td></tr>
                    @forelse ($c['tramos'] as $t)
                        <tr><td>{{ $t['tramo'] }} <span class="detalle">({{ $t['tasa'] }}% de {{ number_format($t['base'], 2) }})</span></td><td class="num">{{ number_format($t['impuesto'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="detalle">La renta no pasa de 7 UIT: no hay impuesto.</td></tr>
                    @endforelse
                    <tr class="total"><td>Impuesto a la renta del ejercicio</td><td class="num">{{ number_format($c['impuesto'], 2) }}</td></tr>
                </table>
            </td>

            {{-- ── III. Retenciones mes a mes ── --}}
            <td class="col" style="width: 45%; padding-left: 6px;">
                <div class="barra">III. Retenciones efectuadas</div>
                <table class="cuadro">
                    <tr><th style="width: 34%;">Mes</th><th class="num" style="width: 36%;">Renta del mes</th><th class="num" style="width: 30%;">Retención</th></tr>
                    @foreach ($c['meses'] as $m)
                        <tr><td>{{ $meses[$m['mes']] }}</td><td class="num">{{ number_format($m['total'], 2) }}</td><td class="num">{{ $monto($m['retencion']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total</td><td class="num">{{ number_format($c['renta_bruta'], 2) }}</td><td class="num">{{ number_format($c['retenido'], 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="neto">
        <tr>
            <td class="rotulo-neto">IMPUESTO RETENIDO EN EL EJERCICIO</td>
            <td class="monto">S/ {{ number_format($c['retenido'], 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" class="saldo">
                <strong>SON:</strong> {{ \App\Support\Pdf\MontoEnLetras::soles($c['retenido']) }}<br>
                @if ($saldo > 0.005)
                    <strong>Saldo por regularizar: S/ {{ number_format($saldo, 2) }}.</strong> El impuesto del ejercicio ({{ number_format($c['impuesto'], 2) }}) es mayor que lo retenido.
                @elseif ($saldo < -0.005)
                    <strong>Retenido en exceso: S/ {{ number_format(-$saldo, 2) }}.</strong> Lo retenido es mayor que el impuesto del ejercicio ({{ number_format($c['impuesto'], 2) }}); el exceso se devuelve al trabajador.
                @else
                    <strong>Sin saldo:</strong> lo retenido cubre exactamente el impuesto del ejercicio.
                @endif
            </td>
        </tr>
    </table>

    <p class="nota">
        Comprende solo las rentas pagadas por este empleador. Si en el ejercicio percibió rentas de quinta categoría de otros
        empleadores, cada uno le entrega su propio certificado. Se expide en cumplimiento del artículo 45° del Reglamento de la Ley
        del Impuesto a la Renta{{ $c['al_cese'] ? ', con ocasión del cese del trabajador' : '' }}.
    </p>

    <p class="lugar-fecha">Juliaca, {{ $enLetra($fechaEmision ?? now()) }}</p>

    @include('partials.firma-digital', [
        'trabajador' => trim("{$e->nombre} {$e->apellido}"),
        'dni'        => $e->dni,
    ])
</body>
</html>
