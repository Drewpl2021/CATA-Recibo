<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La constancia de trabajo que el trabajador pide para un préstamo o un
        trámite. A quien ya cesó le sale el CERTIFICADO DE TRABAJO (D.S. N°
        001-96-TR, art. 2): el tiempo de servicios y la labor que hizo. Con la
        cabecera y los colores de las demás hojas, y la firma digital del
        colegio (partials/firma-digital, solo el empleador).

        Lo dibuja dompdf: nada de flex ni de variables CSS.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 16mm 20mm 14mm 20mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1F2B33; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { vertical-align: middle; }
        .cab__logo img { width: 52px; height: 52px; }
        .cab__inst { font-size: 7.5px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 8px; line-height: 1.4; color: #4A5961; }
        .cab__lugar strong { color: #1F2B33; font-size: 9px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }
        .titulo { margin-top: 26px; text-align: center; }
        .titulo h1 { font-size: 16px; letter-spacing: 2px; }
        .titulo .linea { display: inline-block; margin-top: 6px; width: 70px; height: 3px; background: #EC7A3A; }
        .dirigido { margin-top: 26px; font-size: 10px; font-weight: bold; }
        .cuerpo { margin-top: 18px; font-size: 11px; line-height: 1.8; text-align: justify; }
        .cuerpo p { margin-bottom: 12px; }
        .datos { margin-top: 6px; border: 1px solid #9FB6BF; }
        .datos td { padding: 6px 10px; border-bottom: 1px solid #DCE7EB; font-size: 10px; }
        .datos td.rotulo { width: 34%; background: #EDF3F5; font-size: 8px; font-weight: bold; text-transform: uppercase; color: #4A5961; }
        .lugar-fecha { margin-top: 26px; text-align: right; font-size: 10px; }
    </style>
</head>
<body>
@php
    $colegioNombre    = 'Asociación Educativa Colegio Adventista Túpac Amaru';
    $colegioRuc       = config('institucion.ruc');
    $colegioDireccion = 'Jr. Moquegua N° 852, Juliaca';
    $meses   = \App\Support\Meses::NOMBRES;
    $enLetra = function ($f) use ($meses) {
        $x = \Carbon\Carbon::parse($f);
        return $x->day . ' de ' . mb_strtolower($meses[$x->month]) . ' de ' . $x->year;
    };
    $cesado  = $e->estado === 'inactivo' && $e->fecha_cese;
    $ingreso = \Carbon\Carbon::parse($e->fecha_ingreso);
    $fin     = $cesado ? \Carbon\Carbon::parse($e->fecha_cese) : \Carbon\Carbon::parse($fechaEmision);
    // El tiempo de servicios en años, meses y días.
    $tiempo  = $ingreso->copy()->diff($fin->copy()->addDay());
    $partes  = array_filter([
        $tiempo->y ? $tiempo->y . ' ' . ($tiempo->y === 1 ? 'año' : 'años') : null,
        $tiempo->m ? $tiempo->m . ' ' . ($tiempo->m === 1 ? 'mes' : 'meses') : null,
        $tiempo->d ? $tiempo->d . ' ' . ($tiempo->d === 1 ? 'día' : 'días') : null,
    ]);
    $tiempoTexto = $partes ? implode(', ', $partes) : '1 día';
    $cargo = $e->cargo->nombre ?? null;
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
        <h1>{{ $cesado ? 'CERTIFICADO DE TRABAJO' : 'CONSTANCIA DE TRABAJO' }}</h1>
        <span class="linea"></span>
    </div>

    <p class="dirigido">{{ $dirigidoA ? 'Señores: ' . $dirigidoA : 'A QUIEN CORRESPONDA:' }}</p>

    <div class="cuerpo">
        @if ($cesado)
            <p>
                La <strong>{{ $colegioNombre }}</strong>, con RUC N° {{ $colegioRuc }}, certifica que
                <strong>{{ $e->apellido }}, {{ $e->nombre }}</strong>, identificado(a) con DNI N° {{ $e->dni }},
                laboró en nuestra institución desde el {{ $enLetra($e->fecha_ingreso) }} hasta el {{ $enLetra($e->fecha_cese) }},
                con un tiempo de servicios de {{ $tiempoTexto }}{{ $cargo ? ', desempeñando el cargo de ' . $cargo : '' }}.
            </p>
            <p>Se expide el presente certificado conforme al artículo 2° del D.S. N° 001-96-TR, a solicitud del interesado.</p>
        @else
            <p>
                La <strong>{{ $colegioNombre }}</strong>, con RUC N° {{ $colegioRuc }}, hace constar que
                <strong>{{ $e->apellido }}, {{ $e->nombre }}</strong>, identificado(a) con DNI N° {{ $e->dni }},
                labora en nuestra institución desde el {{ $enLetra($e->fecha_ingreso) }} hasta la fecha{{ $cargo ? ', desempeñando el cargo de ' . $cargo : '' }}.
            </p>
            <p>Se expide la presente constancia a solicitud del interesado, para los fines que estime conveniente.</p>
        @endif
    </div>

    <table class="datos">
        <tr><td class="rotulo">Trabajador</td><td>{{ $e->apellido }}, {{ $e->nombre }} · DNI {{ $e->dni }}</td></tr>
        <tr><td class="rotulo">Cargo</td><td>{{ $cargo ?? '---' }}</td></tr>
        @if ($e->area?->nombre || $e->sede?->nombre)
            <tr><td class="rotulo">Área y sede</td><td>{{ implode(' · ', array_filter([$e->area?->nombre, $e->sede?->nombre])) }}</td></tr>
        @endif
        @if ($tipoContrato)
            <tr><td class="rotulo">Tipo de contrato</td><td>{{ $tipoContrato }}</td></tr>
        @endif
        <tr><td class="rotulo">Fecha de ingreso</td><td>{{ $ingreso->format('d/m/Y') }}</td></tr>
        @if ($cesado)
            <tr><td class="rotulo">Fecha de cese</td><td>{{ $fin->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td class="rotulo">Tiempo de servicios</td><td>{{ $tiempoTexto }}{{ $cesado ? '' : ' (a la fecha)' }}</td></tr>
    </table>

    <p class="lugar-fecha">Juliaca, {{ $enLetra($fechaEmision) }}</p>

    @include('partials.firma-digital', [
        'trabajador'    => trim("{$e->nombre} {$e->apellido}"),
        'dni'           => $e->dni,
        'soloEmpleador' => true,
    ])
</body>
</html>
