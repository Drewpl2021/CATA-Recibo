<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentConcept;

class PaymentConceptSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PaymentConcept::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $concepts = [
            ['nombre' => 'Descuento por tardanza', 'tipo' => 'descuento', 'calculo' => 'fijo', 'valor' => 10.00],
            ['nombre' => 'Descuento por falta', 'tipo' => 'descuento', 'calculo' => 'fijo', 'valor' => 50.00],
            ['nombre' => 'Descuento AFP', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 10.00],
            ['nombre' => 'Descuento seguro', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 4.00],
            ['nombre' => 'Bonificación por puntualidad', 'tipo' => 'bonificacion', 'calculo' => 'fijo', 'valor' => 100.00],
        ];
        foreach ($concepts as $concept) {
            PaymentConcept::create($concept);
        }
    }
}