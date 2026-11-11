@extends('emails.plantilla')

@section('preheader', match ($momento) {
    'pedida'   => 'Se pidió cambiar tu cuenta ' . $destino . ' en CATA-Recibo. Si no fuiste tú, avisa a Recursos Humanos.',
    'aprobada' => 'Recursos Humanos aprobó el cambio de tu cuenta ' . $destino . '.',
    default    => 'Recursos Humanos no aprobó el cambio de tu cuenta ' . $destino . '.',
})
@section('titulo', match ($momento) {
    'pedida'   => 'Pediste cambiar tu cuenta ' . $destino,
    'aprobada' => 'Tu cuenta ' . $destino . ' ya cambió',
    default    => 'No se cambió tu cuenta ' . $destino,
})

@section('contenido')
    <p style="margin:0 0 12px;">Hola, <strong>{{ $nombreEmpleado }}</strong>:</p>
    <p style="margin:0 0 16px;">
        @if ($momento === 'pedida')
            Desde tu cuenta de CATA-Recibo se pidió cambiar tu cuenta {{ $destino }} a esta. Recursos Humanos la revisará antes de aprobarla.
        @elseif ($momento === 'aprobada')
            Recursos Humanos aprobó el cambio. Desde ahora tus pagos {{ $destino === 'CTS' ? 'de CTS' : 'de sueldo' }} van a esta cuenta.
        @else
            Recursos Humanos no aprobó el cambio: tu cuenta sigue siendo la de antes.
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#F4F7FC; border:1px solid #DCE3EE; border-radius:10px; margin:0 0 6px;">
        <tr>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif;">
                <span style="display:block; font-size:12px; color:#6B7690;">Entidad</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ $entidad }}</span>
            </td>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif; text-align:right;">
                <span style="display:block; font-size:12px; color:#6B7690;">Cuenta</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ $numeroEnmascarado }}</span>
            </td>
        </tr>
    </table>

    @if ($respuesta)
        <p style="margin:12px 0 0;"><strong>Respuesta de Recursos Humanos:</strong> {{ $respuesta }}</p>
    @endif

    @include('emails._boton', ['texto' => 'Ver mis solicitudes'])

    @if ($momento === 'pedida')
        <p style="margin:0 0 12px;">
            <strong>Si no fuiste tú, avisa hoy mismo a Recursos Humanos</strong> y cambia tu contraseña: alguien podría estar usando tu cuenta.
        </p>
    @endif
@endsection
