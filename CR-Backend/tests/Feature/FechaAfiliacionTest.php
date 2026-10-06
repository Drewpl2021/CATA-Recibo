<?php

namespace Tests\Feature;

use App\Support\ColumnasDeEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La fecha de afiliación a la AFP u ONP: opcional, en la ficha y en el Excel. */
class FechaAfiliacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_guarda_se_puede_dejar_vacia_y_no_acepta_fechas_futuras(): void
    {
        $empleado = $this->crearEmpleado(['sistema_pensiones' => 'ONP']);
        $rrhh = $this->crearUsuario('rrhh');

        $this->actingAs($rrhh, 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", ['sistema_pensiones' => 'ONP', 'fecha_afiliacion' => '2015-03-15'])
            ->assertOk();
        $this->assertSame('2015-03-15', substr((string) $empleado->fresh()->fecha_afiliacion, 0, 10));

        $this->actingAs($rrhh, 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", ['fecha_afiliacion' => now()->addMonth()->toDateString()])
            ->assertStatus(422);

        $this->actingAs($rrhh, 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", ['sistema_pensiones' => 'ONP', 'fecha_afiliacion' => null])
            ->assertOk();
        $this->assertNull($empleado->fresh()->fecha_afiliacion);
    }

    public function test_quien_no_aporta_no_guarda_fecha_de_afiliacion(): void
    {
        $empleado = $this->crearEmpleado(['sistema_pensiones' => 'ONP', 'fecha_afiliacion' => '2015-03-15']);

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", ['sistema_pensiones' => null])
            ->assertOk();

        $this->assertNull($empleado->fresh()->fecha_afiliacion);
    }

    public function test_el_excel_la_reconoce_y_la_lee_como_dia_mes_anio(): void
    {
        $this->assertSame('fecha_afiliacion', ColumnasDeEmpleado::reconocer(['Fecha de afiliación'])[0]['campo']);

        $leida = ColumnasDeEmpleado::leer('fecha_afiliacion', '15/03/2015', []);
        $this->assertNull($leida['error']);
        $this->assertSame('2015-03-15', $leida['valor']);
    }
}
