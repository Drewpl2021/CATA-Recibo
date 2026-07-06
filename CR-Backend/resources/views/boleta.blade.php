<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #222;
            zoom: 0.68;
        }

        .boleta {
            width: 96%;
            margin: 0 auto;
            padding: 15px;
            border: 2px solid #1565C0;
        }

        .boleta + .boleta {
            page-break-before: always;
        }

        .encabezado {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1565C0;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .encabezado-logo {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
        }

        .encabezado-logo img {
            width: 65px;
            height: 65px;
        }

        .encabezado-texto {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        .encabezado-texto h1 {
            font-size: 13px;
            color: #1565C0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .encabezado-texto h2 {
            font-size: 11px;
            color: #B8860B;
            margin-top: 3px;
            font-weight: bold;
        }

        .encabezado-texto p {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }

        .titulo-boleta {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #1565C0;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .seccion { margin-bottom: 4px; }

        .seccion-titulo {
            background: #1565C0;
            color: white;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .seccion-titulo.descuento {
            background: #B71C1C;
        }

        .seccion-titulo.aporte {
            background: #2E7D32;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table td {
            padding: 3px 6px;
            border: 1px solid #ddd;
            font-size: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table td.monto {
            text-align: right;
        }

        .fila-total td {
            background: #B8860B;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }

        .fila-subtotal td {
            background: #e8e8e8;
            font-weight: bold;
        }

        .nota {
            font-size: 8px;
            color: #888;
            margin-top: 3px;
            font-style: italic;
        }

        .firma-section {
            margin-top: 4px;
            display: table;
            width: 100%;
            page-break-inside: avoid;
            page-break-before: avoid;
        }

        .firma-box {
            display: table-cell;
            width: 48%;
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #333;
            font-size: 9px;
            color: #555;
        }

        .copia-label {
            text-align: center;
            font-size: 9px;
            color: #B8860B;
            margin-bottom: 6px;
            font-style: italic;
            font-weight: bold;
        }

        .separador {
            border: none;
            border-top: 2px dashed #aaa;
            margin: 18px 0;
        }

        .firma-digital {
            margin-top: 8px;
            padding: 6px 8px;
            background: #f0f7ff;
            border: 1px solid #1565C0;
            font-size: 9px;
            color: #1565C0;
        }
    </style>
</head>
<body>

@php
    $totalIngresos = (float)$planilla->sueldo_base
        + (float)$planilla->bonificaciones
        + $asignacionFamiliar
        + $gratificacion['total'];

    $totalDescuentosManual = (float)$planilla->descuentos;
$totalDescuentosOtros = $descuentos->sum('monto');
$totalDescuentos = $totalDescuentosManual + $pension['total'] + $renta5ta + $totalDescuentosOtros;

    $totalNeto = $totalIngresos - $totalDescuentos;
@endphp

@for ($copia = 1; $copia <= 2; $copia++)
<div class="boleta">
    <div class="copia-label">
        @if ($copia == 1)
            -- COPIA TRABAJADOR --
        @else
            -- COPIA INSTITUCIÓN --
        @endif
    </div>

    <div class="encabezado">
        <div class="encabezado-logo">
            <img src="{{ public_path('logo.png') }}">
        </div>
        <div class="encabezado-texto">
            <h1>Asociación Educativa Colegio Adventista Túpac Amaru</h1>
            <h2>Boleta de Pago — {{ $mes_nombre }} {{ $anio }}</h2>
            <p>Documento generado el {{ now()->format('d/m/Y') }}</p>
            <p>N° Boleta: {{ $numero_boleta }}</p>
        </div>
    </div>

    <div class="titulo-boleta">Boleta de Remuneraciones</div>

    <div class="seccion">
        <div class="seccion-titulo">Datos del Trabajador</div>
        <table>
            <tr>
                <td class="label">Apellidos y Nombres</td>
                <td>{{ $empleado->apellido }}, {{ $empleado->nombre }}</td>
                <td class="label">DNI</td>
                <td>{{ $empleado->dni }}</td>
            </tr>
            <tr>
                <td class="label">Cargo</td>
                <td>{{ $empleado->cargo->nombre ?? 'Sin asignar' }}</td>
                <td class="label">Área</td>
                <td>{{ $empleado->area->nombre ?? 'Sin asignar' }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de Ingreso</td>
                <td>{{ \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') }}</td>
                <td class="label">Estado</td>
                <td>{{ ucfirst($empleado->estado) }}</td>
            </tr>
            <tr>
                <td class="label">Sistema de Pensión</td>
                <td>{{ $pension['tipo'] }}</td>
                <td class="label">CUSPP</td>
                <td>{{ $empleado->cuspp ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Entidad Financiera</td>
                <td>{{ $empleado->entidad_financiera ?? '-' }}</td>
                <td class="label">N° de Cuenta</td>
                <td>{{ $empleado->numero_cuenta ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Ingresos</div>
        <table>
            <tr>
                <td class="label">Remuneración Básica</td>
                <td class="monto">S/ {{ number_format($planilla->sueldo_base, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Bonificaciones</td>
                <td class="monto">S/ {{ number_format($planilla->bonificaciones, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Asignación Familiar</td>
                <td class="monto">S/ {{ number_format($asignacionFamiliar, 2) }}</td>
            </tr>
            @if ($gratificacion['aplica'])
            <tr>
                <td class="label">Gratificación ({{ $mes_nombre }}) — {{ $gratificacion['meses_trabajados'] }}/6 meses</td>
                <td class="monto">S/ {{ number_format($gratificacion['monto_base'] + $gratificacion['asignacion_familiar'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">Bonificación Extraordinaria (Ley 30334, 9%)</td>
                <td class="monto">S/ {{ number_format($gratificacion['bonificacion_extraordinaria'], 2) }}</td>
            </tr>
            @endif
            <tr class="fila-subtotal">
                <td class="label">Total Ingresos</td>
                <td class="monto">S/ {{ number_format($totalIngresos, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo descuento">Descuentos</div>
        <table>
            @foreach ($pension['detalle'] as $item)
            <tr>
                <td class="label">{{ $item['concepto'] }}</td>
                <td class="monto">S/ {{ number_format($item['monto'], 2) }}</td>
            </tr>
            @endforeach
            @if($descuentos->count() > 0)
                @foreach($descuentos as $desc)
                <tr>
                    <td class="label">{{ ucfirst($desc->tipo) }}</td>
                    <td class="monto">S/ {{ number_format($desc->monto, 2) }}</td>
                </tr>
                @endforeach
            @endif
            @if($totalDescuentosManual > 0)
            <tr>
                <td class="label">Otros Descuentos</td>
                <td class="monto">S/ {{ number_format($totalDescuentosManual, 2) }}</td>
            </tr>
            @endif
            @if($renta5ta > 0)
            <tr>
                <td class="label">I.R. 5ta Categoría</td>
                <td class="monto">S/ {{ number_format($renta5ta, 2) }}</td>
            </tr>
            @endif
            <tr class="fila-subtotal">
                <td class="label">Total Descuentos</td>
                <td class="monto">S/ {{ number_format($totalDescuentos, 2) }}</td>
            </tr>
        </table>
        <p class="nota">El descuento por {{ $pension['tipo'] }} se calcula sobre la remuneración básica según tasas vigentes 2026.</p>
    </div>

    <div class="seccion">
        <div class="seccion-titulo aporte">Aportaciones del Empleador (Informativo)</div>
        <table>
            <tr>
                <td class="label">ESSALUD (9%)</td>
                <td class="monto">S/ {{ number_format($essalud, 2) }}</td>
            </tr>
        </table>
        <p class="nota">Este monto es asumido íntegramente por el empleador y no afecta el sueldo neto del trabajador.</p>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Total Neto a Pagar</div>
        <table>
            <tr class="fila-total">
                <td class="label" style="color:white;">TOTAL NETO</td>
                <td class="monto">S/ {{ number_format($totalNeto, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="firma-section">
        <div class="firma-box" style="margin-right:4%;">
            @if (isset($documento) && $documento->estado_firma === 'firmado' && $empleado->firma_imagen)
                <img src="{{ storage_path('app/public/' . $empleado->firma_imagen) }}" style="height:50px; margin-bottom:5px;"><br>
            @endif
            <div style="border-top:1px solid #333; padding-top:5px; margin-top:5px;">
                Firma del Trabajador<br>
                {{ $empleado->apellido }}, {{ $empleado->nombre }}
            </div>
        </div>
        <div class="firma-box">
            @if (file_exists(storage_path('app/public/firmas/rrhh.png')))
                <img src="{{ storage_path('app/public/firmas/rrhh.png') }}" style="height:50px; margin-bottom:5px;"><br>
            @endif
            <div style="border-top:1px solid #333; padding-top:5px; margin-top:5px;">
                Firma Empleador<br>
                Colegio Adventista Túpac Amaru
            </div>
        </div>
    </div>

    @if (isset($documento) && $documento->estado_firma === 'firmado')
    <div class="firma-digital">
        Documento firmado digitalmente por {{ $documento->firmado_por }}
        el {{ \Carbon\Carbon::parse($documento->fecha_firma)->format('d/m/Y H:i') }} —
        Código de verificación: {{ $documento->codigo_firma }}
    </div>
    @endif

</div>
@endfor

</body>
</html>