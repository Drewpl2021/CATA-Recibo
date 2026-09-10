<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El grupo del menú se llamaba "Dashboard".
 *
 * Es la única palabra en inglés que quedaba en la barra lateral, y encima
 * era redundante: el grupo decía "Dashboard" y su único ítem, "Panel de
 * Control". Quien usa esto son la secretaria y RR.HH. de un colegio, no
 * gente de sistemas.
 *
 * El seeder ya lo siembra como "Inicio"; esto es para las bases que ya
 * están instaladas, que no se vuelven a sembrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulo_padre')
            ->where('nombre', 'Dashboard')
            ->update(['nombre' => 'Inicio', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('modulo_padre')
            ->where('nombre', 'Inicio')
            ->update(['nombre' => 'Dashboard', 'updated_at' => now()]);
    }
};
