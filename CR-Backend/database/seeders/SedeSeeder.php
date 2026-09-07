<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Sede;

class SedeSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Sede::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        Sede::create(['nombre' => 'CATA', 'direccion' => 'Sede Principal', 'estado' => 'activo']);
        Sede::create(['nombre' => 'Jerusalen', 'direccion' => 'Sede Anexa', 'estado' => 'activo']);
    }
}
