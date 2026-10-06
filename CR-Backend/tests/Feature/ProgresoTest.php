<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Un proceso largo va dejando cuánto lleva, y solo quien lo pidió lo puede ver. */
class ProgresoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_proceso_deja_su_avance_y_solo_lo_ve_quien_lo_pidio(): void
    {
        Cache::store('file')->flush();
        $rrhh = $this->crearUsuario('rrhh');
        $ids = [$this->crearEmpleado()->id, $this->crearEmpleado()->id, $this->crearEmpleado()->id];

        $this->actingAs($rrhh, 'sanctum')
            ->withHeader('X-Progreso', 'prueba-1234')
            ->postJson('/api/employees/status', ['ids' => $ids, 'estado' => 'inactivo'])
            ->assertOk();

        $this->actingAs($rrhh, 'sanctum')->getJson('/api/progress/prueba-1234')
            ->assertOk()
            ->assertJsonPath('data.etapa', 'Dando de baja a los trabajadores')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.hechos', 3);

        // Otra cuenta, con el mismo id, no ve nada.
        $this->actingAs($this->crearUsuario('rrhh'), 'sanctum')->getJson('/api/progress/prueba-1234')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}
