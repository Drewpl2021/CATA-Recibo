<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Importar el PLAME registra a los cesados después de los activos: por fecha
 * de registro quedaban ellos primero en la lista de Empleados.
 */
class OrdenDelPersonalTest extends TestCase
{
    use RefreshDatabase;

    private function listar(string $parametros = ''): array
    {
        return $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')
            ->getJson("/api/employees?page=0&size=10{$parametros}")->assertOk()->json('data.content');
    }

    public function test_los_activos_salen_antes_que_los_cesados_y_lo_ultimo_registrado_arriba(): void
    {
        $antiguo = $this->crearEmpleado(['apellido' => 'Antiguo']);
        $antiguo->forceFill(['created_at' => now()->subDays(2)])->save();
        $nuevo = $this->crearEmpleado(['apellido' => 'Nuevo']);
        $nuevo->forceFill(['created_at' => now()->subDay()])->save();
        $this->crearEmpleado(['apellido' => 'Cesado', 'estado' => 'inactivo', 'fecha_cese' => '2026-01-31']);

        $this->assertSame(['Nuevo', 'Antiguo', 'Cesado'], array_column($this->listar(), 'apellido'));
    }

    public function test_ordenar_a_mano_no_separa_a_los_cesados(): void
    {
        $this->crearEmpleado(['apellido' => 'Zapata']);
        $this->crearEmpleado(['apellido' => 'Aguirre', 'estado' => 'inactivo', 'fecha_cese' => '2026-01-31']);

        $this->assertSame(['Aguirre', 'Zapata'], array_column($this->listar('&orden=apellido'), 'apellido'));
    }
}
