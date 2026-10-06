<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuando se acaba un contrato y nadie hace nada: que no se pague un mes de
 * más, que se avise, y que la baja quede en la fecha real.
 */
class ContratoVencidoTest extends TestCase
{
    use RefreshDatabase;

    private function contratadoHasta(string $fin, array $datos = [])
    {
        $e = $this->crearEmpleado(array_merge(['tipo_contrato_id' => $this->idTipoContrato('Contratado'), 'fecha_cese' => $fin], $datos));
        Contrato::create(['empleado_id' => $e->id, 'tipo_contrato_id' => $this->idTipoContrato('Contratado'),
            'fecha_inicio' => '2020-03-01', 'fecha_fin' => $fin, 'estado' => 'vigente']);

        return $e;
    }

    public function test_la_baja_queda_en_la_fecha_elegida_o_en_su_fin_de_contrato(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $vencido = $this->contratadoHasta(now()->subDays(10)->toDateString());
        $otro = $this->crearEmpleado();

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', [
            'ids' => [$vencido->id, $otro->id], 'estado' => 'inactivo',
            'fecha_cese' => now()->subDays(2)->toDateString(), 'usar_fin_de_contrato' => true,
        ])->assertOk();

        // Al vencido, en su fin de contrato; al otro, en la fecha elegida.
        $this->assertSame(now()->subDays(10)->toDateString(), substr((string) $vencido->fresh()->fecha_cese, 0, 10));
        $this->assertSame(now()->subDays(2)->toDateString(), substr((string) $otro->fresh()->fecha_cese, 0, 10));

        // Una fecha que todavía no llega, no.
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', [
            'ids' => [$this->crearEmpleado()->id], 'estado' => 'inactivo', 'fecha_cese' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_el_filtro_y_el_panel_cuentan_a_los_de_contrato_vencido(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $this->contratadoHasta(now()->subDay()->toDateString());
        $this->contratadoHasta(now()->addMonth()->toDateString());
        $this->crearEmpleado();

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/employees?contrato_vencido=1&page=0&size=10')
            ->assertOk()->assertJsonPath('data.totalElements', 1);
    }

    public function test_cada_noche_se_da_de_baja_a_los_vencidos_en_su_fecha_de_fin(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $fin = now()->subDays(20)->toDateString();
        $vencido = $this->contratadoHasta($fin);
        $vigente = $this->contratadoHasta(now()->addMonth()->toDateString());
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => (string) $vencido->id])->save();

        $this->artisan('contratos:dar-de-baja-vencidos')->assertSuccessful();

        $vencido = $vencido->fresh();
        $this->assertSame('inactivo', $vencido->estado);
        $this->assertSame($fin, substr((string) $vencido->fecha_cese, 0, 10));
        $this->assertSame('inactivo', $cuenta->fresh()->estado_registro);
        $this->assertSame('activo', $vigente->fresh()->estado);
        $this->assertTrue(\App\Models\Notificacion::where('user_id', $rrhh->id)->where('tipo', 'contratos_vencidos')->exists());

        // Se renueva y se reactiva: vuelve a estar activo con su contrato nuevo.
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/contracts', [
            'empleado_id' => $vencido->id, 'tipo_contrato_id' => $this->idTipoContrato('Contratado'),
            'fecha_inicio' => now()->subDays(19)->toDateString(), 'fecha_fin' => now()->addYear()->toDateString(),
        ])->assertCreated();
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', ['ids' => [$vencido->id], 'estado' => 'activo'])->assertOk();
        $this->artisan('contratos:dar-de-baja-vencidos')->assertSuccessful();
        $this->assertSame('activo', $vencido->fresh()->estado);
        $this->assertSame(now()->addYear()->toDateString(), substr((string) $vencido->fresh()->fecha_cese, 0, 10));
    }

    public function test_renovar_desde_contratos_quita_el_vencido(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $e = $this->contratadoHasta(now()->subDays(5)->toDateString());
        $this->assertTrue($e->fresh()->contratoVencidoAntesDe(now()->toDateString()));

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/contracts', [
            'empleado_id' => $e->id, 'tipo_contrato_id' => $this->idTipoContrato('Contratado'),
            'fecha_inicio' => now()->subDays(4)->toDateString(), 'fecha_fin' => now()->addYear()->toDateString(),
        ])->assertCreated();

        $e = $e->fresh();
        $this->assertSame(now()->addYear()->toDateString(), substr((string) $e->fecha_cese, 0, 10));
        $this->assertFalse($e->contratoVencidoAntesDe(now()->toDateString()));
    }

    public function test_al_generar_no_se_le_arma_sola_pero_se_puede_incluir(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $anio = (int) now()->year;
        $mes = (int) now()->month;
        $vencido = $this->contratadoHasta(now()->startOfMonth()->subDay()->toDateString(), ['sueldo_base' => 2000]);
        $vigente = $this->crearEmpleado(['sueldo_base' => 2000]);

        $r = $this->actingAs($rrhh, 'sanctum')->postJson('/api/payroll-runs', [
            'nombre' => 'Planilla del mes', 'mes' => $mes, 'anio' => $anio, 'generar' => true,
        ])->assertCreated();

        $this->assertTrue(Planilla::where('empleado_id', $vigente->id)->exists());
        $this->assertFalse(Planilla::where('empleado_id', $vencido->id)->exists());
        $omitido = collect($r->json('data.detalle'))->firstWhere('empleado_id', $vencido->id);
        $this->assertTrue($omitido['contrato_vencido']);

        // "Incluirlos igual".
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/payroll-runs/' . $r->json('data.corrida.id') . '/generate', [
            'empleado_ids' => [$vencido->id], 'incluir_contrato_vencido' => true,
        ])->assertOk();
        $this->assertTrue(Planilla::where('empleado_id', $vencido->id)->exists());
    }
}
