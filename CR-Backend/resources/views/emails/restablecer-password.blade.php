@extends('emails.plantilla')

@section('preheader', 'Pediste una contraseña nueva para CATA-Recibo. El enlace vence en ' . ($minutosValidez) . ' minutos.')
@section('titulo', '¿Olvidaste tu contraseña?')

@section('contenido')
    <p style="margin:0 0 12px;">Hola, <strong>{{ $nombre }}</strong>:</p>
    <p style="margin:0 0 12px;">
        Recibimos un pedido para poner una contraseña nueva en tu cuenta de CATA-Recibo. Puedes hacerlo con este botón:
    </p>

    @include('emails._boton', ['texto' => 'Poner una contraseña nueva'])

    @include('emails._aviso', ['html' => 'El enlace sirve <strong>una sola vez</strong> y vence en <strong>' . $minutosValidez . ' minutos</strong>. Si no fuiste tú quien lo pidió, no hagas nada: tu contraseña sigue siendo la de siempre.'])

    <p style="margin:0 0 12px;">Si el enlace ya venció, pide otro desde la pantalla de ingreso.</p>

    @include('emails._enlace-plano')
@endsection
