<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja registrar a un trabajador que NO aporta a ninguna pensión.
 *
 * La columna era `enum('AFP','ONP') NOT NULL DEFAULT 'ONP'`, así que la
 * tercera posibilidad no existía: al que no aportaba se le guardaba ONP y el
 * motor le descontaba el 13% igual. Ahora el nulo es un dato con significado
 * —"no aporta"— y no un "todavía no lo sé".
 *
 * Es el caso del jubilado que ya cobra su pensión y vuelve a dictar, y el del
 * extranjero acogido a un convenio: por ley no aportan.
 *
 * Se usa SQL directo porque cambiar un enum con doctrine/dbal se lleva por
 * delante el valor por defecto y los datos que ya están.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE empleados MODIFY sistema_pensiones ENUM('AFP','ONP') NULL DEFAULT 'ONP'");
    }

    public function down(): void
    {
        // Al volver atrás, los que no aportaban pasan a ONP: es lo único que
        // la columna sabe decir cuando no admite nulos.
        DB::table('empleados')->whereNull('sistema_pensiones')->update(['sistema_pensiones' => 'ONP']);
        DB::statement("ALTER TABLE empleados MODIFY sistema_pensiones ENUM('AFP','ONP') NOT NULL DEFAULT 'ONP'");
    }
};
