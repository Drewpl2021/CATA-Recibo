<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El primer acceso deja de ser el DNI.
 *
 * Hasta ahora cada cuenta nacía con el DNI como contraseña. El DNI no es un
 * secreto (está en la boleta, en las listas, en cualquier trámite): quien
 * supiera el correo y el DNI de alguien podía entrar a su cuenta antes que él.
 * Ahora la cuenta nace con una clave aleatoria que nadie conoce, y la persona
 * crea la suya con un enlace personal que le llega al correo (Ver
 * App\Support\AccesoPorCorreo).
 *
 *   invitaciones_acceso  los enlaces de «Tu acceso a CATA-Recibo»: aparte de los
 *                        de «olvidé mi contraseña» porque duran distinto (72 h
 *                        frente a 60 min) y así uno no puede pasar por el otro.
 *   acceso_enviado_en    cuándo se le mandó el último enlace.
 *
 * Y las cuentas de trabajadores que todavía no pusieron su contraseña (las
 * que siguen con el DNI) pasan a una clave aleatoria: desde hoy nadie entra
 * con el DNI. Cada una entra cuando RR.HH. le envíe su acceso. Las cuentas de
 * Administración y RR.HH. no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitaciones_acceso', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('acceso_enviado_en')->nullable()->after('debe_cambiar_password');
        });

        $rolEmpleado = DB::table('roles')->where('nombre', 'empleado')->value('id');
        if ($rolEmpleado) {
            DB::table('users')
                ->where('rol_id', $rolEmpleado)
                ->where('debe_cambiar_password', true)
                ->orderBy('id')
                ->eachById(function ($usuario) {
                    DB::table('users')->where('id', $usuario->id)->update([
                        'password' => Hash::make(Str::password(40)),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('acceso_enviado_en');
        });
        Schema::dropIfExists('invitaciones_acceso');
        // Las claves aleatorias no se devuelven al DNI: eso es justo lo que se quitó.
    }
};
