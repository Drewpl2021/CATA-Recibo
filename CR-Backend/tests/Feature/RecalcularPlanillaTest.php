<?php

namespace Tests\Feature;

use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use App\Support\ConceptosDePago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generar la planilla es una foto del sueldo de ese momento. Si RR.HH.
 * corrige el sueldo del empleado DESPUÉS, la planilla se queda con el
 * sueldo viejo — este endpoint es la forma de ponerla al día.
 */
class RecalcularPlanillaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El caso real: ingresó el jueves 24 de septiembre de 2026. Ese mes
     * tiene 22 días hábiles (lunes a viernes) en total, y del 24 al 30 caen
     * 5 (jue 24, vie 25, lun 28, mar 29, mié 30 — sáb 26 y dom 27 no
     * cuentan). La proporción es 5/22, NO 7/30: el denominador también es
     * en días hábiles, para que alguien con el mes completo siga dando 1.0.
     */
    private function planillaDeSeptiembre(float $sueldo): array
    {
        $empleado = $this->crearEmpleado([
            'sueldo_base'   => $sueldo,
            'fecha_ingreso' => '2026-09-24',
        ]);
        $planilla = Planilla::create([
            'empleado_id' => $empleado->id, 'mes' => 9, 'anio' => 2026,
            'sueldo_base' => round($sueldo * 5 / 22, 2), 'total' => 0,
        ]);

        return [$empleado, $planilla];
    }

    public function test_recalcula_el_sueldo_prorrateado_con_el_sueldo_nuevo_de_la_ficha(): void
    {
        $admin = $this->crearUsuario('admin');
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);

        $this->assertSame(181.82, (float) $planilla->sueldo_base, 'sueldo viejo: 800 × 5 hábiles/22 hábiles');

        // RR.HH. le sube el sueldo en la ficha, después de generada la planilla.
        $empleado->update(['sueldo_base' => 1200]);

        $r = $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular");

        $r->assertOk();
        $this->assertSame(272.73, (float) $planilla->fresh()->sueldo_base, '1200 × 5/22');
    }

    /**
     * El caso que se rompía con la fórmula a medias (hábiles arriba, mes de
     * calendario abajo): alguien que trabajó el mes COMPLETO tiene que
     * seguir cobrando su sueldo completo, no un 73%.
     */
    public function test_quien_trabajo_el_mes_completo_cobra_el_sueldo_completo(): void
    {
        $admin = $this->crearUsuario('admin');
        // Sin fecha_ingreso este mes: ya estaba desde antes (usa el default de crearEmpleado, 2020).
        $empleado = $this->crearEmpleado(['sueldo_base' => 800]);
        $planilla = Planilla::create([
            'empleado_id' => $empleado->id, 'mes' => 9, 'anio' => 2026,
            'sueldo_base' => 800, 'total' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular")->assertOk();

        $this->assertSame(800.00, (float) $planilla->fresh()->sueldo_base);
    }

    public function test_regenera_los_conceptos_automaticos_con_el_sueldo_nuevo(): void
    {
        $admin = $this->crearUsuario('admin');
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);
        $empleado->update(['tiene_hijos' => 1, 'sistema_pensiones' => 'ONP']);
        $this->generarConceptosDePrueba($planilla, $empleado);

        $empleado->update(['sueldo_base' => 1200]);
        $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular")->assertOk();

        $planilla->refresh();
        $onp = $planilla->payrollDetalles()->whereHas('paymentConcept', fn ($q) => $q->where('nombre', ConceptosDePago::ONP))->first();

        // Base afecta nueva: 272.73 (sueldo prorrateado) + 113 (asignación) = 385.73; ONP 13% = 50.14.
        $this->assertEqualsWithDelta(50.14, (float) $onp->monto_calculado, 0.01);
    }

    public function test_no_toca_las_lineas_manuales(): void
    {
        $admin = $this->crearUsuario('admin');
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);

        $concepto = \App\Models\PaymentConcept::create(['nombre' => 'Planilla de Movilidad', 'tipo' => 'bonificacion']);
        \App\Models\PayrollDetalle::create([
            'planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id, 'monto_calculado' => 100,
        ]);

        $empleado->update(['sueldo_base' => 1200]);
        $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular")->assertOk();

        $linea = $planilla->payrollDetalles()->whereHas('paymentConcept', fn ($q) => $q->where('nombre', 'Planilla de Movilidad'))->first();
        $this->assertSame(100.00, (float) $linea->monto_calculado, 'la línea manual no cambia con el recálculo');
    }

    public function test_una_corrida_cerrada_no_se_puede_recalcular(): void
    {
        $admin = $this->crearUsuario('admin');
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);

        $corrida = PlanillaCorrida::create(['nombre' => 'TIC', 'mes' => 9, 'anio' => 2026, 'estado' => 'cerrada']);
        $planilla->update(['corrida_id' => $corrida->id]);

        $empleado->update(['sueldo_base' => 1200]);
        $r = $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular");

        $r->assertStatus(422);
        $this->assertSame(181.82, (float) $planilla->fresh()->sueldo_base, 'no se movió nada');
    }

    public function test_un_trabajador_no_puede_recalcular_planillas(): void
    {
        $trabajador = $this->crearUsuario('empleado');
        [, $planilla] = $this->planillaDeSeptiembre(800);

        $this->actingAs($trabajador, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular")->assertForbidden();
    }

    public function test_queda_anotado_en_auditoria_con_el_antes_y_el_despues(): void
    {
        $admin = $this->crearUsuario('admin');
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);
        $this->actingAs($admin);

        $empleado->update(['sueldo_base' => 1200]);
        $this->actingAs($admin, 'sanctum')->putJson("/api/payrolls/{$planilla->id}/recalcular")->assertOk();

        $fila = \App\Models\Auditoria::where('entidad', 'planilla')->where('accion', 'recalculó')->firstOrFail();
        $this->assertEquals(['181.82', '272.73'], $fila->cambios['sueldo_base']);
    }

    public function test_diezmo_se_aplica_por_defecto_a_todos(): void
    {
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);
        $empleado->update(['sistema_pensiones' => null]);
        // Nadie dijo nada en su ficha: lo que manda es el default de la
        // columna (true), no lo que haya quedado en el objeto en memoria.
        $empleado->refresh();
        $this->crearConceptoDiezmo();
        $this->generarConceptosDePrueba($planilla, $empleado);

        $linea = $planilla->payrollDetalles()->whereHas('paymentConcept', fn ($q) => $q->where('nombre', ConceptosDePago::DIEZMO))->first();

        // Sueldo prorrateado 181.82 × 10% = 18.18.
        $this->assertNotNull($linea, 'a nadie que no diga lo contrario se le salta el diezmo');
        $this->assertEqualsWithDelta(18.18, (float) $linea->monto_calculado, 0.01);
    }

    public function test_diezmo_no_se_aplica_a_quien_no_lo_autoriza(): void
    {
        [$empleado, $planilla] = $this->planillaDeSeptiembre(800);
        $empleado->update(['sistema_pensiones' => null, 'aplica_diezmo' => false]);
        $this->crearConceptoDiezmo();
        $this->generarConceptosDePrueba($planilla, $empleado);

        $linea = $planilla->payrollDetalles()->whereHas('paymentConcept', fn ($q) => $q->where('nombre', ConceptosDePago::DIEZMO))->first();

        $this->assertNull($linea, 'con aplica_diezmo=false no se le crea la línea');
    }

    private function crearConceptoDiezmo(): void
    {
        \App\Models\PaymentConcept::create([
            'nombre' => ConceptosDePago::DIEZMO, 'tipo' => 'descuento',
            'calculo' => 'porcentaje', 'valor' => 10.00, 'aplica_a_todos' => true,
        ]);
    }

    private function generarConceptosDePrueba($planilla, $empleado): void
    {
        \App\Models\PaymentConcept::create(['nombre' => ConceptosDePago::ASIGNACION_FAMILIAR, 'tipo' => 'bonificacion']);
        \App\Models\PaymentConcept::create(['nombre' => ConceptosDePago::ONP, 'tipo' => 'descuento']);
        \App\Models\PaymentConcept::create(['nombre' => ConceptosDePago::ESSALUD, 'tipo' => 'aportacion']);
        // Se generan con el mismo motor que usa la corrida real.
        $motor = new class {
            use \App\Traits\CalculaConceptosPlanilla;
            public function generar($planilla, $empleado) { $this->generarConceptosAutomaticos($planilla, $empleado); }
        };
        $motor->generar($planilla, $empleado);
    }
}
