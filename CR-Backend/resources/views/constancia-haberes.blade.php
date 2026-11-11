<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La constancia de haberes: lo que el trabajador cobró en sus últimos
        meses pagados, de sus planillas (ingresos, descuentos y neto), con el
        promedio. Es la que piden los bancos y las cooperativas. Con la
        cabecera y los colores de las demás hojas, y la firma digital del
        colegio (partials/firma-digital, solo el empleador).

        Lo dibuja dompdf: nada de flex ni de variables CSS.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 16mm 20mm 14mm 20mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1F2B33; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: middle; }
        .cab__logo img { width: 52px; height: 52px; }
        .cab__inst { font-size: 7.5px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 8px; line-height: 1.4; color: #4A5961; }
        .cab__lugar strong { color: #1F2B33; font-size: 9px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }
        .titulo { margin-top: 22px; text-align: center; }
        .titulo h1 { font-size: 16px; letter-spacing: 2px; }
        .titulo .linea { display: inline-block; margin-top: 6px; width: 70px; height: 3px; background: #EC7A3A; }
        .dirigido { margin-top: 20px; font-size: 10px; font-weight: bold; }
        .cuerpo { margin-top: 14px; font-size: 10.5px; line-height: 1.7; text-align: justify; }
        .cuadro { margin-top: 12px; border: 1px solid #9FB6BF; }
        .cuadro th { background: #EDF3F5; font-size: 7.5px; text-transform: uppercase; text-align: left; padding: 6px 10px; border-bottom: 1px solid #9FB6BF; }
        .cuadro td { padding: 5px 10px; border-bottom: 1px solid #DCE7EB; font-size: 9.5px; }
        .cuadro .num { text-align: right; white-space: nowrap; }
        .cuadro tr.total td { background: #D3E3E8; font-weight: bold; border-bottom: none; }
        .nota { margin-top: 10px; font-size: 8px; color: #6B7A82; line-height: 1.5; }
        .lugar-fecha { margin-top: 20px; text-align: right; font-size: 10px; }
    </style>
</head>
<body>
@php
    $colegioNombre    = 'Asociación Educativa Colegio Adventista Túpac Amaru';
    $colegioRuc       = config('institucion.ruc');
    $colegioDireccion = 'Jr. Moquegua N° 852, Juliaca';
    $nombres = \App\Support\Meses::NOMBRES;
    $enLetra = function ($f) use ($nombres) {
        $x = \Carbon\Carbon::parse($f);
        return $x->day . ' de ' . mb_strtolower($nombres[$x->month]) . ' de ' . $x->year;
    };
    $cesado = $e->estado === 'inactivo' && $e->fecha_cese;
    $suma = fn (string $c) => round(array_sum(array_column($meses, $c)), 2);
    $n = max(1, count($meses));
    $cargo = $e->cargo->nombre ?? null;
    $primero = $meses ? $nombres[$meses[0]['mes']] . ' ' . $meses[0]['anio'] : '';
    $ultimo = $meses ? $nombres[end($meses)['mes']] . ' ' . end($meses)['anio'] : '';
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
        <h1>CONSTANCIA DE HABERES</h1>
        <span class="linea"></span>
    </div>

    <p class="dirigido">{{ $dirigidoA ? 'Señores: ' . $dirigidoA : 'A QUIEN CORRESPONDA:' }}</p>

    <p class="cuerpo">
        La <strong>{{ $colegioNombre }}</strong>, con RUC N° {{ $colegioRuc }}, hace constar que
        <strong>{{ $e->apellido }}, {{ $e->nombre }}</strong>, identificado(a) con DNI N° {{ $e->dni }},
        {{ $cesado ? 'laboró' : 'labora' }} en nuestra institución desde el {{ $enLetra($e->fecha_ingreso) }}{{ $cesado ? ' hasta el ' . $enLetra($e->fecha_cese) : '' }}{{ $cargo ? ', en el cargo de ' . $cargo : '' }},
        y que en {{ count($meses) === 1 ? 'el último mes pagado' : 'sus últimos ' . count($meses) . ' meses pagados' }}
        ({{ $primero === $ultimo ? $primero : 'de ' . mb_strtolower($primero) . ' a ' . mb_strtolower($ultimo) }}) percibió lo siguiente:
    </p>

    <table class="cuadro">
        <tr>
            <th style="width: 31%;">Mes</th>
            <th class="num" style="width: 23%;">Total ingresos S/</th>
            <th class="num" style="width: 23%;">Descuentos S/</th>
            <th class="num" style="width: 23%;">Neto pagado S/</th>
        </tr>
        @foreach ($meses as $m)
            <tr>
                <td>{{ $nombres[$m['mes']] }} {{ $m['anio'] }}</td>
                <td class="num">{{ number_format($m['ingresos'], 2) }}</td>
                <td class="num">{{ number_format($m['descuentos'], 2) }}</td>
                <td class="num">{{ number_format($m['neto'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Promedio mensual</td>
            <td class="num">{{ number_format($suma('ingresos') / $n, 2) }}</td>
            <td class="num">{{ number_format($suma('descuentos') / $n, 2) }}</td>
            <td class="num">{{ number_format($suma('neto') / $n, 2) }}</td>
        </tr>
    </table>

    <p class="nota">
        Montos tomados de sus planillas de remuneraciones. Los ingresos comprenden la remuneración y los demás conceptos
        pagados en el mes; los descuentos, los de ley (pensiones, renta de quinta categoría) y los autorizados por el trabajador.
        Se expide la presente constancia a solicitud del interesado, para los fines que estime conveniente.
    </p>

    <p class="lugar-fecha">Juliaca, {{ $enLetra($fechaEmision) }}</p>

    @include('partials.firma-digital', [
        'trabajador'    => trim("{$e->nombre} {$e->apellido}"),
        'dni'           => $e->dni,
        'soloEmpleador' => true,
    ])
</body>
</html>
