<?php

namespace Tests\Feature;

use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Vacaciones Truncas" es la vía de pago de plazo fijo, suplencia y
 * prácticas: quien tiene contrato indeterminado ya cobra sus vacaciones de
 * verdad (días de descanso), así que este concepto no debe poder aplicársele.
 */
class VacacionesTruncasTest extends TestCase
{
    use RefreshDatabase;

    private function planillaDe(string $tipoContrato): Planilla
    {
        $empleado = $this->crearEmpleado(['tipo_contrato' => $tipoContrato]);

        return Planilla::create([
            'empleado_id' => $empleado->id, 'mes' => 7, 'anio' => 2026,
            'sueldo_base' => 3000, 'total' => 3000,
        ]);
    }

    private function conceptoTruncas(): PaymentConcept
    {
        return PaymentConcept::create([
            'nombre' => ConceptosDePago::VACACIONES_TRUNCAS,
            'tipo'   => 'bonificacion',
        ]);
    }

    public function test_se_rechaza_para_un_contrato_indeterminado(): void
    {
        $admin = $this->crearUsuario('admin');
        $planilla = $this->planillaDe('indeterminado');
        $concepto = $this->conceptoTruncas();

        $r = $this->actingAs($admin, 'sanctum')->postJson('/api/payroll-details', [
            'planilla_id'        => $planilla->id,
            'payment_concept_id' => $concepto->id,
            'monto_calculado'    => 250,
        ]);

        $r->assertStatus(422);
        $this->assertSame(0, $planilla->payrollDetalles()->count());
    }

    #[DataProvider('tiposConDerecho')]
    public function test_se_acepta_para_quien_no_es_indeterminado(string $tipoContrato): void
    {
        $admin = $this->crearUsuario('admin');
        $planilla = $this->planillaDe($tipoContrato);
        $concepto = $this->conceptoTruncas();

        $r = $this->actingAs($admin, 'sanctum')->postJson('/api/payroll-details', [
            'planilla_id'        => $planilla->id,
            'payment_concept_id' => $concepto->id,
            'monto_calculado'    => 250,
        ]);

        $r->assertCreated();
        $this->assertSame(1, $planilla->payrollDetalles()->count());
    }

    public static function tiposConDerecho(): array
    {
        return [
            'plazo fijo' => ['plazo_fijo'],
            'suplencia'  => ['suplencia'],
            'prácticas'  => ['practicas'],
        ];
    }

    public function test_otros_conceptos_siguen_libres_para_un_indeterminado(): void
    {
        $admin = $this->crearUsuario('admin');
        $planilla = $this->planillaDe('indeterminado');
        $concepto = PaymentConcept::create(['nombre' => 'Bono de alimentación', 'tipo' => 'bonificacion']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/payroll-details', [
            'planilla_id'        => $planilla->id,
            'payment_concept_id' => $concepto->id,
            'monto_calculado'    => 100,
        ])->assertCreated();
    }
}
