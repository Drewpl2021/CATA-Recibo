@extends('emails.plantilla')

@section('preheader', ($restablecer ? 'Recursos Humanos restableció tu acceso: crea una contraseña nueva.' : 'Crea tu contraseña para recibir tus boletas de pago en CATA-Recibo.'))
@section('titulo', ($restablecer ? 'Crea una contraseña nueva' : 'Te damos la bienvenida a CATA-Recibo'))

@section('contenido')
    <p style="margin:0 0 12px;">Hola, <strong>{{ $nombre }}</strong>:</p>

    @if ($restablecer)
        <p style="margin:0 0 12px;">
            Recursos Humanos restableció el acceso a tu cuenta. La contraseña que tenías ya no sirve:
            crea una nueva con este botón.
        </p>
    @else
        <p style="margin:0 0 12px;">
            Ya tienes tu cuenta en <strong>CATA-Recibo</strong>, el sistema donde vas a recibir tus boletas de
            pago y tus documentos laborales. Para empezar, crea tu contraseña:
        </p>
    @endif

    @include('emails._boton', ['texto' => $restablecer ? 'Crear contraseña nueva' : 'Crear mi contraseña'])

    <p style="margin:0 0 12px;">
        Después entrarás siempre con tu correo <strong>{{ $email }}</strong> y la contraseña que elijas.
    </p>

    @include('emails._aviso', ['html' => 'Este enlace es <strong>personal</strong>, sirve <strong>una sola vez</strong> y vence en <strong>' . $horasValidez . ' horas</strong>. No lo reenvíes a nadie. Si venció, pídele a Recursos Humanos que te envíe uno nuevo.'])

    @if ($restablecer)
        <p style="margin:0 0 12px; font-size:13.5px; color:#6B7690;">
            Si no pediste este cambio, avísale hoy mismo a Recursos Humanos.
        </p>
    @endif

    @include('emails._enlace-plano')
@endsection
