<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Planilla;
use App\Support\AniosAnteriores;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Planillas y boletas de un año que ya pasó, para dejar de registro las que
 * antes se firmaban a mano. Hoy es 15/10/2026: "año anterior" es 2025.
 */
class PlanillasDeAniosAnterioresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function generarMes(string $mesAnio)
    {
        return $this->actingAs($this->crearUsuario('admin'), 'sanctum')->postJson('/api/payroll-runs', [
            'nombre'  => 'Planilla General',
            'meses'   => [$mesAnio],
            'generar' => true,
        ]);
    }

    public function test_un_anio_anterior_incluye_a_quien_ya_ceso_si_todavia_trabajaba_ese_mes(): void
    {
        $sigue     = $this->crearEmpleado(['apellido' => 'Sigue']);
        $cesoLuego = $this->crearEmpleado(['apellido' => 'CesoLuego', 'estado' => 'inactivo', 'fecha_cese' => '2025-06-30']);
        $cesoAntes = $this->crearEmpleado(['apellido' => 'CesoAntes', 'estado' => 'inactivo', 'fecha_cese' => '2024-12-31']);

        $this->generarMes('2025-03')->assertCreated();

        $conPlanilla = Planilla::where('mes', 3)->where('anio', 2025)->pluck('empleado_id');
        $this->assertTrue($conPlanilla->contains($sigue->id));
        $this->assertTrue($conPlanilla->contains($cesoLuego->id), 'en marzo 2025 todavía trabajaba');
        $this->assertFalse($conPlanilla->contains($cesoAntes->id), 'cesó en 2024: en 2025 ya no estaba');
    }

    public function test_en_el_anio_en_curso_quien_ceso_sigue_sin_planilla(): void
    {
        $cesado = $this->crearEmpleado(['estado' => 'inactivo', 'fecha_cese' => '2026-09-30']);

        $this->generarMes('2026-09')->assertCreated();

        $this->assertFalse(Planilla::where('empleado_id', $cesado->id)->exists());
    }

    /**
     * Marzo 2025 tiene 21 días hábiles. Quien cesó el viernes 14 trabajó 10:
     * del 3 al 7 y del 10 al 14. 2100 × 10/21 = 1000.
     */
    public function test_el_cese_a_mitad_de_mes_se_prorratea(): void
    {
        $empleado = $this->crearEmpleado([
            'sueldo_base' => 2100, 'estado' => 'inactivo', 'fecha_cese' => '2025-03-14',
        ]);

        $this->generarMes('2025-03')->assertCreated();

        $planilla = Planilla::where('empleado_id', $empleado->id)->where('mes', 3)->where('anio', 2025)->first();
        $this->assertNotNull($planilla);
        $this->assertSame(1000.0, (float) $planilla->sueldo_base);
    }

    public function test_la_boleta_de_un_anio_anterior_queda_firmada_en_papel_y_no_se_avisa(): void
    {
        Storage::fake('local');
        Mail::fake();

        $empleado = $this->crearEmpleado(['estado' => 'inactivo', 'fecha_cese' => '2025-12-31']);
        $this->crearUsuario('empleado', ['empleado_id' => $empleado->id]);
        $this->generarMes('2025-03')->assertCreated();

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->postJson('/api/payslips/generate-bulk', ['mes' => 3, 'anio' => 2025])
            ->assertOk()
            ->assertJson(['generadas' => 1]);

        $boleta = Documento::where('empleado_id', $empleado->id)->where('tipo', 'boleta')->first();
        $this->assertSame('en_papel', $boleta->estado_firma);
        $this->assertSame(0, Notificacion::count());
        Mail::assertNothingQueued();
    }

    public function test_apagado_no_deja_armar_anios_anteriores_pero_si_el_actual(): void
    {
        $this->crearEmpleado();
        Configuracion::poner(AniosAnteriores::AJUSTE, false);

        $this->generarMes('2025-03')->assertStatus(422);
        $this->assertSame(0, Planilla::where('anio', 2025)->count());

        $this->generarMes('2026-10')->assertCreated();
    }

    public function test_solo_el_administrador_cambia_el_ajuste(): void
    {
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson('/api/settings', [AniosAnteriores::AJUSTE => false])
            ->assertForbidden();

        $this->actingAs($this->crearUsuario('admin'), 'sanctum')
            ->putJson('/api/settings', [AniosAnteriores::AJUSTE => false])
            ->assertOk()
            ->assertJsonPath('data.' . AniosAnteriores::AJUSTE, false);

        $this->assertFalse(AniosAnteriores::permitidos());
    }
}
