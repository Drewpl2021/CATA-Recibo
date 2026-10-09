{{--
    La plantilla de todos los correos del sistema.

    Hecha con tablas y estilos en línea a propósito: es lo único que Gmail,
    Outlook y los celulares muestran igual. El logo va incrustado en el correo
    (no enlazado), así se ve aunque el servidor no sea público; en la vista
    previa sin envío se usa la dirección del sistema.

    Cada correo pone: @section('preheader') — el resumen que se ve en la
    bandeja antes de abrirlo —, @section('titulo') y @section('contenido').
--}}
@php
    $logo = isset($message)
        ? $message->embed(public_path('marca-agua.png'))
        : rtrim((string) config('app.frontend_url'), '/') . '/logo-cata.png';
    $azul = '#1B4282';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo')</title>
</head>
<body style="margin:0; padding:0; background:#EEF2F8; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF2F8;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="max-width:600px; background:#FFFFFF; border-radius:14px; overflow:hidden; border:1px solid #DCE3EE;">

                    {{-- Cabecera: el logo CATA y la línea dorada --}}
                    <tr>
                        <td align="center" style="padding:28px 24px 20px;">
                            <img src="{{ $logo }}" width="170" alt="CATA, Colegio Adventista Túpac Amaru"
                                style="display:block; width:170px; max-width:60%; height:auto; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px;">
                            <div style="height:3px; background:#F4B41A; border-radius:3px; line-height:3px; font-size:0;">&nbsp;</div>
                        </td>
                    </tr>

                    {{-- El mensaje --}}
                    <tr>
                        <td style="padding:28px 32px 8px; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1F2937; font-size:15px; line-height:1.6;">
                            <h1 style="margin:0 0 16px; font-size:22px; line-height:1.3; color:{{ $azul }}; font-weight:700;">@yield('titulo')</h1>
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="padding:20px 32px 26px; font-family:'Segoe UI', Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#6B7690; border-top:1px solid #E5EAF2;">
                            <strong style="color:#374151;">Colegio Adventista Túpac Amaru</strong><br>
                            Jr. Moquegua N.° 852, Juliaca &middot; Recursos Humanos: 051-325096<br>
                            Este correo se envió automáticamente desde CATA-Recibo; no lo respondas.
                            Si tienes una consulta, acércate a Recursos Humanos.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
