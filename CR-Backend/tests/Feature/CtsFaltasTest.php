<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CtsInasistencia;
use App\Models\CtsLiquidacion;
use App\Models\PaymentConcept;
use App\Models\Planilla;
use App\Models\PayrollDetalle;
use App\Support\ConceptosDePago;
use App\Support\Cts\MotorCts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Las faltas injustificadas le quitan días a la CTS; las tardanzas no. */
class CtsFaltasTest extends TestCase
{
    use RefreshDatabase;

    private function trabajador()
    {
        // Semestre de mayo sin planilla de diciembre: RC = 3000 + 3000/6 = 3500.
        return $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0,
            'cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '105-0012345-6']);
    }

    private function anotar(string $empleadoId, int $dias, string $periodo = 'mayo')
    {
        return $this->putJson('/api/cts/absences', ['empleado_id' => $empleadoId, 'anio' => 2026, 'periodo' => $periodo, 'dias' => $dias, 'observacion' => 'Control de asistencia']);
    }

    public function test_dos_faltas_dejan_cinco_meses_y_veintiocho_dias_como_la_hoja_con_descuento(): void
    {
        $e = $this->trabajador();
        CtsInasistencia::create(['empleado_id' => $e->id, 'anio' => 2026, 'periodo' => 'mayo', 'dias' => 2]);

        $r = (new MotorCts())->calcular($e, 2026, 'mayo');

        $this->assertSame(2, $r['faltas']);
        $this->assertSame(5, $r['meses']);
        $this->assertSame(28, $r['dias']);
        // 3500 / 12 × 5 + 3500 / 360 × 28 = 1750 − 2 × 3500/360
        $this->assertEqualsWithDelta(1730.56, $r['monto'], 0.001);
    }

    public function test_las_faltas_no_le_quitan_mas_de_lo_que_trabajo(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2026-04-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);

        $r = (new MotorCts())->calcular($e, 2026, 'mayo', 45);

