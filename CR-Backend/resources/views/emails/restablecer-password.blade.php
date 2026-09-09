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
        .boton { display: inline-block; background: #1565C0; color: #ffffff; text-decoration: none;
                 padding: 12px 22px; border-radius: 4px; font-weight: bold; margin: 16px 0; }
        .enlace-plano { word-break: break-all; font-size: 12px; color: #555; }
        .aviso { background: white; border-left: 4px solid #F4B41A; padding: 12px 15px; margin: 15px 0; }
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
            <p>Hola <strong>{{ $nombre }}</strong>,</p>
            <p>Pediste volver a entrar al sistema y no recuerdas tu contraseña. Este enlace te deja poner una nueva:</p>

            <p style="text-align: center;">
                <a class="boton" href="{{ $enlace }}">Poner una contraseña nueva</a>
            </p>

            <p>Si el botón no funciona, copia esta dirección en tu navegador:</p>
            <p class="enlace-plano">{{ $enlace }}</p>

            <div class="aviso">
                <p style="margin: 0;">
                    El enlace sirve <strong>una sola vez</strong> y vence en {{ $minutosValidez }} minutos.
                    Si no fuiste tú quien lo pidió, no hagas nada: tu contraseña sigue siendo la de siempre.
                </p>
            </div>

            <p>Si el enlace ya venció, pide otro desde la pantalla de ingreso, o acércate a Recursos Humanos.</p>
        </div>
        <div class="pie">
            <p>Este correo es generado automáticamente. Por favor no responda a este mensaje.</p>
            <p>© {{ date('Y') }} Colegio Adventista Túpac Amaru</p>
        </div>
    </div>
</body>
</html>
