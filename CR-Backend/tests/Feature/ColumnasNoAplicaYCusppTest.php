<?php

namespace Tests\Feature;

use App\Support\ColumnasDeEmpleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El Excel de empleados se llena entero: a quien está en ONP se le escribe
 * «No aplica» en las columnas de AFP, y el CUSPP puede llevar Ñ (así viene
 * en el PLAME: 540581FLUZÑ0).
 */
class ColumnasNoAplicaYCusppTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_aplica_en_las_columnas_de_afp_se_lee_como_vacio_sin_error(): void
    {
        foreach (['afp', 'tipo_comision_afp', 'cuspp'] as $campo) {
            $r = ColumnasDeEmpleado::leer($campo, 'No aplica', []);

            $this->assertNull($r['error'], $campo);
            $this->assertNull($r['valor'], $campo);
            $this->assertFalse($r['omitir'], $campo);
        }
    }

    public function test_el_cuspp_acepta_la_enie(): void
    {
        $empleado = $this->crearEmpleado();

        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->putJson("/api/employees/{$empleado->id}", [
                'sistema_pensiones' => 'AFP', 'afp' => 'Integra', 'tipo_comision_afp' => 'flujo', 'cuspp' => '540581FLUZÑ0',
            ])
            ->assertOk();

        $this->assertSame('540581FLUZÑ0', $empleado->fresh()->cuspp);
    }
}
