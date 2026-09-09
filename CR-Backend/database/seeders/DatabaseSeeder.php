<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            AreaSeeder::class,
            CargoSeeder::class,
            PaymentConceptSeeder::class,
            ModuloSeeder::class,
            SedeSeeder::class,
            UsuarioDemoSeeder::class,
        ]);

        // PlanillaDemoSeeder ya no corre: sembraba 13 planillas de mentira,
        // con montos escritos a mano y sin detalle de conceptos. Servía para
        // ver pantallas llenas mientras se construía, pero ahora estorba —
        // las planillas tienen que nacer del generador real, que es lo que
        // se está probando. Para una demo con datos: php artisan db:seed
        // --class=PlanillaDemoSeeder
    }
}