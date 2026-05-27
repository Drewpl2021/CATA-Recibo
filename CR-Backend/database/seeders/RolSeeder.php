<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Rol;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Rol::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = ['admin', 'rrhh', 'empleado'];
        foreach ($roles as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }
    }
}