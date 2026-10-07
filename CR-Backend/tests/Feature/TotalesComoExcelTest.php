<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los totales de varias planillas se sacan como el Excel del PLAME: se suman
 * los netos con todos sus decimales y se redondea al final.
 */
class TotalesComoExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_total_suma_los_netos_exactos_y_redondea_al_final(): void
    {
        $descuento = PaymentConcept::firstOrCreate(['nombre' => 'Descuento de prueba'], ['tipo' => 'descuento']);

        // Dos netos de 999.996: cada uno se paga 1,000.00, pero juntos son
        // 1,999.992 → 1,999.99 (el Excel), no 2,000.00 (sumando redondeados).
        foreach ([1, 2] as $_) {
            $e = $this->crearEmpleado();
            $p = Planilla::create(['empleado_id' => $e->id, 'mes' => 3, 'anio' => 2026, 'sueldo_base' => 1000, 'total' => 0]);
            PayrollDetalle::create(['planilla_id' => $p->id, 'payment_concept_id' => $descuento->id, 'monto_calculado' => 0.004]);
            $p->recalcularTotal();
            $this->assertEqualsWithDelta(1000.00, (float) $p->fresh()->total, 0.0001);
        }

        $this->assertSame(1999.99, Planilla::sumaDeNetos(Planilla::where('mes', 3)));

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/payrolls?mes=3&anio=2026&page=0&size=10')
            ->assertOk()
            ->assertJsonPath('data.masaSalarial', 1999.99);
    }
}
