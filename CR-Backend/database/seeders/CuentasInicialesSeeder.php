<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Las dos cuentas con las que se entra a un sistema recién instalado:
 * el administrador y Recursos Humanos.
 *
 * No llevan ficha de trabajador (empleado_id queda en null) y es a propósito:
 * son cuentas para OPERAR el sistema, no personas en planilla. Cuando RR.HH.
 * dé de alta al personal, a cada trabajador se le crea su propia cuenta con
 * su ficha, su sueldo y su boleta. Si el director además cobra por planilla,
 * se le da de alta como a cualquiera y se le pone el rol que le toque.
 *
 * La contraseña sale del .env del despliegue. Si no está puesta, acá se
 * inventa una larga, se enseña UNA vez en pantalla y la cuenta queda obligada
 * a cambiarla al entrar: así ninguna instalación nace con una clave que esté
 * escrita en un repositorio público.
 *
 * Se puede volver a correr sin miedo: busca por correo y actualiza en vez de
 * duplicar (`updateOrCreate`).
 */
class CuentasInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Rol::pluck('id', 'nombre');

        foreach (['admin', 'rrhh'] as $rol) {
            $datos = config('instalacion.' . $rol);

            if (! isset($roles[$rol])) {
                $this->command?->error("   Falta el rol '{$rol}': corre antes RolSeeder.");
                continue;
            }

            // Sin contraseña en el .env se inventa una y se obliga a
            // cambiarla: una clave que nadie eligió no debería quedarse.
            $laPusoElDespliegue = ! empty($datos['password']);
            // Sin símbolos: son dieciséis caracteres que alguien va a tener
            // que teclear desde un papel, y un "~" o un "^" en un teclado
            // prestado es un dolor de cabeza. Con letras y números, dieciséis
            // ya son de sobra.
            $clave = $laPusoElDespliegue ? $datos['password'] : Str::password(16, symbols: false);

            $cuenta = User::updateOrCreate(
                ['email' => $datos['email']],
                [
                    'name'                  => $datos['nombre'],
                    'password'              => Hash::make($clave),
                    'rol_id'                => $roles[$rol],
                    'empleado_id'           => null,
                    'estado_registro'       => 'activo',
                    'debe_cambiar_password' => ! $laPusoElDespliegue,
                ]
            );

            $this->command?->info("   {$rol}: {$cuenta->email}");

            if (! $laPusoElDespliegue) {
                // Se enseña una sola vez. No queda en ningún archivo.
                $this->command?->warn("      contraseña generada: {$clave}");
                $this->command?->warn('      anótala ahora; el sistema te la hará cambiar al entrar');
            }
        }
    }
}
