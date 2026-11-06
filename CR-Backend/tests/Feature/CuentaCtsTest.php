<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\CtsLiquidacion;
use App\Support\ColumnasDeEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** La CTS se deposita en su propia cuenta, nunca en la del sueldo. */
class CuentaCtsTest extends TestCase
{
    use RefreshDatabase;

    private function conCts(array $datos = []): array
    {
        $e = $this->crearEmpleado([
            'sueldo_base' => 3000, 'fecha_ingreso' => '2020-03-01', 'tiene_hijos' => 0, 'bonificacion_cargo' => 0,
            'entidad_financiera' => 'BCP', 'numero_cuenta' => '19100000000011',
            ...$datos,
        ]);
        $rrhh = $this->crearUsuario('rrhh');
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/calculate', ['anio' => 2026, 'periodo' => 'mayo'])->assertOk();

        return [$e, CtsLiquidacion::where('empleado_id', $e->id)->firstOrFail(), $rrhh];
    }

    public function test_la_ficha_guarda_la_cuenta_cts_y_queda_en_la_auditoria(): void
    {
        $e = $this->crearEmpleado();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$e->id}", ['cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '105-0012345-6'])
            ->assertOk();

        $e->refresh();
        $this->assertSame('Caja Arequipa', $e->cts_entidad_financiera);
        $this->assertSame('105-0012345-6', $e->cts_numero_cuenta);
        $cambio = Auditoria::where('entidad_id', $e->id)->where('accion', 'cambió')->latest('id')->firstOrFail();
        $this->assertStringContainsString('cuenta CTS', json_encode($cambio->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_una_cuenta_cts_mal_escrita_o_sin_entidad_no_se_guarda(): void
    {
        $e = $this->crearEmpleado();
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum');

        $this->putJson("/api/employees/{$e->id}", ['cts_entidad_financiera' => 'BCP', 'cts_numero_cuenta' => 'ABC 123456'])
            ->assertStatus(422)->assertJsonValidationErrors('cts_numero_cuenta');
        $this->putJson("/api/employees/{$e->id}", ['cts_entidad_financiera' => 'BCP', 'cts_numero_cuenta' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors('cts_numero_cuenta');
        $this->putJson("/api/employees/{$e->id}", ['cts_numero_cuenta' => '1050012345'])
            ->assertStatus(422)->assertJsonValidationErrors('cts_entidad_financiera');

        $this->assertNull($e->fresh()->cts_numero_cuenta);
    }

    public function test_el_deposito_va_a_la_cuenta_cts_y_no_a_la_del_sueldo(): void
    {
        Storage::fake('local');
        [$e, $l, $rrhh] = $this->conCts(['cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '105-0012345-6']);

        $fila = collect($this->actingAs($rrhh, 'sanctum')->getJson('/api/cts?anio=2026&periodo=mayo')->assertOk()->json('data.filas'))
            ->firstWhere('id', $e->id);
        $this->assertSame('105-0012345-6', $fila['cuenta_ficha']);
        $this->assertSame('Caja Arequipa', $fila['entidad_ficha']);

        $this->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12'])->assertOk();

        $l->refresh();
        $this->assertSame('105-0012345-6', $l->numero_cuenta);
        $this->assertSame('Caja Arequipa', $l->entidad_financiera);
    }

    public function test_sin_cuenta_cts_no_se_registra_el_deposito_aunque_tenga_la_del_sueldo(): void
    {
        [$e, $l, $rrhh] = $this->conCts();

        $r = $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12'])
            ->assertStatus(422);

        $this->assertStringContainsString('Falta la cuenta CTS', $r->json('message'));
        $this->assertStringContainsString($e->apellido, $r->json('message'));
        $this->assertFalse($l->refresh()->depositada());
        $this->assertNull($l->numero_cuenta);
    }

    public function test_la_cuenta_escrita_al_depositar_a_una_persona_se_valida_y_se_usa(): void
    {
        Storage::fake('local');
        [, $l, $rrhh] = $this->conCts();
        $this->actingAs($rrhh, 'sanctum');

        $this->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12', 'entidad_financiera' => 'BBVA', 'numero_cuenta' => 'no-es-cuenta'])
            ->assertStatus(422)->assertJsonValidationErrors('numero_cuenta');
        $this->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12', 'numero_cuenta' => '0011-0200-0123456789'])
            ->assertStatus(422)->assertJsonValidationErrors('entidad_financiera');

        $this->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-05-12', 'entidad_financiera' => 'BBVA', 'numero_cuenta' => '0011-0200-0123456789'])
            ->assertOk();
        $this->assertSame('0011-0200-0123456789', $l->refresh()->numero_cuenta);
    }

    public function test_al_cesado_se_le_registra_sin_cuenta_porque_se_le_paga_directo(): void
    {
        Storage::fake('local');
        [, $l, $rrhh] = $this->conCts(['estado' => 'inactivo', 'fecha_cese' => '2026-03-31']);

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/cts/deposit', ['ids' => [$l->id], 'fecha_deposito' => '2026-04-02'])->assertOk();

        $l->refresh();
        $this->assertTrue($l->depositada());
        $this->assertNull($l->numero_cuenta, 'Al cesado no se le anota la cuenta del sueldo como si fuera la de la CTS.');
    }

    public function test_la_importacion_no_confunde_la_cuenta_cts_con_la_del_sueldo(): void
    {
        $columnas = collect(ColumnasDeEmpleado::reconocer(['DNI', 'Banco', 'N° de cuenta', 'Banco CTS', 'N° de cuenta CTS']))->pluck('campo')->all();

        $this->assertSame(['dni', 'entidad_financiera', 'numero_cuenta', 'cts_entidad_financiera', 'cts_numero_cuenta'], $columnas);
    }

    public function test_quien_no_lleva_empleados_no_ve_la_cuenta_cts(): void
    {
        $e = $this->crearEmpleado(['cts_entidad_financiera' => 'Caja Arequipa', 'cts_numero_cuenta' => '105-0012345-6']);
        \App\Models\Contrato::create(['empleado_id' => $e->id, 'tipo_contrato_id' => $e->tipo_contrato_id, 'fecha_inicio' => '2026-03-01', 'estado' => 'vigente']);
        $this->actingAs($this->crearUsuario('admin'), 'sanctum');

        $this->assertStringNotContainsString('105-0012345-6', $this->getJson('/api/contracts')->assertOk()->getContent());
        $this->assertStringNotContainsString('105-0012345-6', $this->getJson('/api/employee-files?page=0&size=5')->assertOk()->getContent());
    }
}
