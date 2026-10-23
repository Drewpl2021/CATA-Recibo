<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La hoja de liquidación de la CTS que el colegio entrega con cada
        depósito (art. 29 del TUO de la Ley de CTS, D.S. 001-97-TR): datos del
        empleador, del trabajador y del depósito; la remuneración computable,
        el tiempo y el cálculo en cuadros; el monto en letras y las firmas.
        Con la cabecera y los colores de la boleta, para que se lean igual.

        Lo dibuja dompdf: nada de flex ni de variables CSS, todo con tablas y
        colores literales.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { margin: 14mm 16mm 12mm 16mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.8px; color: #1F2B33; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: middle; }

        /* ── Cabecera ── */
        .cab td { vertical-align: middle; }
        .cab__logo img { width: 52px; height: 52px; }
        .cab__inst { font-size: 7.5px; font-weight: bold; line-height: 1.3; padding-left: 6px; }
        .cab__lugar { text-align: right; font-size: 8px; line-height: 1.4; color: #4A5961; }
        .cab__lugar strong { color: #1F2B33; font-size: 9px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }

        .titulo { margin-top: 10px; text-align: center; }
        .titulo h1 { font-size: 13px; letter-spacing: .3px; }
        .titulo p { font-size: 7.5px; color: #4A5961; margin-top: 2px; }
        .titulo .periodo { display: inline-block; margin-top: 6px; padding: 3px 12px; background: #EC7A3A; color: #FFFFFF; font-size: 9.5px; font-weight: bold; }

        /* ── Bloques de datos ── */
        .barra { background: #D3E3E8; font-weight: bold; font-size: 7.8px; text-transform: uppercase; padding: 4px 6px; margin-top: 10px; }
        .datos td { padding: 4px 6px; border-bottom: 1px solid #DCE7EB; vertical-align: top; }
        .rotulo { font-size: 6.6px; font-weight: bold; text-transform: uppercase; color: #4A5961; }
        .valor { font-size: 8.8px; color: #1F2B33; margin-top: 1px; }
        .valor--grande { font-size: 10.5px; font-weight: bold; }

        /* ── Cuadros con borde, como en una liquidación ── */
        .cuadro { margin-top: 4px; border: 1px solid #9FB6BF; }
        .cuadro th { background: #EDF3F5; font-size: 7px; text-transform: uppercase; text-align: left; padding: 5px 8px; border-bottom: 1px solid #9FB6BF; }
        .cuadro td { padding: 5px 8px; border-bottom: 1px solid #DCE7EB; font-size: 8.8px; }
        .cuadro .num { text-align: right; white-space: nowrap; }
        .cuadro .centro { text-align: center; }
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
    // Datos institucionales fijos, los mismos de la boleta.
    $colegioNombre    = 'Asociación Educativa Colegio Adventista Túpac Amaru';
    $colegioRuc       = config('institucion.ruc');
    $colegioDireccion = 'Jr. Moquegua N° 852, Juliaca';

    $meses   = \App\Support\Meses::NOMBRES;
    $fecha   = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '---';
    $enLetra = function ($f) use ($meses) {
        $c = \Carbon\Carbon::parse($f);
        return $c->day . ' de ' . mb_strtolower($meses[$c->month]) . ' de ' . $c->year;
    };
    $monto   = fn (float $v) => abs($v) < 0.005 ? '-' : number_format($v, 2);

    $hasta       = \Carbon\Carbon::parse($l->hasta);
    $mesCierre   = $meses[$hasta->month] . ' ' . $hasta->year;
    $gratiMes    = $l->periodo === 'mayo' ? 'diciembre ' . ($l->anio - 1) : 'julio ' . $l->anio;
    $porMeses    = round($l->remuneracion_computable / 12 * $l->meses, 2);
    $porDias     = round($l->monto - $porMeses, 2);
    $rc          = number_format($l->remuneracion_computable, 2);
    $cesado      = $empleado->estado === 'inactivo' && $empleado->fecha_cese;
    $fechaHoja   = $l->fecha_deposito ?? now();
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
                <strong>JULIACA</strong><br>
                {{ $colegioDireccion }}<br>
                RUC {{ $colegioRuc }}
            </td>
        </tr>
    </table>

    <div class="titulo">
        <h1>HOJA DE LIQUIDACIÓN DE LA COMPENSACIÓN POR TIEMPO DE SERVICIOS</h1>
        <p>Art. 29° del TUO de la Ley de CTS, D.S. N° 001-97-TR</p>
        <span class="periodo">DEPÓSITO DE {{ mb_strtoupper($l->periodo) }} {{ $l->anio }}</span>
    </div>

    {{-- ── El empleador y el trabajador ── --}}
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
            <td style="width: 50%;"><div class="rotulo">Apellidos y nombres</div><div class="valor valor--grande">{{ $empleado->apellido }}, {{ $empleado->nombre }}</div></td>
            <td style="width: 20%;"><div class="rotulo">DNI</div><div class="valor valor--grande">{{ $empleado->dni }}</div></td>
            <td style="width: 30%;"><div class="rotulo">Cargo</div><div class="valor">{{ $empleado->cargo->nombre ?? '---' }}</div></td>
        </tr>
        <tr>
            <td><div class="rotulo">Fecha de ingreso</div><div class="valor">{{ $fecha($empleado->fecha_ingreso) }}</div></td>
            <td><div class="rotulo">Fecha de cese</div><div class="valor">{{ $cesado ? $fecha($empleado->fecha_cese) : '---' }}</div></td>
            <td><div class="rotulo">Periodo que se liquida</div><div class="valor">Del {{ $fecha($l->desde) }} al {{ $fecha($l->hasta) }}</div></td>
        </tr>
    </table>

    <div class="barra">Datos del depósito</div>
    <table class="datos">
        <tr>
            <td style="width: 30%;"><div class="rotulo">Entidad depositaria</div><div class="valor">{{ $l->entidad_financiera ?: ($empleado->entidad_financiera ?: '---') }}</div></td>
            <td style="width: 30%;"><div class="rotulo">N° de cuenta CTS</div><div class="valor">{{ $l->numero_cuenta ?: ($empleado->numero_cuenta ?: '---') }}</div></td>
            <td style="width: 15%;"><div class="rotulo">Moneda</div><div class="valor">Soles</div></td>
            <td style="width: 25%;"><div class="rotulo">Fecha de depósito</div><div class="valor">{{ $l->fecha_deposito ? $fecha($l->fecha_deposito) : 'Hasta el ' . $semestre['limite']->format('d/m/Y') }}</div></td>
        </tr>
    </table>

    {{-- ── I. Remuneración computable ── --}}
    <div class="barra">I. Remuneración computable</div>
    <table class="cuadro">
        <tr>
            <th style="width: 75%;">Concepto</th>
            <th class="num" style="width: 25%;">Importe S/</th>
        </tr>
        <tr><td>Remuneración básica <span class="detalle">({{ mb_strtolower($mesCierre) }})</span></td><td class="num">{{ $monto($l->sueldo) }}</td></tr>
        <tr><td>Asignación familiar</td><td class="num">{{ $monto($l->asignacion_familiar) }}</td></tr>
        @if ($l->bonificacion_cargo > 0)
            <tr><td>Bonificación por cargo</td><td class="num">{{ $monto($l->bonificacion_cargo) }}</td></tr>
        @endif
        <tr>
            <td>
                Sexta parte de la gratificación de {{ $gratiMes }}
                @if ($l->gratificacion > 0)
                    <span class="detalle">(S/ {{ number_format($l->gratificacion, 2) }} ÷ 6)</span>
                @endif
            </td>
            <td class="num">{{ $monto($l->sexto_gratificacion) }}</td>
        </tr>
        <tr class="total"><td>Total remuneración computable</td><td class="num">{{ number_format($l->remuneracion_computable, 2) }}</td></tr>
    </table>

    {{-- ── II. Tiempo de servicios ── --}}
    <div class="barra">II. Tiempo de servicios computable</div>
    <table class="cuadro tiempo">
        <tr>
            <td style="width: 33%;"><strong>{{ $l->meses }}</strong><span>{{ $l->meses === 1 ? 'Mes' : 'Meses' }}</span></td>
            <td style="width: 33%;"><strong>{{ $l->dias }}</strong><span>{{ $l->dias === 1 ? 'Día' : 'Días' }}</span></td>
            <td style="width: 34%;"><strong style="font-size: 10px;">{{ $fecha($l->desde) }} – {{ $fecha($l->hasta) }}</strong><span>Periodo computado</span></td>
        </tr>
    </table>

    {{-- ── III. Cálculo ── --}}
    <div class="barra">III. Cálculo de la CTS</div>
    <table class="cuadro">
        <tr>
            <th style="width: 40%;">Concepto</th>
            <th style="width: 35%;">Cálculo</th>
            <th class="num" style="width: 25%;">Importe S/</th>
        </tr>
        @if ($l->meses > 0)
            <tr>
                <td>Por {{ $l->meses }} {{ $l->meses === 1 ? 'mes completo' : 'meses completos' }}</td>
                <td class="detalle">{{ $rc }} ÷ 12 × {{ $l->meses }}</td>
                <td class="num">{{ number_format($porMeses, 2) }}</td>
            </tr>
        @endif
        @if ($l->dias > 0)
            <tr>
                <td>Por {{ $l->dias }} {{ $l->dias === 1 ? 'día' : 'días' }}</td>
                <td class="detalle">{{ $rc }} ÷ 360 × {{ $l->dias }}</td>
                <td class="num">{{ number_format($porDias, 2) }}</td>
            </tr>
        @endif
    </table>

    <table class="neto">
        <tr>
            <td class="rotulo-neto">CTS A DEPOSITAR</td>
            <td class="monto">S/ {{ number_format($l->monto, 2) }}</td>
        </tr>
        <tr><td colspan="2" class="son"><strong>SON:</strong> {{ \App\Support\MontoEnLetras::soles($l->monto) }}</td></tr>
    </table>

    <p class="nota">
        La CTS no está afecta a aportes de pensiones, EsSalud ni al Impuesto a la Renta de 5ta categoría.
        Cualquier observación a esta liquidación se presenta a la Oficina de Recursos Humanos.
    </p>

    <p class="lugar-fecha">Juliaca, {{ $enLetra($fechaHoja) }}</p>

    <table class="firmas">
        <tr>
            <td><div class="raya">EL EMPLEADOR<br>{{ $colegioNombre }}</div></td>
            <td><div class="raya">EL TRABAJADOR<br>{{ $empleado->nombre }} {{ $empleado->apellido }} · DNI {{ $empleado->dni }}</div></td>
        </tr>
    </table>
</body>
</html>
