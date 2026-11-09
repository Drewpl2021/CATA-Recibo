<?php

namespace App\Support;

use App\Exceptions\AvisoParaElUsuario;
use App\Mail\AccesoAlSistema;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * El primer acceso a una cuenta, por correo y nunca con el DNI.
 *
 * Una cuenta nace con una clave aleatoria que nadie conoce
 * (claveQueNadieConoce). Para entrar, la persona recibe un enlace personal
 * («Tu acceso a CATA-Recibo») y crea su contraseña. El enlace sirve una vez
 * y vence a las 72 horas; si vence, RR.HH. le envía otro.
 *
 * Primero sale el correo y recién después se toca la cuenta: si se anulara
 * antes y el correo fallara, la persona se quedaría sin poder entrar.
 *
 * enviar() va por la cola y nunca anula nada: quien ya tiene su contraseña la
 * conserva hasta que use el enlace. restablecer() envía en el momento y, solo
 * si el correo salió, anula la clave y cierra las sesiones.
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
     * Le pone en la cola el enlace para crear su contraseña.
     *
     * @return string|null por qué no se pudo (null si quedó en la cola)
     */
    public static function enviar(User $usuario): ?string
    {
        if ($motivo = self::porQueNoSePuede($usuario)) {
            return $motivo;
        }

        try {
            $token = Password::broker(self::BROKER)->createToken($usuario);
            Mail::to($usuario->email)->queue(new AccesoAlSistema($usuario->name, $usuario->email, $token, ! $usuario->debe_cambiar_password));
        } catch (Throwable $e) {
            Log::error('No se pudo poner en cola el acceso por correo', ['usuario' => $usuario->id, 'excepcion' => $e::class, 'mensaje' => $e->getMessage()]);

            return 'no se pudo preparar el correo';
        }

        $usuario->forceFill(['acceso_enviado_en' => now()])->save();

        return null;
    }

    /**
     * Restablece una cuenta: la clave actual se anula solo si el correo salió.
     *
     * @throws AvisoParaElUsuario si el correo no salió; la cuenta queda como estaba
     */
    public static function restablecer(User $usuario): void
    {
        if ($motivo = self::porQueNoSePuede($usuario)) {
            throw new AvisoParaElUsuario("No se pudo restablecer: {$motivo}.");
        }

        try {
            $token = Password::broker(self::BROKER)->createToken($usuario);
            Mail::to($usuario->email)->sendNow(new AccesoAlSistema($usuario->name, $usuario->email, $token, true));
        } catch (Throwable $e) {
            Log::error('No salió el correo para restablecer una cuenta', ['usuario' => $usuario->id, 'excepcion' => $e::class, 'mensaje' => $e->getMessage()]);

            throw new AvisoParaElUsuario('No se pudo enviar el correo: su contraseña no cambió. Revisa la configuración del correo e inténtalo de nuevo.');
        }

        DB::transaction(function () use ($usuario) {
            $usuario->forceFill([
                'password'              => self::claveQueNadieConoce(),
                'debe_cambiar_password' => true,
                'acceso_enviado_en'     => now(),
            ])->save();
            $usuario->tokens()->delete();
        });
    }

    private static function porQueNoSePuede(User $usuario): ?string
    {
        if (! $usuario->email || ! filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
            return 'no tiene un correo válido';
        }

        return $usuario->estado_registro === 'activo' ? null : 'su cuenta está dada de baja';
    }
}