        $this->assertSame(30, $r['faltas']);
        $this->assertSame(0, $r['meses'] * 30 + $r['dias']);
        $this->assertEqualsWithDelta(0, $r['monto'], 0.001);
    }

    public function test_al_anotar_las_faltas_la_cts_calculada_se_recalcula_al_momento(): void
    {
        $e = $this->trabajador();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');
        $this->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();

        $this->anotar($e->id, 2)->assertOk()->assertJsonPath('data.meses', 5)->assertJsonPath('data.dias', 28);

        $l = CtsLiquidacion::where('empleado_id', $e->id)->firstOrFail();
        $this->assertSame(2, $l->faltas);
        $this->assertEqualsWithDelta(1730.56, $l->monto, 0.001);

        $fila = collect($this->getJson('/api/cts?anio=2026&periodo=mayo')->json('data.filas'))->firstWhere('id', $e->id);
        $this->assertSame(2, $fila['faltas']);
        $this->assertSame(2, $fila['faltas_registradas']);
        $this->assertSame('Control de asistencia', $fila['faltas_observacion']);
        $this->assertFalse($fila['desactualizada'], 'Cuadra al instante: no hace falta volver a calcular.');

        $this->assertTrue(Auditoria::where('entidad', 'cts')->where('descripcion', 'like', '%días no laborados%de 0 a 2%')->exists());
    }

    public function test_la_lista_de_rrhh_se_guarda_de_una_vez_y_salta_las_depositadas(): void
    {
        Storage::fake('local');
        $a = $this->trabajador();
        $b = $this->trabajador();
        $c = $this->trabajador();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');
        $this->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();
        $depositada = CtsLiquidacion::where('empleado_id', $c->id)->firstOrFail();
        $this->postJson('/api/cts/deposit', ['ids' => [$depositada->id], 'fecha_deposito' => '2026-05-12'])->assertOk();

        $r = $this->putJson('/api/cts/absences/batch', ['anio' => 2026, 'periodo' => 'mayo', 'faltas' => [
            ['empleado_id' => $a->id, 'dias' => 2, 'observacion' => 'Asistencia de enero'],
            ['empleado_id' => $b->id, 'dias' => 1],
            ['empleado_id' => $c->id, 'dias' => 4],
        ]])->assertOk();

        $this->assertSame(2, $r->json('data.cambiadas'));
        $this->assertCount(1, $r->json('data.omitidas'));
        $this->assertSame(2, CtsLiquidacion::where('empleado_id', $a->id)->value('faltas'));
        $this->assertSame(1, CtsLiquidacion::where('empleado_id', $b->id)->value('faltas'));
        $this->assertSame(0, (int) $depositada->refresh()->faltas, 'Lo depositado no se toca.');
        $this->assertSame(0, CtsInasistencia::where('empleado_id', $c->id)->count());
        $this->assertEqualsWithDelta(1730.56 + 1740.28 + 1750, $r->json('data.total'), 0.01);
        $this->assertTrue(Auditoria::where('entidad', 'cts')->where('descripcion', 'like', '%de 2 trabajador(es)%')->exists());
    }

    public function test_la_lista_rechaza_repetidos_y_dias_imposibles_sin_guardar_nada(): void
    {
        $a = $this->trabajador();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->putJson('/api/cts/absences/batch', ['anio' => 2026, 'periodo' => 'mayo', 'faltas' => [
            ['empleado_id' => $a->id, 'dias' => 2], ['empleado_id' => $a->id, 'dias' => 3],
        ]])->assertStatus(422);
        $this->putJson('/api/cts/absences/batch', ['anio' => 2026, 'periodo' => 'mayo', 'faltas' => [
            ['empleado_id' => $a->id, 'dias' => 200],
        ]])->assertStatus(422);

        $this->assertSame(0, CtsInasistencia::count());
    }

    public function test_con_cero_se_borra_el_registro(): void
    {
        $e = $this->trabajador();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->anotar($e->id, 3)->assertOk();
        $this->anotar($e->id, 0)->assertOk()->assertJsonPath('data.meses', 6);

        $this->assertSame(0, CtsInasistencia::count());
    }

    public function test_no_se_cambian_las_faltas_de_una_cts_depositada(): void
    {
        Storage::fake('local');
        $e = $this->trabajador();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');
        $this->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();
        $l = CtsLiquidacion::where('empleado_id', $e->id)->firstOrFail();
        $this->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12'])->assertOk();

        $r = $this->anotar($e->id, 2)->assertStatus(422);

        $this->assertStringContainsString('ya está depositada', $r->json('message'));
        $this->assertSame(0, CtsInasistencia::count());
    }

    public function test_se_validan_los_dias_y_solo_entra_quien_lleva_cts(): void
    {
        $e = $this->trabajador();

        $this->actingAs($this->crearUsuario('empleado'), 'sanctum');
        $this->anotar($e->id, 2)->assertForbidden();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');
        $this->anotar($e->id, 181)->assertStatus(422)->assertJsonValidationErrors('dias');
        $this->putJson('/api/cts/absences', ['empleado_id' => $e->id, 'anio' => 2026, 'periodo' => 'mayo', 'dias' => -1])
            ->assertStatus(422)->assertJsonValidationErrors('dias');
        $this->putJson('/api/cts/absences', ['empleado_id' => $e->id, 'anio' => 2026, 'periodo' => 'enero', 'dias' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('periodo');
    }

    public function test_la_lista_trae_como_pista_lo_descontado_por_tardanzas_y_faltas_en_el_semestre(): void
    {
        $e = $this->trabajador();
        $concepto = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::TARDANZAS_Y_FALTAS], ['tipo' => 'descuento']);
        foreach ([[2026, 1, 20.50], [2026, 3, 10.25], [2026, 6, 99]] as [$anio, $mes, $monto]) {
            $p = Planilla::create(['empleado_id' => $e->id, 'mes' => $mes, 'anio' => $anio, 'sueldo_base' => 3000, 'total' => 3000]);
            PayrollDetalle::create(['planilla_id' => $p->id, 'payment_concept_id' => $concepto->id, 'monto_calculado' => $monto]);
        }

        $fila = collect($this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson('/api/cts?anio=2026&periodo=mayo')->assertOk()->json('data.filas'))->firstWhere('id', $e->id);

        // Enero y marzo están en el semestre de mayo (noviembre a abril); junio no.
        $this->assertEqualsWithDelta(30.75, $fila['tardanzas_soles'], 0.001);
        $this->assertSame(6, $fila['meses'], 'El descuento en soles no le quita días: solo las faltas anotadas.');
    }
}
