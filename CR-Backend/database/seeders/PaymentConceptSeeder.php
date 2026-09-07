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
            // ── Ingresos (bonificacion: suman al total de la planilla/boleta) ──
            ['nombre' => 'Remuneración Básica', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null, 'descripcion' => 'NO marcar aplica_a_todos: ya se controla vía Empleado.sueldo_base; este concepto es solo de referencia para la boleta. Activarlo duplicaría el sueldo de todos.'],
            ['nombre' => 'Bonificación por Cargo', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null, 'descripcion' => 'Cada empleado tiene su propio sueldo_base — no se maneja como un monto único por cargo. Se agrega manual, caso por caso.'],
            ['nombre' => 'Asignación Familiar', 'tipo' => 'bonificacion', 'calculo' => 'fijo', 'valor' => 113.00],
            ['nombre' => 'Vacaciones Truncas', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Gratificación Fiestas Patrias y Navidad - Ley 29351 y 30334', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Bonificación Extraordinaria Temporal - Ley 29351 y 30334', 'tipo' => 'bonificacion', 'calculo' => 'porcentaje', 'valor' => 9.00],
            ['nombre' => 'Bonificaciones', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null, 'descripcion' => 'Bonificación general, distinta de la de Cargo y de la Extraordinaria.'],
            ['nombre' => 'Compensación por Tiempo de Servicios', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Otros Conceptos (Ingreso)', 'tipo' => 'bonificacion', 'calculo' => null, 'valor' => null, 'descripcion' => 'Bolsa genérica para un ingreso puntual que no tiene concepto propio todavía.'],
            ['nombre' => 'Bonificación por puntualidad', 'tipo' => 'bonificacion', 'calculo' => 'fijo', 'valor' => 100.00],

            // ── Descuentos (restan del total) ──
            ['nombre' => 'ONP', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 13.00, 'descripcion' => 'Se calcula solo para empleados con sistema_pensiones=ONP — cálculo especial por empleado, no usa aplica_a_todos.'],
            ['nombre' => 'SPP Fondo de Pensiones', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 10.00, 'descripcion' => 'Se calcula solo para empleados con sistema_pensiones=AFP — cálculo especial por empleado, no usa aplica_a_todos.'],
            // Nombres verificados contra la boleta física del colegio: "Prima de Seguro" ahí
            // es la tasa que varía por AFP, y "Comisión" es la tasa fija (1.37% para todas).
            ['nombre' => 'SPP Prima de Seguro', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => null, 'descripcion' => 'Varía según la AFP del empleado (Habitat, Integra, Prima, Profuturo) — cálculo especial, no usa aplica_a_todos.'],
            ['nombre' => 'SPP Comisión', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 1.37, 'descripcion' => 'Cálculo especial por empleado (según su AFP) — no usa aplica_a_todos.'],
            ['nombre' => 'I.R. 5ta Categoría', 'tipo' => 'descuento', 'calculo' => null, 'valor' => null, 'descripcion' => 'Se calcula por tramos progresivos (SUNAT), no como % fijo ni monto fijo — no usa aplica_a_todos.'],
            ['nombre' => 'Descuento Serv. Alimentación', 'tipo' => 'descuento', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Descuento Serv. de Bazar', 'tipo' => 'descuento', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Descuento Autorizado - Diezmo', 'tipo' => 'descuento', 'calculo' => 'porcentaje', 'valor' => 10.00],
            ['nombre' => 'Descuento Otros Conceptos', 'tipo' => 'descuento', 'calculo' => null, 'valor' => null, 'descripcion' => 'Bolsa genérica para un descuento puntual que no tiene concepto propio todavía.'],
            ['nombre' => 'Descuento - Pago Escolaridad Mensual', 'tipo' => 'descuento', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Descuento por tardanza', 'tipo' => 'descuento', 'calculo' => 'fijo', 'valor' => 10.00],
            ['nombre' => 'Descuento por falta', 'tipo' => 'descuento', 'calculo' => 'fijo', 'valor' => 50.00],

            // ── Aportaciones del empleador (informativo: NO afectan el neto del trabajador) ──
            ['nombre' => 'ESSALUD', 'tipo' => 'aportacion', 'calculo' => 'porcentaje', 'valor' => 9.00, 'aplica_a_todos' => true, 'descripcion' => 'Aplica a todos los empleados — cálculo especial por empleado (sueldo + asignación familiar), no pasa por el motor genérico aunque el flag esté en true.'],
            ['nombre' => 'SCTR', 'tipo' => 'aportacion', 'calculo' => null, 'valor' => null, 'descripcion' => 'Varía según la categoría de riesgo y la aseguradora contratada.'],

            // ── Adelanto (resta del neto, aparte del bloque de Descuentos) ──
            ['nombre' => 'Adelanto de Sueldo', 'tipo' => 'adelanto', 'calculo' => null, 'valor' => null],
            ['nombre' => 'Adelanto de Bonificación', 'tipo' => 'adelanto', 'calculo' => null, 'valor' => null],
        ];

        foreach ($concepts as $concept) {
            PaymentConcept::create($concept);
        }
    }
}
