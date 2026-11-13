<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La relación de pagos (sueldos de un mes o depósito de CTS): por banco o
        caja, con subtotales, el total en letras, lo que no va por abono y lo
        que hay que revisar antes de pagar; al pie, las firmas de quien la
        prepara, quien la revisa y quien autoriza. Ver App\Support\Pagos\RelacionDePagos.

        Lo dibuja dompdf: nada de flex ni de variables CSS.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 12mm 12mm 14mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5px; color: #1F2B33; }
        table { width: 100%; border-collapse: collapse; }
        .cab__logo img { width: 42px; height: 42px; }
        .cab__inst { font-size: 7px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 7.5px; line-height: 1.4; color: #4A5961; }
        .titulo { margin-top: 10px; text-align: center; }
        .titulo h1 { font-size: 13px; letter-spacing: 1px; }
        .titulo p { margin-top: 3px; font-size: 9px; color: #4A5961; }
        .aviso { margin-top: 8px; padding: 6px 8px; background: #FFF4E5; border: 1px solid #F0C27B; font-size: 8px; }
        h2 { margin-top: 12px; font-size: 9.5px; color: #1B4282; }
        .cuadro { margin-top: 5px; border: 1px solid #9FB6BF; }
        .cuadro th { background: #EDF3F5; font-size: 7px; text-transform: uppercase; text-align: left; padding: 4px 6px; border-bottom: 1px solid #9FB6BF; }
        .cuadro td { padding: 3px 6px; border-bottom: 1px solid #E3ECEF; }
        .num, .cuadro th.num { text-align: right; white-space: nowrap; }
        .cuadro tr.sub td { background: #EDF3F5; font-weight: bold; }
        .cuadro tr.total td { background: #D3E3E8; font-weight: bold; font-size: 9px; }
        .letras { margin-top: 6px; font-size: 8.5px; }
        .lista { margin-top: 4px; padding-left: 14px; font-size: 8px; line-height: 1.5; }
        .firmas { margin-top: 42px; }
        .firmas td { width: 33%; text-align: center; padding: 0 10px; font-size: 8px; }
        .firmas .raya { border-top: 1px solid #1F2B33; padding-top: 4px; }
        .pie { margin-top: 14px; font-size: 7px; color: #6B7A82; }
    </style>
</head>
<body>
@php
    $soles = fn ($m) => number_format((float) $m, 2, '.', ',');
@endphp

    <table>
        <tr>
            <td style="width: 60%;">
                <table>
                    <tr>
                        <td class="cab__logo" style="width: 12%;"><img src="{{ public_path('pdf/logo.png') }}"></td>
                        <td class="cab__inst">ASOCIACIÓN EDUCATIVA<br>COLEGIO ADVENTISTA<br>TÚPAC AMARU</td>
                    </tr>
                </table>
            </td>
            <td class="cab__lugar" style="width: 40%;"><strong>JULIACA</strong><br>RUC {{ config('institucion.ruc') }}</td>
        </tr>
    </table>

    <div class="titulo">
        <h1>{{ $titulo }}</h1>
        <p>{{ $subtitulo }}</p>
    </div>

    @if (!empty($borrador))
        <p class="aviso"><strong>Borrador.</strong> {{ $borrador }}</p>
    @endif

    <h2>Resumen por entidad</h2>
    <table class="cuadro">
        <tr><th>Banco o caja</th><th class="num" style="width: 18%;">Personas</th><th class="num" style="width: 22%;">Monto S/</th></tr>
        @foreach ($r['grupos'] as $entidad => $g)
            <tr><td>{{ $entidad }}</td><td class="num">{{ count($g['filas']) }}</td><td class="num">{{ $soles($g['total']) }}</td></tr>
        @endforeach
        <tr class="total"><td>TOTAL A ABONAR</td><td class="num">{{ array_sum(array_map(fn ($g) => count($g['filas']), $r['grupos'])) }}</td><td class="num">{{ $soles($r['total_abonar']) }}</td></tr>
    </table>
    <p class="letras">Son: <strong>{{ \App\Support\Pdf\MontoEnLetras::soles($r['total_abonar']) }}</strong></p>

    <h2>Detalle de los abonos</h2>
    <table class="cuadro">
        <tr>
            <th style="width: 5%;">N°</th><th style="width: 11%;">DNI</th><th>Apellidos y nombres</th>
            <th style="width: 20%;">N° de cuenta</th>
            @if ($conCci)<th style="width: 19%;">CCI</th>@endif
            <th class="num" style="width: 12%;">Monto S/</th>
        </tr>
        @php $n = 0; @endphp
        @foreach ($r['grupos'] as $entidad => $g)
            @foreach ($g['filas'] as $f)
                <tr>
                    <td>{{ ++$n }}</td><td>{{ $f['dni'] }}</td><td>{{ $f['nombre'] }}</td><td>{{ $f['cuenta'] }}</td>
                    @if ($conCci)<td>{{ $f['cci'] }}</td>@endif
                    <td class="num">{{ $soles($f['monto']) }}</td>
                </tr>
            @endforeach
            <tr class="sub">
                <td colspan="{{ $conCci ? 5 : 4 }}">Subtotal {{ $entidad }} ({{ count($g['filas']) }})</td>
                <td class="num">{{ $soles($g['total']) }}</td>
            </tr>
        @endforeach
    </table>

    @if ($r['sin_abono'])
        <h2>No van por abono ({{ count($r['sin_abono']) }})</h2>
        <table class="cuadro">
            <tr><th style="width: 11%;">DNI</th><th style="width: 32%;">Apellidos y nombres</th><th class="num" style="width: 12%;">Monto S/</th><th>Por qué</th></tr>
            @foreach ($r['sin_abono'] as $f)
                <tr><td>{{ $f['dni'] }}</td><td>{{ $f['nombre'] }}</td><td class="num">{{ $soles($f['monto']) }}</td><td>{{ $f['motivo'] }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($r['alertas'])
        <h2>Revisar antes de pagar</h2>
        <ul class="lista">
            @foreach ($r['alertas'] as $a)<li>{{ $a }}</li>@endforeach
        </ul>
    @endif

    <table class="firmas">
        <tr>
            <td><div class="raya">Elaborado por<br>Recursos Humanos</div></td>
            <td><div class="raya">Revisado por<br>Contabilidad</div></td>
            <td><div class="raya">Autorizado por<br>Administración</div></td>
        </tr>
    </table>

    <p class="pie">Generado en CATA-Recibo el {{ now()->format('d/m/Y H:i') }}{{ $quien ? ' por ' . $quien : '' }}.</p>
</body>
</html>
