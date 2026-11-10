<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La hoja de liquidación de la CTS que el colegio entrega con cada
        depósito, con la redacción de la hoja que usaba el colegio: la
        constancia del depósito (art. 24 del TUO del D. Leg. 650), el periodo
        con años, meses y días, la remuneración compensable, el cálculo por
        años, meses y días, el monto en letras y las firmas (digital del
        colegio y la línea del trabajador, ver partials/firma-digital).
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
        .titulo .titulo__sub { font-size: 10px; font-style: italic; font-weight: bold; color: #1F2B33; margin-top: 3px; }

        /* ── La constancia: el texto que el colegio entrega con el depósito ── */
        .constancia { margin-top: 12px; padding: 8px 10px; background: #EDF3F5; border-left: 3px solid #EC7A3A; font-size: 8.8px; line-height: 1.6; text-align: justify; }
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

        .tiempo { margin-top: 10px; }
        .tiempo td { text-align: center; padding: 6px 8px; border-right: 1px solid #DCE7EB; }
        .tiempo td:last-child { border-right: none; }
        .tiempo strong { display: block; font-size: 11px; margin-top: 2px; }
        .tiempo span { display: block; font-size: 6.8px; font-weight: bold; text-transform: uppercase; color: #4A5961; }

        .neto { margin-top: 10px; border: 1.5px solid #1F2B33; }
        .neto td { padding: 8px 10px; }
        .neto .rotulo-neto { font-size: 10px; font-weight: bold; }
        .neto .monto { text-align: right; font-size: 14px; font-weight: bold; }
        .son { padding: 5px 10px; background: #EDF3F5; font-size: 8.2px; border-top: 1px solid #9FB6BF; }

        .nota { font-size: 7px; color: #6B7A82; margin-top: 10px; line-height: 1.45; }
        .lugar-fecha { margin-top: 14px; text-align: right; font-size: 8.8px; }
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
    // El monto sale de la remuneración sin redondear (ver MotorCts): las dos
    // filas se reparten el monto para que siempre sumen lo depositado. Sin
    // días, todo es de los meses (si no, salía «-0.01» por días).
    $porMeses    = $l->dias > 0 ? round($l->remuneracion_computable / 12 * $l->meses, 2) : (float) $l->monto;
    $porDias     = round($l->monto - $porMeses, 2);
    $rc          = number_format($l->remuneracion_computable, 2);
    $cesado      = $empleado->estado === 'inactivo' && $empleado->fecha_cese;
    $fechaHoja   = $l->fecha_deposito ?? now();

    // El semestre que se liquida, en meses: «noviembre 2025 a abril 2026».
    $mesDesde    = mb_strtolower($meses[\Carbon\Carbon::parse($l->desde)->month]) . ' ' . \Carbon\Carbon::parse($l->desde)->year;
    $mesHasta    = mb_strtolower($mesCierre);
    // Un depósito es de un semestre: nunca llega a un año completo. La fila
    // de «años» se deja, como en la hoja del colegio, y sale con «-».
    $anios       = 0;
    $porAnios    = 0.0;
    $cuenta      = $l->numero_cuenta ?: $empleado->cts_numero_cuenta;
    $entidad     = $l->entidad_financiera ?: $empleado->cts_entidad_financiera;
    $representante = $representante ?? null;
    // «; representada por el Sr. … identificado con DNI …», escapado aquí
    // porque va con {!! !!} (para que no quede un espacio suelto antes de la coma).
    $representadaPor = $representante
        ? '; representada por el Sr. <strong>' . e($representante['nombre']) . '</strong>'
            . ($representante['dni'] ? ', identificado con DNI N° ' . e($representante['dni']) : '')
        : '';
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
                <strong>JULIACA</strong><br>
                {{ $colegioDireccion }}<br>
                RUC {{ $colegioRuc }}
            </td>
        </tr>
    </table>

    <div class="titulo">
        <h1>LIQUIDACIÓN DE COMPENSACIÓN POR TIEMPO DE SERVICIOS</h1>
        <p class="titulo__sub">Correspondiente al periodo de {{ $mesDesde }} a {{ $mesHasta }}</p>
        <span class="periodo">DEPÓSITO DE {{ mb_strtoupper($l->periodo) }} {{ $l->anio }}</span>
    </div>

    {{-- ── La constancia, como la redacta el colegio ── --}}
    <div class="constancia">
        La <strong>{{ mb_strtoupper($colegioNombre) }}</strong>, con domicilio en Jirón Moquegua N° 852 de la ciudad de Juliaca,
        con RUC N° {{ $colegioRuc }}{!! $representadaPor !!},
        otorga la presente constancia a: <strong>{{ mb_strtoupper(trim("{$empleado->apellido} {$empleado->nombre}")) }}</strong>,
        con DNI N° <strong>{{ $empleado->dni }}</strong>,
        @if ($l->fecha_deposito)
            del depósito de su Compensación por Tiempo de Servicios realizado el {{ $enLetra($l->fecha_deposito) }}
        @else
            del depósito de su Compensación por Tiempo de Servicios, que se realiza hasta el {{ $enLetra($semestre['limite']) }},
        @endif
        @if ($cuenta)
            en la Cuenta CTS N° <strong>{{ $cuenta }}</strong>@if ($entidad) en {{ $entidad }}@endif,
        @elseif ($cesado)
            pagado directamente al cese,
        @endif
        por los siguientes montos y periodos. Cumpliendo con lo dispuesto en el artículo 24 del TUO del D. Leg. N° 650,
        Ley de CTS, aprobado mediante el D.S. N° 001-97-TR.
    </div>

    {{-- ── Periodo y tiempo computado ── --}}
    <table class="cuadro tiempo">
        <tr>
            <td style="width: 32%;"><span>Del</span><strong>{{ $fecha($l->desde) }}</strong></td>
            <td style="width: 32%;"><span>Al</span><strong>{{ $fecha($l->hasta) }}</strong></td>
            <td style="width: 12%;"><span>Años</span><strong>{{ $anios }}</strong></td>
            <td style="width: 12%;"><span>Meses</span><strong>{{ $l->meses }}</strong></td>
            <td style="width: 12%;"><span>Días</span><strong>{{ $l->dias }}</strong></td>
        </tr>
    </table>
    @if ($l->faltas > 0)
        <p class="nota">Del periodo se descontaron {{ $l->faltas }} {{ $l->faltas === 1 ? 'día' : 'días' }} de inasistencia injustificada: solo se computan los días de trabajo efectivo.</p>
    @endif

    {{-- ── Remuneración compensable ── --}}
    <div class="barra">Remuneración compensable</div>
    <table class="cuadro">
        <tr><td style="width: 75%;">Remuneración <span class="detalle">({{ mb_strtolower($mesCierre) }})</span></td><td class="num" style="width: 25%;">S/ {{ $monto($l->sueldo) }}</td></tr>
        <tr><td>Bonificación por cargo</td><td class="num">S/ {{ $monto($l->bonificacion_cargo) }}</td></tr>
        <tr><td>Asignación familiar</td><td class="num">S/ {{ $monto($l->asignacion_familiar) }}</td></tr>
        <tr>
            <td>
                Un sexto de gratificación <span class="detalle">({{ $gratiMes }}, con su bonificación extraordinaria{{ $l->gratificacion > 0 ? ': S/ ' . number_format($l->gratificacion, 2) . ' ÷ 6' : '' }})</span>
            </td>
            <td class="num">S/ {{ $monto($l->sexto_gratificacion) }}</td>
        </tr>
        <tr class="total"><td>Total remuneración compensable</td><td class="num">S/ {{ number_format($l->remuneracion_computable, 2) }}</td></tr>
    </table>

    {{-- ── Cálculo: por años, meses y días ── --}}
    <div class="barra">Cálculo de la CTS</div>
    <table class="cuadro calculo">
        <tr>
            <th style="width: 30%;">Concepto</th>
            <th class="num" style="width: 18%;">Remuneración</th>
            <th class="centro" style="width: 12%;">Factor</th>
            <th class="centro" style="width: 18%;">Tiempo</th>
            <th class="num" style="width: 22%;">Importe S/</th>
        </tr>
        <tr>
            <td>Por los años completos</td>
            <td class="num">{{ $rc }}</td>
            <td class="centro">×</td>
            <td class="centro">{{ $anios }} {{ $anios === 1 ? 'año' : 'años' }}</td>
            <td class="num">{{ $monto($porAnios) }}</td>
        </tr>
        <tr>
            <td>Por los meses completos</td>
            <td class="num">{{ $rc }}</td>
            <td class="centro">÷ 12 ×</td>
            <td class="centro">{{ $l->meses }} {{ $l->meses === 1 ? 'mes' : 'meses' }}</td>
            <td class="num">{{ $monto($porMeses) }}</td>
        </tr>
        <tr>
            <td>Por los días completos</td>
            <td class="num">{{ $rc }}</td>
            <td class="centro">÷ 360 ×</td>
            <td class="centro">{{ $l->dias }} {{ $l->dias === 1 ? 'día' : 'días' }}</td>
            <td class="num">{{ $monto($porDias) }}</td>
        </tr>
    </table>

    <table class="neto">
        <tr>
            <td class="rotulo-neto">{{ $l->fecha_deposito ? 'MONTO DEPOSITADO' : 'MONTO A DEPOSITAR' }}</td>
            <td class="monto">S/ {{ number_format($l->monto, 2) }}</td>
        </tr>
        <tr><td colspan="2" class="son"><strong>SON:</strong> {{ \App\Support\Pdf\MontoEnLetras::soles($l->monto) }}</td></tr>
    </table>

    <p class="nota">
        La CTS no está afecta a aportes de pensiones, EsSalud ni al Impuesto a la Renta de 5ta categoría.
        Cualquier observación a esta liquidación se presenta a la Oficina de Recursos Humanos.
    </p>
    <p class="lugar-fecha">Juliaca, {{ $enLetra($fechaHoja) }}</p>

    @include('partials.firma-digital', [
        'trabajador' => trim("{$empleado->nombre} {$empleado->apellido}"),
        'dni'        => $empleado->dni,
    ])
</body>
</html>
