@extends('emails.plantilla')

@section('preheader', 'Tu boleta de pago de ' . ($mesNombre) . ' ' . ($anio) . ' ya está en CATA-Recibo, firmada por el colegio.')
@section('titulo', 'Tu boleta de ' . ($mesNombre) . ' ya está lista')

@section('contenido')
    <p style="margin:0 0 12px;">Hola, <strong>{{ $nombreEmpleado }}</strong>:</p>
    <p style="margin:0 0 16px;">
        Tu boleta de pago ya está en tu cuenta de CATA-Recibo, con la firma digital del colegio.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background:#F4F7FC; border:1px solid #DCE3EE; border-radius:10px; margin:0 0 6px;">
        <tr>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif;">
                <span style="display:block; font-size:12px; color:#6B7690;">Periodo</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ $mesNombre }} {{ $anio }}</span>
            </td>
            <td style="padding:14px 18px; font-family:'Segoe UI', Arial, Helvetica, sans-serif; text-align:right;">
                <span style="display:block; font-size:12px; color:#6B7690;">N.° de boleta</span>
                <span style="display:block; font-size:17px; font-weight:700; color:#1B4282;">{{ $numeroBoleta }}</span>
            </td>
        </tr>
    </table>

    @include('emails._boton', ['texto' => 'Ver mi boleta'])

    <p style="margin:0 0 12px;">
        Revísala y, si todo está bien, <strong>da tu conformidad</strong> con tu contraseña. Si algo no cuadra,
        avísale a Recursos Humanos dentro de los 30 días.
    </p>
@endsection
