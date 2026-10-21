<?php

namespace Tests\Feature;

use App\Models\CtsLiquidacion;
use App\Models\Documento;
use App\Models\PaymentConcept;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use App\Support\ConceptosDePago;
use App\Support\Cts\MotorCts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El módulo «CTS»: el cálculo del semestre, el depósito con su hoja en el expediente y lo que no genera CTS. */
class CtsModuloTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_tiempo_cuenta_meses_completos_y_dias_sueltos(): void
    {
        $t = fn (string $a, string $b) => MotorCts::tiempo(Carbon::parse($a), Carbon::parse($b));

        $this->assertSame(['meses' => 6, 'dias' => 0], $t('2025-11-01', '2026-04-30'));
        $this->assertSame(['meses' => 3, 'dias' => 17], $t('2026-01-15', '2026-04-30'));
        $this->assertSame(['meses' => 4, 'dias' => 20], $t('2026-05-01', '2026-09-20'));
    }

    public function test_un_semestre_completo_suma_el_sexto_de_la_gratificacion(): void
    {
        // Sin planilla de diciembre: la gratificación se calcula (3000 por el semestre entero).
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);

        $r = (new MotorCts())->calcular($e, 2026, 'mayo');

        $this->assertTrue($r['aplica']);
        $this->assertSame('calculada', $r['gratificacion_fuente']);
        $this->assertEqualsWithDelta(500, $r['sexto_gratificacion'], 0.001);
        $this->assertEqualsWithDelta(3500, $r['remuneracion_computable'], 0.001);
        $this->assertSame(6, $r['meses']);
        $this->assertEqualsWithDelta(1750, $r['monto'], 0.001);
    }

    public function test_la_remuneracion_sale_de_la_planilla_del_mes_de_cierre(): void
    {
        // La ficha dice 3000, pero en octubre se le pagó 3200 con asignación y bonificación.
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);
        $octubre = Planilla::create(['empleado_id' => $e->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3200, 'total' => 0]);
        $concepto = fn (string $nombre) => PaymentConcept::firstOrCreate(['nombre' => $nombre], ['tipo' => 'bonificacion'])->id;
        PayrollDetalle::create(['planilla_id' => $octubre->id, 'payment_concept_id' => $concepto(ConceptosDePago::ASIGNACION_FAMILIAR), 'monto_calculado' => 113]);
        PayrollDetalle::create(['planilla_id' => $octubre->id, 'payment_concept_id' => $concepto(ConceptosDePago::BONIFICACION_CARGO), 'monto_calculado' => 100]);

        $r = (new MotorCts())->calcular($e, 2026, 'noviembre');

        $this->assertSame('planilla', $r['remuneracion_fuente']);
        $this->assertEqualsWithDelta(3200, $r['sueldo'], 0.001);
        $this->assertEqualsWithDelta(113, $r['asignacion_familiar'], 0.001);
        $this->assertEqualsWithDelta(100, $r['bonificacion_cargo'], 0.001);
        // Julio no pasó por el sistema: la gratificación se calcula con la ficha (3000 ÷ 6 = 500).
        $this->assertEqualsWithDelta(3200 + 113 + 100 + 500, $r['remuneracion_computable'], 0.001);
        $this->assertEqualsWithDelta(3913 / 2, $r['monto'], 0.01);
    }

    public function test_el_historial_trae_los_seis_meses_con_sus_marcas(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2026-06-10', 'tiene_hijos' => 0]);
        $octubre = Planilla::create(['empleado_id' => $e->id, 'mes' => 10, 'anio' => 2026, 'sueldo_base' => 3000, 'total' => 2610]);
        $onp = PaymentConcept::firstOrCreate(['nombre' => ConceptosDePago::ONP], ['tipo' => 'descuento'])->id;
        PayrollDetalle::create(['planilla_id' => $octubre->id, 'payment_concept_id' => $onp, 'monto_calculado' => 390]);

        $h = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson("/api/cts/{$e->id}/history?anio=2026&periodo=noviembre")
            ->assertOk()->json('data');

        $this->assertCount(6, $h['meses']);
        $porMes = collect($h['meses'])->keyBy('mes');
        $this->assertSame('no_trabajaba', $porMes[5]['situacion']);
        $this->assertSame('sin_planilla', $porMes[8]['situacion']);
        $this->assertTrue($porMes[7]['es_gratificacion']);
        $this->assertTrue($porMes[10]['es_cierre']);
        $this->assertSame('planilla', $porMes[10]['situacion']);
        $this->assertEqualsWithDelta(3000, $porMes[10]['ingresos'], 0.001);
        $this->assertEqualsWithDelta(390, $porMes[10]['descuentos'], 0.001);
        $this->assertEqualsWithDelta(2610, $porMes[10]['neto'], 0.001);
    }

    public function test_sin_planilla_del_cierre_se_toma_la_ficha(): void
    {
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 50]);

        $r = (new MotorCts())->calcular($e, 2026, 'noviembre');

        $this->assertSame('ficha', $r['remuneracion_fuente']);
        $this->assertEqualsWithDelta(3000, $r['sueldo'], 0.001);
        $this->assertEqualsWithDelta(50, $r['bonificacion_cargo'], 0.001);
    }

    public function test_quien_ingreso_a_mitad_del_semestre_cobra_por_meses_y_dias(): void
    {
        // Ingresó el 15 de enero: no tuvo gratificación de diciembre, 3 meses y 17 días.
        $e = $this->crearEmpleado(['sueldo_base' => 3600, 'fecha_ingreso' => '2026-01-15', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0]);

        $r = (new MotorCts())->calcular($e, 2026, 'mayo');

        $this->assertSame(3, $r['meses']);
        $this->assertSame(17, $r['dias']);
        $this->assertEqualsWithDelta(0, $r['sexto_gratificacion'], 0.001);
        $this->assertEqualsWithDelta(3600 / 12 * 3 + 3600 / 360 * 17, $r['monto'], 0.01);
    }

    public function test_las_practicas_no_generan_cts(): void
    {
        $e = $this->crearEmpleado(['tipo_contrato_id' => $this->idTipoContrato('Prácticas')]);

        $r = (new MotorCts())->calcular($e, 2026, 'mayo');

        $this->assertFalse($r['aplica']);
        $this->assertStringContainsString('no genera CTS', $r['motivo']);
    }

    public function test_calcular_depositar_y_anular_desde_la_api(): void
    {
        Storage::fake('local');
        $e = $this->crearEmpleado(['sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0,
            'entidad_financiera' => 'Caja Arequipa', 'numero_cuenta' => '123-456']);
        $practicante = $this->crearEmpleado(['tipo_contrato_id' => $this->idTipoContrato('Prácticas')]);
        $rrhh = $this->crearUsuario('rrhh');

        // Antes de calcular: la vista previa, sin nada guardado.
        $lista = $this->actingAs($rrhh, 'sanctum')->getJson('/api/cts?anio=2026&periodo=mayo')->assertOk();
        $this->assertSame('2026-05-15', $lista->json('data.limite'));
        $filas = collect($lista->json('data.filas'));
        $this->assertSame('por_calcular', $filas->firstWhere('id', $e->id)['estado_cts']);
        $this->assertSame('sin_derecho', $filas->firstWhere('id', $practicante->id)['estado_cts']);

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])
            ->assertOk()->assertJsonPath('data.calculadas', 1);

        $l = CtsLiquidacion::where('empleado_id', $e->id)->firstOrFail();
        $this->assertEqualsWithDelta(1750, $l->monto, 0.001);

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12'])
            ->assertOk()->assertJsonPath('data.depositadas', 1);

        $l->refresh();
        $this->assertTrue($l->depositada());
        $this->assertSame('123-456', $l->numero_cuenta);
        $documento = Documento::findOrFail($l->documento_id);
        $this->assertSame('cts', $documento->tipo);
        Storage::disk('local')->assertExists($documento->archivo);

        // Una depositada no se vuelve a calcular aunque le cambien el sueldo.
        $e->update(['sueldo_base' => 9000]);
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])
            ->assertOk()->assertJsonPath('data.depositadas', 1);
        $this->assertEqualsWithDelta(1750, $l->refresh()->monto, 0.001);

        // No se deposita dos veces.
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-13'])
            ->assertStatus(422);

        $this->actingAs($rrhh, 'sanctum')->getJson("/api/cts/{$l->id}/sheet")->assertOk();

        $this->actingAs($rrhh, 'sanctum')->deleteJson("/api/cts/{$l->id}/deposit")->assertOk();
        $this->assertSame(CtsLiquidacion::CALCULADA, $l->refresh()->estado);
        $this->assertSame('inactivo', $documento->refresh()->estado_registro);
    }

    public function test_un_trabajador_no_entra_al_modulo(): void
    {
        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')->getJson('/api/cts')->assertForbidden();
    }
}
