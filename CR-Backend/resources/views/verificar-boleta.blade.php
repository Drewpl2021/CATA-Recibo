<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verificar boleta de pago</title>
    {{-- La abre un celular al escanear el QR de la boleta: una sola pantalla,
         sin nada que cargar de afuera. Colores del sistema (styles.scss):
         #1B4282 marca, #0E2650 marca oscura, #E7EEF9 marca clara. --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            background: #F3F6FB;
            color: #1A2238;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .tarjeta {
            width: 100%;
            max-width: 440px;
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px -10px rgba(16, 27, 51, 0.25);
            overflow: hidden;
        }
        .cabecera {
            background: #1B4282;
            color: #FFFFFF;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .cabecera img { width: 44px; height: 44px; border-radius: 50%; background: #fff; }
        .cabecera strong { display: block; font-size: 15px; }
        .cabecera span { font-size: 12px; opacity: 0.85; }
        .estado {
            margin: 20px 20px 4px;
            padding: 14px 16px;
            border-radius: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
            font-weight: 600;
        }
        .estado--ok { background: #DCFCE7; color: #047857; }
        .estado--mal { background: #FDECEA; color: #B3271C; }
        .estado svg { flex: none; }
        .estado small { display: block; font-weight: 400; font-size: 13px; margin-top: 2px; }
        dl { padding: 12px 20px 20px; }
        .fila { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #E4E9F2; font-size: 14px; }
        .fila:last-child { border-bottom: none; }
        dt { color: #5A6478; }
        dd { font-weight: 600; text-align: right; }
        .neto dd { color: #0E2650; font-size: 16px; }
        .pie { padding: 0 20px 18px; font-size: 12px; color: #5A6478; line-height: 1.5; }
    </style>
</head>
<body>
<main class="tarjeta">
    <div class="cabecera">
        <img src="/logo-colegio.png" alt="">
        <div>
            <strong>Colegio Adventista Túpac Amaru</strong>
            <span>Verificación de boleta de pago</span>
        </div>
    </div>

    @if ($valida)
        <div class="estado estado--ok" role="status">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
            <div>
                Boleta auténtica
                <small>Fue emitida por el colegio y coincide con sus registros.</small>
            </div>
        </div>
        <dl>
            <div class="fila"><dt>N° de boleta</dt><dd>{{ $numero }}</dd></div>
            <div class="fila"><dt>Periodo</dt><dd>{{ $periodo }}</dd></div>
            <div class="fila"><dt>Trabajador</dt><dd>{{ $nombre }}</dd></div>
            <div class="fila"><dt>DNI</dt><dd>{{ $dni }}</dd></div>
            <div class="fila neto"><dt>Total líquido</dt><dd>S/ {{ number_format($neto, 2) }}</dd></div>
            <div class="fila"><dt>Emitida el</dt><dd>{{ $emitida }}</dd></div>
            <div class="fila"><dt>Firma del trabajador</dt><dd>{{ $firmada ? ($fechaFirma ? 'Firmada el ' . $fechaFirma : 'Firmada') : 'Pendiente' }}</dd></div>
        </dl>
        <p class="pie">Compara estos datos con la boleta impresa. Si alguno no coincide, la boleta fue alterada.</p>
    @else
        <div class="estado estado--mal" role="status">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/></svg>
            <div>
                No se pudo verificar
                <small>El código no corresponde a ninguna boleta emitida por el colegio, o fue modificado.</small>
            </div>
        </div>
        <p class="pie" style="padding-top: 12px;">Si tienes la boleta en la mano, consulta con Recursos Humanos del colegio.</p>
    @endif
</main>
</body>
</html>
