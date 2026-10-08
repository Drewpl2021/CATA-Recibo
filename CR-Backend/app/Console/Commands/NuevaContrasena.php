<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Le pone una contraseña nueva a una cuenta, desde el servidor.
 *
 *   php artisan usuario:nueva-contrasena admin@colegio.com
 *
 * Las contraseñas se guardan cifradas: no hay forma de "verlas", ni para el
 * administrador. Si se olvida la del admin (y no hay otro admin que la
 * cambie desde la pantalla, ni correo configurado para "olvidé mi
 * contraseña"), esto es la salida: inventa una, la enseña UNA vez y obliga
 * a cambiarla al entrar. Cierra además las sesiones abiertas de esa cuenta.
 *
 * Solo toca esa cuenta. Volver a correr CuentasInicialesSeeder también
 * serviría, pero cambia a la vez las del admin y de RR.HH.
 */
class NuevaContrasena extends Command
{
    protected $signature = 'usuario:nueva-contrasena {correo : El correo con el que entra la cuenta}';

    protected $description = 'Genera una contraseña nueva para una cuenta y la muestra una sola vez';

    public function handle(): int
    {
        $correo = trim((string) $this->argument('correo'));
        $cuenta = User::where('email', $correo)->first();

        if (! $cuenta) {
            $this->error("  No hay ninguna cuenta con el correo {$correo}.");
            $this->line('  Las que hay de administrador y RR.HH.:');
            User::whereHas('rol', fn ($q) => $q->whereIn('nombre', ['admin', 'rrhh']))
                ->orderBy('email')->get()
                ->each(fn (User $u) => $this->line("    {$u->email}  ({$u->rol?->nombre})"));

            return self::FAILURE;
        }

        // Sin símbolos, como las de la instalación: se teclean desde un papel.
        $clave = Str::password(16, symbols: false);

        $cuenta->forceFill([
            'password'              => Hash::make($clave),
            'debe_cambiar_password' => true,
        ])->save();
        $cuenta->tokens()->delete();

        $this->info("  Cuenta: {$cuenta->email} ({$cuenta->rol?->nombre})");
        $this->warn("  Contraseña nueva: {$clave}");
        $this->line('  Anótala ahora: no se vuelve a mostrar. Al entrar, el sistema pedirá cambiarla.');

        return self::SUCCESS;
    }
}
