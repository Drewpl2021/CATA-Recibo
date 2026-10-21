<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Support\Gratificacion\MotorGratificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El módulo «Gratificaciones» y las reglas de la gratificación (Ley 27735). */
class GratificacionModuloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::GRATIFICACION], ['tipo' => 'bonificacion']);
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::BONIF_EXTRAORDINARIA], ['tipo' => 'bonificacion']);
    }

    private function calcular($empleado, string $periodo = 'julio', int $anio = 2026): array
    {
        return (new MotorGratificacion())->calcular($empleado->fresh(), $anio, $periodo);
    }

    public function test_un_semestre_completo_con_bonificacion_por_cargo_y_el_9_por_ciento(): void
    {
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 3000, 'bonificacion_cargo' => 300, 'tiene_hijos' => 0]);

        $r = $this->calcular($e);

        $this->assertSame(6, $r['meses']);
        // La bonificación por cargo es fija: entra.
        $this->assertEqualsWithDelta(3300, $r['gratificacion'], 0.001);
        $this->assertEqualsWithDelta(297, $r['bonificacion_extraordinaria'], 0.001);
        $this->assertEqualsWithDelta(3597, $r['total'], 0.001);
        $this->assertSame(MotorGratificacion::POR_PAGAR, $r['estado']);
    }

    public function test_solo_cuentan_los_meses_completos(): void
    {
        // Entró un miércoles 15: marzo no cuenta → abril, mayo y junio.
        $e = $this->crearEmpleado(['fecha_ingreso' => '2026-04-15', 'sueldo_base' => 3000, 'tiene_hijos' => 0]);
        $this->assertSame(2, $this->calcular($e)['meses']);
        $this->assertEqualsWithDelta(1000, $this->calcular($e)['gratificacion'], 0.001);
    }

    public function test_entrar_el_primer_dia_habil_cuenta_el_mes_entero(): void
    {
        // El 1 de marzo de 2026 fue domingo: entrar el lunes 2 completa marzo (como el Excel de RR.HH.).
        $e = $this->crearEmpleado(['fecha_ingreso' => '2026-03-02', 'sueldo_base' => 3000, 'tiene_hijos' => 0,
            'tipo_contrato_id' => $this->idTipoContrato('Contratado')]);

        $this->assertSame(4, $this->calcular($e)['meses']);
    }

    public function test_practicas_y_contrato_parcial_no_generan_gratificacion(): void
    {
        foreach (['Prácticas', 'Contrato/Parcial'] as $tipo) {
            $e = $this->crearEmpleado(['tipo_contrato_id' => $this->idTipoContrato($tipo)]);
            $r = $this->calcular($e);

            $this->assertFalse($r['aplica'], $tipo);
            $this->assertSame(MotorGratificacion::NO_CORRESPONDE, $r['estado']);
            $this->assertStringContainsString('no genera gratificación', $r['motivo']);
        }
    }

    public function test_quien_cesa_a_mitad_de_semestre_cobra_la_trunca_en_el_mes_de_su_cese(): void
    {
        // Cesó el miércoles 20 de mayo: enero a abril completos.
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 3000, 'tiene_hijos' => 0,
            'estado' => 'inactivo', 'fecha_cese' => '2026-05-20']);

        $r = $this->calcular($e);

        $this->assertTrue($r['trunca']);
        $this->assertSame(4, $r['meses']);
        $this->assertSame(5, $r['mes_planilla']);
        $this->assertEqualsWithDelta(2000, $r['gratificacion'], 0.001);
    }

    public function test_la_planilla_de_julio_paga_la_gratificacion_con_la_bonificacion_por_cargo(): void
    {
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 3000, 'bonificacion_cargo' => 300, 'tiene_hijos' => 0]);
        PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::BONIFICACION_CARGO], ['tipo' => 'bonificacion']);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/payroll-runs', ['nombre' => 'Planilla General', 'mes' => 7, 'anio' => 2026, 'generar' => true])
            ->assertCreated();

        $planilla = Planilla::where('empleado_id', $e->id)->where('mes', 7)->firstOrFail();
        $grati = PayrollDetalle::where('planilla_id', $planilla->id)
            ->whereHas('paymentConcept', fn ($q) => $q->where('nombre', ConceptosDePago::GRATIFICACION))->value('monto_calculado');
        $this->assertEqualsWithDelta(3300, (float) $grati, 0.001);

        // Y el módulo la ve pagada.
        $this->assertSame(MotorGratificacion::PAGADA, $this->calcular($e)['estado']);
    }

    public function test_si_la_planilla_pago_otro_monto_se_marca_la_diferencia(): void
    {
        $e = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 3000, 'tiene_hijos' => 0]);
        $planilla = Planilla::create(['empleado_id' => $e->id, 'mes' => 7, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 0]);
        $concepto = PaymentConcept::where('nombre', ConceptosDePago::GRATIFICACION)->first();
        PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id, 'monto_calculado' => 2500]);

        $r = $this->calcular($e);

        $this->assertSame(MotorGratificacion::DIFERENCIA, $r['estado']);
        $this->assertEqualsWithDelta(2500 - 3270, $r['diferencia'], 0.001);
    }

    public function test_la_lista_la_hoja_y_la_entrega_al_expediente(): void
    {
        Storage::fake('local');
        $rrhh = $this->crearUsuario('rrhh');
        $pagada = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 3000, 'tiene_hijos' => 0]);
        $porPagar = $this->crearEmpleado(['fecha_ingreso' => '2020-03-01', 'sueldo_base' => 2000, 'tiene_hijos' => 0]);
        $planilla = Planilla::create(['empleado_id' => $pagada->id, 'mes' => 7, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 0]);
        foreach ([ConceptosDePago::GRATIFICACION => 3000, ConceptosDePago::BONIF_EXTRAORDINARIA => 270] as $nombre => $monto) {
            PayrollDetalle::create(['planilla_id' => $planilla->id, 'payment_concept_id' => PaymentConcept::where('nombre', $nombre)->value('id'), 'monto_calculado' => $monto]);
        }

        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/bonuses?anio=2026&periodo=julio')->assertOk();
        $this->assertSame(1, $lista->json('data.resumen.pagadas'));
        $this->assertSame(1, $lista->json('data.resumen.por_pagar'));
        $this->assertSame('2026-07-15', $lista->json('data.limite'));

        $this->actingAs($rrhh, 'sanctum')->get("/api/bonuses/{$pagada->id}/sheet?anio=2026&periodo=julio")->assertOk();

        $entrega = $this->actingAs($rrhh, 'sanctum')
            ->postJson('/api/bonuses/deliver', ['anio' => 2026, 'periodo' => 'julio', 'ids' => [$pagada->id, $porPagar->id]])
            ->assertOk();
        $this->assertSame(1, $entrega->json('data.entregadas'));
        $this->assertCount(1, $entrega->json('data.saltadas'));
        $this->assertTrue(Documento::where('empleado_id', $pagada->id)->where('tipo', 'gratificacion')->exists());
    }

    public function test_un_trabajador_no_ve_el_modulo(): void
    {
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->getJson('/api/bonuses')->assertForbidden();
    }
}
