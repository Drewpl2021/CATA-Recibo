<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\PayrollDetalle;
use App\Models\Planilla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Eliminar una planilla la borra de verdad, y al volver a crearla entra
 * toda la gente: antes quedaban "Sin agrupar" y el sistema los saltaba.
 */
class EliminarPlanillaTest extends TestCase
{
    use RefreshDatabase;

    private function crearPlanilla(string $nombre): array
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->postJson('/api/payroll-runs', [
            'nombre' => $nombre, 'mes' => (int) now()->month, 'anio' => (int) now()->year, 'generar' => true,
        ])->assertCreated()->json('data');
    }

    public function test_eliminar_la_planilla_la_borra_y_se_puede_volver_a_crear(): void
    {
        Storage::fake('local');
        Mail::fake();
        $a = $this->crearEmpleado(['sueldo_base' => 2000]);
        $b = $this->crearEmpleado(['sueldo_base' => 2500]);

        $primera = $this->crearPlanilla('Planilla del mes');
        $this->assertSame(2, $primera['resumen']['generadas']);

        // Una boleta emitida (sin firmar) también se va con ella.
        $mes = (int) now()->month;
        $anio = (int) now()->year;
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->get("/api/payslips/{$a->id}/{$mes}/{$anio}")->assertOk();
        $archivo = Documento::where('empleado_id', $a->id)->value('archivo');
        Storage::disk('local')->assertExists($archivo);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->deleteJson('/api/payroll-runs/' . $primera['corrida']['id'])->assertOk();

        $this->assertSame(0, Planilla::count());
        $this->assertSame(0, PayrollDetalle::count());
        $this->assertSame(0, Documento::count());
        Storage::disk('local')->assertMissing($archivo);

        // Se vuelve a crear y entran los dos.
        $otra = $this->crearPlanilla('Planilla del mes');
        $this->assertSame(2, $otra['resumen']['generadas']);
        $this->assertSame(0, $otra['resumen']['omitidas']);
    }

    public function test_con_una_boleta_firmada_no_se_elimina(): void
    {
        Storage::fake('local');
        Mail::fake();
        $a = $this->crearEmpleado(['sueldo_base' => 2000]);
        $corrida = $this->crearPlanilla('Planilla del mes');
        $mes = (int) now()->month;
        $anio = (int) now()->year;
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->get("/api/payslips/{$a->id}/{$mes}/{$anio}")->assertOk();
        Documento::where('empleado_id', $a->id)->update(['estado_firma' => 'firmado']);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->deleteJson('/api/payroll-runs/' . $corrida['corrida']['id'])->assertStatus(409);
        $this->assertSame(1, Planilla::count());

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->deleteJson('/api/payrolls/' . Planilla::value('id'))->assertStatus(409);
    }

    public function test_quien_esta_sin_agrupar_pasa_a_la_planilla_nueva(): void
    {
        $a = $this->crearEmpleado(['sueldo_base' => 2000]);
        $suelta = Planilla::create(['empleado_id' => $a->id, 'mes' => (int) now()->month, 'anio' => (int) now()->year,
            'sueldo_base' => 2000, 'total' => 2000]);

        $corrida = $this->crearPlanilla('Planilla del mes');

        $this->assertSame(1, $corrida['resumen']['generadas']);
        $this->assertSame($corrida['corrida']['id'], $suelta->fresh()->corrida_id);
        $this->assertSame(1, Planilla::count());
    }

    public function test_eliminar_la_planilla_de_uno_deja_volver_a_generarla(): void
    {
        $a = $this->crearEmpleado(['sueldo_base' => 2000]);
        $corrida = $this->crearPlanilla('Planilla del mes');
        $planilla = Planilla::where('empleado_id', $a->id)->firstOrFail();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->deleteJson("/api/payrolls/{$planilla->id}")->assertOk();
        $this->assertSame(0, Planilla::count());

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->postJson('/api/payroll-runs/' . $corrida['corrida']['id'] . '/generate', [])
            ->assertOk()->assertJsonPath('data.resumen.generadas', 1);
    }
}
