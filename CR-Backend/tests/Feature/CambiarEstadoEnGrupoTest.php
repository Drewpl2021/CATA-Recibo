<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Activar o dar de baja a varios trabajadores marcados en la lista, de una vez. */
class CambiarEstadoEnGrupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_da_de_baja_a_varios_y_salta_al_que_ya_estaba(): void
    {
        $a = $this->crearEmpleado(['apellido' => 'Apaza']);
        $b = $this->crearEmpleado(['apellido' => 'Benito']);
        $yaDeBaja = $this->crearEmpleado(['apellido' => 'Cruz', 'estado' => 'inactivo', 'fecha_cese' => '2026-01-31']);
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => (string) $a->id])->save();

        $respuesta = $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/employees/status', ['ids' => [$a->id, $b->id, $yaDeBaja->id], 'estado' => 'inactivo'])
            ->assertOk();

        $this->assertSame(['hechos' => 2, 'omitidos' => 1], $respuesta->json('data.resumen'));
        $this->assertSame('inactivo', $a->fresh()->estado);
        $this->assertSame(now()->toDateString(), substr((string) $a->fresh()->fecha_cese, 0, 10));
        $this->assertSame('inactivo', $cuenta->fresh()->estado_registro);
        // Al que ya estaba de baja no se le toca su fecha de cese real.
        $this->assertSame('2026-01-31', substr((string) $yaDeBaja->fresh()->fecha_cese, 0, 10));
    }

    public function test_reactivar_le_devuelve_la_cuenta_y_quita_la_fecha_de_cese(): void
    {
        $e = $this->crearEmpleado();
        $cuenta = $this->crearUsuario('empleado');
        $cuenta->forceFill(['empleado_id' => (string) $e->id])->save();
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', ['ids' => [$e->id], 'estado' => 'inactivo'])->assertOk();
        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', ['ids' => [$e->id], 'estado' => 'activo'])->assertOk();

        $this->assertSame('activo', $e->fresh()->estado);
        $this->assertNull($e->fresh()->fecha_cese);
        $this->assertSame('activo', $cuenta->fresh()->estado_registro);
    }

    public function test_dar_de_baja_y_reactivar_deja_el_contrato_como_estaba(): void
    {
        $rrhh = $this->crearUsuario('rrhh');
        $indeterminado = $this->crearEmpleado();
        $contratado = $this->crearEmpleado(['tipo_contrato_id' => $this->idTipoContrato('Contratado'), 'fecha_cese' => '2026-12-31']);
        \App\Models\Contrato::create(['empleado_id' => $indeterminado->id, 'tipo_contrato_id' => $this->idTipoContrato('Plazo indeterminado'), 'fecha_inicio' => '2020-03-01', 'estado' => 'vigente']);
        \App\Models\Contrato::create(['empleado_id' => $contratado->id, 'tipo_contrato_id' => $this->idTipoContrato('Contratado'), 'fecha_inicio' => '2026-03-01', 'fecha_fin' => '2026-12-31', 'estado' => 'vigente']);
        $ids = [$indeterminado->id, $contratado->id];

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', ['ids' => $ids, 'estado' => 'inactivo'])->assertOk();
        $this->assertFalse($indeterminado->fresh()->contratoVigente()->exists());

        $this->actingAs($rrhh, 'sanctum')->postJson('/api/employees/status', ['ids' => $ids, 'estado' => 'activo'])->assertOk();

        // Con su contrato vigente otra vez: el indeterminado sigue teniendo
        // vacaciones (la 5ta no le suma truncas) y el contratado, su 31/12.
        $this->assertTrue($indeterminado->fresh()->puedeTomarVacaciones());
        $this->assertNull($indeterminado->fresh()->contratoVigente()->first()->fecha_fin);
        $this->assertSame('2026-12-31', substr((string) $contratado->fresh()->contratoVigente()->first()->fecha_fin, 0, 10));
        $this->assertSame('2026-12-31', substr((string) $contratado->fresh()->fecha_cese, 0, 10));
        $this->assertNull($indeterminado->fresh()->fecha_cese);
    }

    public function test_reactivar_desde_la_ficha_tambien_devuelve_la_cuenta(): void
    {
        $e = $this->crearEmpleado(['estado' => 'inactivo', 'fecha_cese' => '2026-01-31']);
        $cuenta = $this->crearUsuario('empleado', ['estado_registro' => 'inactivo']);
        $cuenta->forceFill(['empleado_id' => (string) $e->id])->save();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$e->id}", ['estado' => 'activo'])
            ->assertOk();

        $this->assertSame('activo', $cuenta->fresh()->estado_registro);
    }

    public function test_nadie_se_da_de_baja_a_si_mismo_y_un_trabajador_no_puede_usarlo(): void
    {
        $propio = $this->crearEmpleado();
        $rrhh = $this->crearUsuario('rrhh');
        $rrhh->forceFill(['empleado_id' => (string) $propio->id])->save();

        $respuesta = $this->actingAs($rrhh, 'sanctum')
            ->postJson('/api/employees/status', ['ids' => [$propio->id], 'estado' => 'inactivo'])
            ->assertOk();
        $this->assertSame(0, $respuesta->json('data.resumen.hechos'));
        $this->assertSame('activo', $propio->fresh()->estado);

        $this->actingAs($rrhh, 'sanctum')->deleteJson("/api/employees/{$propio->id}")->assertStatus(422);

        $this->actingAs($this->crearUsuario('empleado'), 'sanctum')
            ->postJson('/api/employees/status', ['ids' => [$propio->id], 'estado' => 'inactivo'])
            ->assertForbidden();
    }
}
