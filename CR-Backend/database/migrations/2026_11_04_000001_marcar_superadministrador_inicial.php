<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El rol «admin» de una instalación nueva nacía sin la marca de
 * superadministrador: la migración que la creó corre antes de que existan los
 * roles, y ni el sembrador ni la semilla la ponían. Sin nadie con la marca, ni
 * el Administrador podía cambiar el rol de un usuario.
 *
 * Solo actúa si todavía no hay ningún superadministrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('es_superadmin', true)->exists()) {
            DB::table('roles')->where('nombre', 'admin')->update(['es_superadmin' => true]);
        }
    }

    public function down(): void
    {
        // Quitar la marca dejaría el sistema sin Administrador.
    }
};
