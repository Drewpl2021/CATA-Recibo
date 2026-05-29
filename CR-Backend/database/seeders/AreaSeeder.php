<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Area;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Area::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $areas = ['Matemáticas', 'Comunicación', 'Ciencias', 'Historia', 'Educación Física', 'Inglés', 'Arte', 'Religión'];
        foreach ($areas as $nombre) {
            Area::create(['nombre' => $nombre]);
        }
    }
}