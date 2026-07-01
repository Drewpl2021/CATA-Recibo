<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #222; font-size: 14px; }
        .contenedor { max-width: 600px; margin: 0 auto; padding: 20px; }
        .encabezado { background: #1565C0; color: white; padding: 20px; text-align: center; }
        .encabezado h1 { font-size: 18px; margin: 0; }
        .encabezado p { font-size: 12px; margin: 5px 0 0; }
        .cuerpo { padding: 20px; background: #f9f9f9; border: 1px solid #ddd; }
        .cuerpo p { margin: 8px 0; }
        .boleta-info { background: white; border: 1px solid #1565C0; padding: 15px; margin: 15px 0; }
        .boleta-info p { margin: 5px 0; }
        .pie { text-align: center; font-size: 11px; color: #888; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="contenedor">
        <div class="encabezado">
            <h1>Asociación Educativa Colegio Adventista Túpac Amaru</h1>
            <p>Sistema de Gestión de Planillas</p>
        </div>
        <div class="cuerpo">
            <p>Estimado/a <strong>{{ $nombreEmpleado }}</strong>,</p>
            <p>Le informamos que su boleta de pago del siguiente periodo ha sido generada:</p>
            <div class="boleta-info">
                <p><strong>Periodo:</strong> {{ $mesNombre }} {{ $anio }}</p>
                <p><strong>N° Boleta:</strong> {{ $numeroBoleta }}</p>
            </div>
            <p>Puede acceder al sistema para revisar y firmar su boleta de pago.</p>
            <p>Si tiene alguna consulta, comuníquese con el área de Recursos Humanos.</p>
        </div>
        <div class="pie">
            <p>Este correo es generado automáticamente. Por favor no responda a este mensaje.</p>
            <p>© {{ date('Y') }} Colegio Adventista Túpac Amaru</p>
        </div>
    </div>
</body>
</html>