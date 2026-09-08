<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca las cuentas que todavía entran con la contraseña que les dieron.
 *
 * Al dar de alta a un empleado su contraseña inicial es su DNI, y el DNI lo
 * sabe medio colegio: está en la ficha, en el listado y en la boleta. Hasta
 * ahora nada obligaba a cambiarla, así que una cuenta podía quedarse años
 * abierta con una contraseña pública.
 *
 * Con esta bandera el sistema no deja pasar a ninguna pantalla hasta que la
 * cambie (middleware ExigirCambioPassword). Se enciende al crear el usuario
 * y al restablecerle la contraseña desde RR.HH., y se apaga sola cuando el
 * trabajador la cambia.
 *
 * A las cuentas que ya existen se les enciende salvo a las tres de demo, que
 * son las que se usan para probar y no tienen el DNI por contraseña.
 */
return new class extends Migration
{
    private const CUENTAS_DE_PRUEBA = [
        'admin@colegio.com',
        'rrhh@colegio.com',
        'test@colegio.com',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('debe_cambiar_password')->default(false)->after('password');
        });

        $marcadas = DB::table('users')
            ->whereNotIn('email', self::CUENTAS_DE_PRUEBA)
            ->update(['debe_cambiar_password' => true]);

        echo "   {$marcadas} cuenta(s) deberán cambiar su contraseña al entrar\n";
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_password');
        });
    }
};
