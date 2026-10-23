<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La hoja de la gratificación de julio o diciembre (Ley 27735 y su
        reglamento, D.S. 005-2002-TR; bonificación de la Ley 30334): datos del
        empleador y del trabajador, la remuneración computable, el tiempo y el
        cálculo en cuadros, el total en letras y las firmas. Con la cabecera y
        los colores de la hoja de CTS y de la boleta.

        Lo dibuja dompdf: nada de flex ni de variables CSS.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 14mm 16mm 12mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.8px; color: #1F2B33; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: middle; }
        .cab__logo img { width: 52px; height: 52px; }
        .cab__inst { font-size: 7.5px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 8px; line-height: 1.4; color: #4A5961; }
        .cab__lugar strong { color: #1F2B33; font-size: 9px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }
        .titulo { margin-top: 10px; text-align: center; }
        .titulo h1 { font-size: 13px; letter-spacing: .3px; }
        .titulo p { font-size: 7.5px; color: #4A5961; margin-top: 2px; }
        .titulo .periodo { display: inline-block; margin-top: 6px; padding: 3px 12px; background: #EC7A3A; color: #FFFFFF; font-size: 9.5px; font-weight: bold; }
        .barra { background: #D3E3E8; font-weight: bold; font-size: 7.8px; text-transform: uppercase; padding: 4px 6px; margin-top: 10px; }
        .datos td { padding: 4px 6px; border-bottom: 1px solid #DCE7EB; vertical-align: top; }
        .rotulo { font-size: 6.6px; font-weight: bold; text-transform: uppercase; color: #4A5961; }
        .valor { font-size: 8.8px; color: #1F2B33; margin-top: 1px; }
        .valor--grande { font-size: 10.5px; font-weight: bold; }
        .cuadro { margin-top: 4px; border: 1px solid #9FB6BF; }
        .cuadro th { background: #EDF3F5; font-size: 7px; text-transform: uppercase; text-align: left; padding: 5px 8px; border-bottom: 1px solid #9FB6BF; }
        .cuadro td { padding: 5px 8px; border-bottom: 1px solid #DCE7EB; font-size: 8.8px; }
        .cuadro .num { text-align: right; white-space: nowrap; }
        .cuadro .detalle { color: #6B7A82; font-size: 7.6px; }
        .cuadro tr.total td { background: #D3E3E8; font-weight: bold; border-bottom: none; }
        .tiempo td { text-align: center; padding: 6px 8px; border-right: 1px solid #DCE7EB; }
        .tiempo td:last-child { border-right: none; }
        .tiempo strong { display: block; font-size: 14px; }
        .tiempo span { font-size: 7px; text-transform: uppercase; color: #4A5961; }
        .neto { margin-top: 10px; border: 1.5px solid #1F2B33; }
        .neto td { padding: 8px 10px; }
        .neto .rotulo-neto { font-size: 10px; font-weight: bold; }
        .neto .monto { text-align: right; font-size: 14px; font-weight: bold; }
        .son { padding: 5px 10px; background: #EDF3F5; font-size: 8.2px; border-top: 1px solid #9FB6BF; }
        .nota { font-size: 7px; color: #6B7A82; margin-top: 10px; line-height: 1.45; }
        .lugar-fecha { margin-top: 14px; text-align: right; font-size: 8.8px; }
        .firmas { margin-top: 56px; }
        .firmas td { text-align: center; font-size: 7.8px; padding: 0 24px; vertical-align: top; }
        .firmas .raya { border-top: 1px solid #1F2B33; padding-top: 4px; line-height: 1.4; }
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

    $nombreGrati = $periodo === 'julio' ? 'FIESTAS PATRIAS' : 'NAVIDAD';
    $mesPago     = mb_strtolower($meses[$c['mes_planilla']]) . ' ' . $c['anio_planilla'];
    $rc          = number_format($c['remuneracion_computable'], 2);
    $cesado      = $e->estado === 'inactivo' && $e->fecha_cese;
    $tasa        = $c['gratificacion'] > 0 ? round($c['bonificacion_extraordinaria'] / $c['gratificacion'] * 100, 2) : 0;
@endphp

    @if (!empty($nombreAnio))
        <p class="cab__nombre-anio">"{{ $nombreAnio }}"</p>
    @endif

    <table class="cab">
        <tr>
            <td style="width: 60%;">
                <table>
                    <tr>
                        <td class="cab__logo" style="width: 16%;"><img src="{{ public_path('logo.png') }}"></td>
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
        <h1>{{ $c['trunca'] ? 'LIQUIDACIÓN DE GRATIFICACIÓN TRUNCA' : 'HOJA DE GRATIFICACIÓN POR ' . $nombreGrati }}</h1>
        <p>Ley N° 27735 y su reglamento, D.S. N° 005-2002-TR · Bonificación extraordinaria, Ley N° 30334</p>
        <span class="periodo">GRATIFICACIÓN DE {{ mb_strtoupper($periodo) }} {{ $anio }}</span>
    </div>

    <div class="barra">Datos del empleador</div>
    <table class="datos">
        <tr>
            <td style="width: 50%;"><div class="rotulo">Razón social</div><div class="valor">{{ $colegioNombre }}</div></td>
            <td style="width: 20%;"><div class="rotulo">RUC</div><div class="valor">{{ $colegioRuc }}</div></td>
            <td style="width: 30%;"><div class="rotulo">Domicilio</div><div class="valor">{{ $colegioDireccion }}</div></td>
        </tr>
    </table>

    <div class="barra">Datos del trabajador</div>
    <table class="datos">
        <tr>
            <td style="width: 50%;"><div class="rotulo">Apellidos y nombres</div><div class="valor valor--grande">{{ $e->apellido }}, {{ $e->nombre }}</div></td>
            <td style="width: 20%;"><div class="rotulo">DNI</div><div class="valor valor--grande">{{ $e->dni }}</div></td>
            <td style="width: 30%;"><div class="rotulo">Cargo</div><div class="valor">{{ $e->cargo->nombre ?? '---' }}</div></td>
        </tr>
        <tr>
            <td><div class="rotulo">Fecha de ingreso</div><div class="valor">{{ $fecha($e->fecha_ingreso) }}</div></td>
            <td><div class="rotulo">Fecha de cese</div><div class="valor">{{ $cesado ? $fecha($e->fecha_cese) : '---' }}</div></td>
            <td><div class="rotulo">Semestre</div><div class="valor">Del {{ $fecha($semestre['desde']) }} al {{ $fecha($semestre['hasta']) }}</div></td>
        </tr>
    </table>

    <div class="barra">I. Remuneración computable</div>
    <table class="cuadro">
        <tr><th style="width: 75%;">Concepto</th><th class="num" style="width: 25%;">Importe S/</th></tr>
        <tr><td>Remuneración básica <span class="detalle">(vigente en {{ $mesPago }})</span></td><td class="num">{{ $monto($c['sueldo']) }}</td></tr>
        <tr><td>Asignación familiar</td><td class="num">{{ $monto($c['asignacion_familiar']) }}</td></tr>
        @if ($c['bonificacion_cargo'] > 0)
            <tr><td>Bonificación por cargo</td><td class="num">{{ $monto($c['bonificacion_cargo']) }}</td></tr>
        @endif
        <tr class="total"><td>Total remuneración computable</td><td class="num">{{ $rc }}</td></tr>
    </table>

    <div class="barra">II. Tiempo computable</div>
    <table class="cuadro tiempo">
        <tr>
            <td style="width: 33%;"><strong>{{ $c['meses'] }}</strong><span>{{ $c['meses'] === 1 ? 'Mes completo' : 'Meses completos' }}</span></td>
            <td style="width: 33%;"><strong>6</strong><span>Meses del semestre</span></td>
            <td style="width: 34%;"><strong style="font-size: 10px;">{{ $fecha($semestre['desde']) }} – {{ $fecha($cesado && $c['trunca'] ? $e->fecha_cese : $semestre['hasta']) }}</strong><span>Periodo computado</span></td>
        </tr>
    </table>

    <div class="barra">III. Cálculo</div>
    <table class="cuadro">
        <tr><th style="width: 40%;">Concepto</th><th style="width: 35%;">Cálculo</th><th class="num" style="width: 25%;">Importe S/</th></tr>
        <tr>
            <td>{{ $c['trunca'] ? 'Gratificación trunca' : 'Gratificación' }}</td>
            <td class="detalle">{{ $rc }} ÷ 6 × {{ $c['meses'] }}</td>
            <td class="num">{{ number_format($c['gratificacion'], 2) }}</td>
        </tr>
        <tr>
            <td>Bonificación extraordinaria (Ley 30334)</td>
            <td class="detalle">{{ number_format($c['gratificacion'], 2) }} × {{ rtrim(rtrim(number_format($tasa, 2), '0'), '.') }}%</td>
            <td class="num">{{ number_format($c['bonificacion_extraordinaria'], 2) }}</td>
        </tr>
    </table>

    <table class="neto">
        <tr>
            <td class="rotulo-neto">TOTAL A PAGAR</td>
            <td class="monto">S/ {{ number_format($c['total'], 2) }}</td>
        </tr>
        <tr><td colspan="2" class="son"><strong>SON:</strong> {{ \App\Support\MontoEnLetras::soles($c['total']) }}</td></tr>
    </table>

    <p class="nota">
        Se paga con la planilla de {{ $mesPago }} y figura en la boleta de ese mes.
        La gratificación y la bonificación extraordinaria no están afectas a aportes de pensiones ni a EsSalud
        (Ley 30334); sí forman parte de la renta de 5ta categoría.
        Cualquier observación se presenta a la Oficina de Recursos Humanos.
    </p>

    <p class="lugar-fecha">Juliaca, {{ $enLetra(now()) }}</p>

    <table class="firmas">
        <tr>
            <td><div class="raya">EL EMPLEADOR<br>{{ $colegioNombre }}</div></td>
            <td><div class="raya">EL TRABAJADOR<br>{{ $e->nombre }} {{ $e->apellido }} · DNI {{ $e->dni }}</div></td>
        </tr>
    </table>
</body>
</html>
