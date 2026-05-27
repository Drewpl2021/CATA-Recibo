<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Cargo;

class CargoSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Cargo::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $cargos = ['Director', 'Subdirector', 'Docente', 'Auxiliar', 'Administrativo', 'Psicólogo', 'Secretaria'];
        foreach ($cargos as $nombre) {
            Cargo::create(['nombre' => $nombre]);
        }
    }
}