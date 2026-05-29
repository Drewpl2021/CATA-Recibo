<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; }

        .boleta {
            width: 100%;
            padding: 20px;
            border: 2px solid #1565C0;
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

        .seccion { margin-bottom: 10px; }

        .seccion-titulo {
            background: #1565C0;
            color: white;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table td {
            padding: 3px 6px;
            border: 1px solid #ddd;
            font-size: 10px;
        }

        table td.label {
            background: #f5f5f5;
            font-weight: bold;
            width: 35%;
            color: #333;
        }

        .fila-total td {
            background: #B8860B;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }

        .firma-section {
            margin-top: 20px;
            display: table;
            width: 100%;
        }

        .firma-box {
            display: table-cell;
            width: 48%;
            text-align: center;
            padding-top: 35px;
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
    </style>
</head>
<body>

{{-- ══════════════════ COPIA TRABAJADOR ══════════════════ --}}
<div class="boleta">
    <div class="copia-label">-- COPIA TRABAJADOR --</div>

    <div class="encabezado">
        <div class="encabezado-logo">
            <img src="{{ public_path('logo.png') }}">
        </div>
        <div class="encabezado-texto">
            <h1>Asociación Educativa Colegio Adventista Túpac Amaru</h1>
            <h2>Boleta de Pago — {{ $mes_nombre }} {{ $anio }}</h2>
            <p>Documento generado el {{ now()->format('d/m/Y') }}</p>
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
                <td>{{ $empleado->cargo }}</td>
                <td class="label">Área</td>
                <td>{{ $empleado->area }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de Ingreso</td>
                <td>{{ \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') }}</td>
                <td class="label">Estado</td>
                <td>{{ ucfirst($empleado->estado) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Haberes</div>
        <table>
            <tr>
                <td class="label">Sueldo Base</td>
                <td>S/ {{ number_format($planilla->sueldo_base, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Bonificaciones</td>
                <td>S/ {{ number_format($planilla->bonificaciones, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Descuentos</div>
        <table>
            @if($descuentos->count() > 0)
                @foreach($descuentos as $desc)
                <tr>
                    <td class="label">{{ ucfirst($desc->tipo) }}</td>
                    <td>S/ {{ number_format($desc->monto, 2) }}</td>
                </tr>
                @endforeach
            @endif
            <tr>
                <td class="label">Total Descuentos</td>
                <td>S/ {{ number_format($planilla->descuentos, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Total Neto a Pagar</div>
        <table>
            <tr class="fila-total">
                <td class="label" style="color:white;">TOTAL NETO</td>
                <td>S/ {{ number_format($planilla->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="firma-section">
        <div class="firma-box" style="margin-right:4%;">
            Firma del Trabajador<br>
            {{ $empleado->apellido }}, {{ $empleado->nombre }}
        </div>
        <div class="firma-box">
            Firma Empleador<br>
            Colegio Adventista Túpac Amaru
        </div>
    </div>
</div>

<hr class="separador">

{{-- ══════════════════ COPIA INSTITUCIÓN ══════════════════ --}}
<div class="boleta">
    <div class="copia-label">-- COPIA INSTITUCIÓN --</div>

    <div class="encabezado">
        <div class="encabezado-logo">
            <img src="{{ public_path('logo.png') }}">
        </div>
        <div class="encabezado-texto">
            <h1>Asociación Educativa Colegio Adventista Túpac Amaru</h1>
            <h2>Boleta de Pago — {{ $mes_nombre }} {{ $anio }}</h2>
            <p>Documento generado el {{ now()->format('d/m/Y') }}</p>
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
                <td>{{ $empleado->cargo }}</td>
                <td class="label">Área</td>
                <td>{{ $empleado->area }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de Ingreso</td>
                <td>{{ \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') }}</td>
                <td class="label">Estado</td>
                <td>{{ ucfirst($empleado->estado) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Haberes</div>
        <table>
            <tr>
                <td class="label">Sueldo Base</td>
                <td>S/ {{ number_format($planilla->sueldo_base, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Bonificaciones</td>
                <td>S/ {{ number_format($planilla->bonificaciones, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Descuentos</div>
        <table>
            @if($descuentos->count() > 0)
                @foreach($descuentos as $desc)
                <tr>
                    <td class="label">{{ ucfirst($desc->tipo) }}</td>
                    <td>S/ {{ number_format($desc->monto, 2) }}</td>
                </tr>
                @endforeach
            @endif
            <tr>
                <td class="label">Total Descuentos</td>
                <td>S/ {{ number_format($planilla->descuentos, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">Total Neto a Pagar</div>
        <table>
            <tr class="fila-total">
                <td class="label" style="color:white;">TOTAL NETO</td>
                <td>S/ {{ number_format($planilla->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="firma-section">
        <div class="firma-box" style="margin-right:4%;">
            Firma del Trabajador<br>
            {{ $empleado->apellido }}, {{ $empleado->nombre }}
        </div>
        <div class="firma-box">
            Firma Empleador<br>
            Colegio Adventista Túpac Amaru
        </div>
    </div>
</div>

</body>
</html>