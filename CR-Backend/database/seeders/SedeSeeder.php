<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Sede;

/**
 * Los locales del colegio.
 *
 * Son estos cuatro y se escriben tal como los llama la gente: el nombre sale
 * en la ficha de cada trabajador, en el filtro del panel y en las cabeceras
 * de los reportes.
 *
 * La dirección y el teléfono se dejan vacíos a propósito: se rellenan desde
 * la pantalla de Sedes, que para eso está, y así no se inventan datos que
 * después nadie corrige.
 */
class SedeSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Sede::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sedes = ['CATA Central', 'CATA Jerusalén', 'CATA Osis', 'CATA Inicial'];

        foreach ($sedes as $nombre) {
            Sede::create(['nombre' => $nombre, 'estado' => 'activo']);
        }

        $this->command?->info('   ' . count($sedes) . ' sedes: ' . implode(', ', $sedes));
    }
}
