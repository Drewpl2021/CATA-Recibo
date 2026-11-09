@extends('emails.plantilla')

@section('preheader', 'Tu ' . $queEs . ' de ' . $periodo . ' ya está en CATA-Recibo, ' . ($femenino ? 'firmada' : 'firmado') . ' por el colegio.')
@section('titulo', 'Tu ' . $queEs . ' ya está ' . ($femenino ? 'lista' : 'listo'))

@section('contenido')
    <p style="margin:0 0 12px;">Hola, <strong>{{ $nombreEmpleado }}</strong>:</p>
    <p style="margin:0 0 16px;">
        Tu {{ $queEs }} ya está en tu cuenta de CATA-Recibo, con la firma digital del colegio.
        {{ $femenino ? 'La' : 'Lo' }} encuentras en <strong>Mis Boletas</strong>, debajo de tus boletas.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#F4F7FC; border:1px solid #DCE3EE; border-radius:10px; margin:0 0 6px;">
        <tr>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif;">
                <span style="display:block; font-size:12px; color:#6B7690;">Documento</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ ucfirst($queEs) }}</span>
            </td>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif; text-align:right;">
                <span style="display:block; font-size:12px; color:#6B7690;">Periodo</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ ucfirst($periodo) }}</span>
            </td>
        </tr>
    </table>

    @include('emails._boton', ['texto' => $femenino ? 'Ver mi hoja' : 'Ver mi certificado'])

    <p style="margin:0 0 12px;">
        {{ $femenino ? 'Revísala' : 'Revísalo' }} y, si todo está bien, <strong>da tu conformidad</strong> con tu contraseña. Si algo no cuadra,
        avísale a Recursos Humanos.
    </p>
@endsection
