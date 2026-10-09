<?php

namespace App\Support;

use App\Mail\AccesoAlSistema;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * El primer acceso a una cuenta, por correo y nunca con el DNI.
 *
 * Una cuenta nace con una clave aleatoria que nadie conoce
 * (claveQueNadieConoce). Para entrar, la persona recibe un enlace personal
 * («Tu acceso a CATA-Recibo») y crea su contraseña. El enlace sirve una vez
 * y vence a las 72 horas; si vence, RR.HH. le envía otro.
 *
 * Enviar el acceso de nuevo anula la contraseña que tuviera: es lo que se
 * quiere cuando RR.HH. «restablece» una cuenta (alguien la olvidó o se sospecha
 * que otro entró). Por eso aquí no se envía a quien ya entró y tiene su
 * contraseña, salvo que se pida restablecerla a propósito.
 */
final class AccesoPorCorreo
{
    public const BROKER = 'invitaciones';

    /** Una contraseña aleatoria y larga: la cuenta existe, pero nadie puede entrar con ella. */
    public static function claveQueNadieConoce(): string
    {
        return Hash::make(Str::password(40));
    }

    /**
     * Le envía el enlace para crear su contraseña.
     *
     * @return string|null por qué no se envió (null si se envió)
     */
    public static function enviar(User $usuario, bool $anularClaveActual = true, ?bool $restablecer = null): ?string
    {
        // Si ya había puesto su propia contraseña, esto es un restablecimiento
        // y el correo lo dice así; si no, es su bienvenida.
        $restablecer ??= ! $usuario->debe_cambiar_password;

        if (! $usuario->email || ! filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
            return 'no tiene un correo válido';
        }
        if ($usuario->estado_registro !== 'activo') {
            return 'su cuenta está dada de baja';
        }

        if ($anularClaveActual) {
            $usuario->forceFill([
                'password'              => self::claveQueNadieConoce(),
                'debe_cambiar_password' => true,
            ])->save();
            // Si se le restablece el acceso es porque algo pasó con la cuenta.
            $usuario->tokens()->delete();
        }

        $token = Password::broker(self::BROKER)->createToken($usuario);
        Mail::to($usuario->email)->queue(new AccesoAlSistema($usuario->name, $usuario->email, $token, $restablecer));

        $usuario->forceFill(['acceso_enviado_en' => now()])->save();

        return null;
    }
}
